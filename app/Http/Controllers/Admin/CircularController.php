<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CircularRequest;
use App\Http\Requests\Admin\DownloadsRequest;
use App\Models\AcademicSession;
use App\Models\AdmissionCategory;
use App\Models\AdmissionCircular;
use App\Models\DocumentType;
use App\Models\Institution;
use App\Models\SchoolClass;
use App\Models\Shift;
use App\Models\StudyGroup;
use App\Models\Version;
use App\Services\CircularService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CircularController extends Controller
{
    public function index(Request $request): View
    {
        $showInactive = $request->boolean('inactive');

        $circulars = AdmissionCircular::query()
            ->when(! $showInactive, fn ($q) => $q->where('status', 'published'))
            ->latest('opens_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.circulars.index', ['circulars' => $circulars, 'showInactive' => $showInactive, 'institute' => Institution::current()]);
    }

    public function store(CircularRequest $request, CircularService $service): RedirectResponse
    {
        $service->save($request->user(), null, $request->validated());

        return redirect()->route('admin.circulars.index', ['inactive' => 1])->with('status', 'Circular created.');
    }

    public function update(CircularRequest $request, AdmissionCircular $circular, CircularService $service): RedirectResponse
    {
        $service->save($request->user(), $circular, $request->validated());

        return redirect()->route('admin.circulars.index', ['inactive' => 1])->with('status', 'Circular updated.');
    }

    public function show(AdmissionCircular $circular): View
    {
        $offerings = $circular->offerings()
            ->with(['schoolClass', 'session', 'versions', 'shifts', 'groups', 'categories', 'documents'])
            ->get()
            ->sortBy([fn ($a, $b) => $a->schoolClass->sort_order <=> $b->schoolClass->sort_order, fn ($a, $b) => $a->session->name <=> $b->session->name])
            ->values();

        return view('admin.circulars.show', [
            'circular' => $circular,
            'institute' => Institution::current(),
            'offerings' => $offerings,
            'classes' => SchoolClass::active()->ordered()->get(),
            'sessions' => AcademicSession::active()->ordered()->get(),
            'versions' => Version::active()->ordered()->get(),
            'shifts' => Shift::active()->ordered()->get(),
            'groups' => StudyGroup::active()->ordered()->get(),
            'categories' => AdmissionCategory::active()->ordered()->get(),
            'documents' => DocumentType::active()->ordered()->get(),
        ]);
    }

    public function downloads(DownloadsRequest $request, AdmissionCircular $circular, CircularService $service): RedirectResponse
    {
        $service->saveDownloads($circular, $request->validated());

        return redirect()->route('admin.circulars.show', $circular)->with('status', 'Download settings saved.');
    }

    public function archive(AdmissionCircular $circular, CircularService $service): RedirectResponse
    {
        $service->archive($circular);

        return redirect()->route('admin.circulars.index', ['inactive' => 1])->with('status', 'Circular archived.');
    }

    public function restore(AdmissionCircular $circular, CircularService $service): RedirectResponse
    {
        $service->restore($circular);

        return redirect()->route('admin.circulars.index', ['inactive' => 1])->with('status', 'Circular restored as a draft.');
    }

    public function destroy(AdmissionCircular $circular, CircularService $service): RedirectResponse
    {
        $service->delete($circular);

        return redirect()->route('admin.circulars.index', ['inactive' => 1])->with('status', 'Circular deleted.');
    }
}
