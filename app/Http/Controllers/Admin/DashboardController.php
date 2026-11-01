<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // two grouped queries instead of loading applications
        $byStatus = Application::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $submitted = $byStatus->except(Application::DRAFT)->sum();

        return view('admin.dashboard', [
            'drafts' => (int) ($byStatus[Application::DRAFT] ?? 0),
            'submitted' => (int) $submitted,
            'today' => Application::where('status', '!=', Application::DRAFT)->whereDate('submitted_at', today())->count(),
            'byStatus' => $byStatus,
            'perClass' => Application::query()->where('applications.status', '!=', Application::DRAFT)
                ->join('circular_offerings', 'circular_offerings.id', '=', 'applications.circular_offering_id')
                ->join('school_classes', 'school_classes.id', '=', 'circular_offerings.school_class_id')
                ->selectRaw('school_classes.name as class_name, school_classes.sort_order as sort_order, count(*) as total')
                ->groupBy('school_classes.name', 'school_classes.sort_order')->orderBy('sort_order')->get(),
            'recent' => Application::where('status', '!=', Application::DRAFT)->with(['profile', 'offering.schoolClass'])->latest('submitted_at')->limit(8)->get(),
        ]);
    }
}
