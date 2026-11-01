<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCircular;
use App\Models\Application;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $circulars = AdmissionCircular::open()->orderBy('opens_at')->get();
        $selected = $circulars->firstWhere('id', $request->integer('circular')) ?? $circulars->first();

        $offerings = $selected
            ? $selected->offerings()->where('is_active', true)
                ->with(['schoolClass', 'session', 'versions', 'shifts', 'groups', 'categories'])->get()
                ->sortBy(fn ($o) => [$o->schoolClass->sort_order, $o->session->name])->values()
            : collect();

        $classCounts = $offerings->countBy('school_class_id');

        // only what the browser needs to fill the dependent drop-downs; the server re-checks every id on submit
        $choices = $offerings
            ->filter(fn ($o) => $o->versions->isNotEmpty() && $o->shifts->isNotEmpty() && $o->categories->isNotEmpty())
            ->map(fn ($o) => [
                'id' => $o->id,
                'label' => $o->schoolClass->name . ($classCounts[$o->school_class_id] > 1 ? ' (' . $o->session->name . ')' : ''),
                'versions' => $o->versions->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values(),
                'shifts' => $o->shifts->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values(),
                'groups' => $o->groups->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values(),
                'categories' => $o->categories->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->values(),
            ])->values();

        $applications = Application::where('user_id', $request->user()->id)
            ->with(['circular', 'offering.schoolClass', 'version', 'shift', 'group', 'category', 'profile'])
            ->latest('id')->get();

        return view('student.home', [
            'institute' => Institution::current(),
            'circulars' => $circulars,
            'selected' => $selected,
            'choices' => $choices,
            'applications' => $applications,
        ]);
    }
}
