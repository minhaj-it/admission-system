<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Institution;
use App\Models\StudentProfile;
use App\Services\ReviewService;
use App\Support\ApplicationData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicantController extends Controller
{
    private const SORTS = ['submitted_at' => 'Submitted', 'tracking_no' => 'Tracking no', 'name' => 'Name'];

    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'circular_id' => ['nullable', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'version_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'study_group_id' => ['nullable', 'integer'],
            'admission_category_id' => ['nullable', 'integer'],
            'gender' => ['nullable', Rule::in(['Female', 'Male', 'Other'])],
            'status' => ['nullable', Rule::in(array_keys(array_diff_key(Application::STATUSES, [Application::DRAFT => 1])))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $f['sort'] ?? 'submitted_at';
        $dir = ($f['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $q = trim((string) ($f['q'] ?? ''));

        $applications = Application::query()
            ->where('status', '!=', Application::DRAFT) // unfinished drafts are never shown to staff
            ->with(['profile', 'offering.schoolClass', 'offering.session', 'version', 'shift', 'group', 'category'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . addcslashes($q, '\\%_') . '%';
                $digits = preg_replace('/\D/', '', $q);
                $query->where(function ($w) use ($q, $like, $digits) {
                    $w->where('application_no', 'like', $like)
                        ->orWhereHas('profile', fn ($p) => $p->where('name', 'like', $like)->orWhere('emergency_phone', 'like', $like)->orWhere('alt_phone', 'like', $like));
                    if ($digits !== '' && strlen($digits) >= 6) {
                        $w->orWhere('tracking_no', (int) $digits);
                    }
                });
            })
            ->when($f['circular_id'] ?? null, fn ($query, $v) => $query->where('circular_id', $v))
            ->when($f['school_class_id'] ?? null, fn ($query, $v) => $query->whereHas('offering', fn ($o) => $o->where('school_class_id', $v)))
            ->when($f['version_id'] ?? null, fn ($query, $v) => $query->where('version_id', $v))
            ->when($f['shift_id'] ?? null, fn ($query, $v) => $query->where('shift_id', $v))
            ->when($f['study_group_id'] ?? null, fn ($query, $v) => $query->where('study_group_id', $v))
            ->when($f['admission_category_id'] ?? null, fn ($query, $v) => $query->where('admission_category_id', $v))
            ->when($f['gender'] ?? null, fn ($query, $v) => $query->whereHas('profile', fn ($p) => $p->where('gender', $v)))
            ->when($f['status'] ?? null, fn ($query, $v) => $query->where('status', $v))
            ->when($f['from'] ?? null, fn ($query, $v) => $query->whereDate('submitted_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($query, $v) => $query->whereDate('submitted_at', '<=', $v))
            ->when(
                $sort === 'name',
                fn ($query) => $query->orderBy(StudentProfile::select('name')->whereColumn('application_id', 'applications.id'), $dir),
                fn ($query) => $query->orderBy($sort, $dir)
            )
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.applicants.index', [
            'applications' => $applications,
            'filters' => $f,
            'sorts' => self::SORTS,
            'circulars' => \App\Models\AdmissionCircular::orderByDesc('opens_at')->get(['id', 'title']),
            'classes' => \App\Models\SchoolClass::ordered()->get(['id', 'name']),
            'versions' => \App\Models\Version::ordered()->get(['id', 'name']),
            'shifts' => \App\Models\Shift::ordered()->get(['id', 'name']),
            'groups' => \App\Models\StudyGroup::ordered()->get(['id', 'name']),
            'categories' => \App\Models\AdmissionCategory::ordered()->get(['id', 'name']),
        ]);
    }

    public function show(Application $application): View
    {
        Gate::authorize('view', $application);

        $application->load([
            'circular', 'offering.schoolClass', 'offering.session', 'offering.documents', 'version', 'shift', 'group', 'category',
            'profile', 'guardians', 'addresses', 'previous', 'documents.type', 'documents.verifier', 'histories.actor', 'user',
        ]);

        return view('admin.applicants.show', [
            'application' => $application,
            'data' => ApplicationData::flatten($application),
            'institute' => Institution::current(),
            'transitions' => Application::TRANSITIONS[$application->status] ?? [],
            'canSeeFullIds' => auth()->user()->can('applicant-edit.view'),
        ]);
    }

    public function status(Request $request, Application $application, ReviewService $review): RedirectResponse
    {
        Gate::authorize('view', $application);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Application::STATUSES))],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);

        $review->changeStatus($request->user(), $application, $data['status'], $data['remark'] ?? null);

        return redirect()->route('admin.applicants.show', $application)->with('status', 'Status updated.');
    }

    public function document(Request $request, Application $application, ApplicationDocument $document, ReviewService $review): RedirectResponse
    {
        Gate::authorize('view', $application);
        abort_unless($document->application_id === $application->id, 404);

        $data = $request->validate([
            'verified_status' => ['required', Rule::in(['verified', 'rejected', 'pending'])],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $review->verifyDocument($request->user(), $document, $data['verified_status'], $data['remark'] ?? null);

        return redirect()->route('admin.applicants.show', $application)->with('status', 'Document updated.');
    }
}
