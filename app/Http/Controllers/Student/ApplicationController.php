<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ApplicationStepRequest;
use App\Http\Requests\Student\StartApplicationRequest;
use App\Http\Requests\Student\SubmitApplicationRequest;
use App\Models\AdmissionCircular;
use App\Models\Application;
use App\Models\CircularOffering;
use App\Models\Institution;
use App\Services\ApplicationService;
use App\Support\ApplicationData;
use App\Support\ApplicationRules;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function start(StartApplicationRequest $request, ApplicationService $service): RedirectResponse
    {
        $circular = AdmissionCircular::findOrFail($request->integer('circular_id'));
        $offering = CircularOffering::findOrFail($request->integer('circular_offering_id'));

        [$application, $created] = $service->start($request->user(), $circular, $offering, $request->validated());

        $route = $application->isEditable()
            ? route('student.applications.step', [$application, min(max($application->current_step, 1), 5)])
            : route('student.applications.show', $application);

        return redirect($route)->with('status', $created ? 'Application started. Your tracking number is ' . $application->tracking_no . '.' : 'You already have an application for this selection.');
    }

    public function show(Application $application): View
    {
        Gate::authorize('view', $application);

        $this->loadAll($application);

        return view('student.applications.show', [
            'application' => $application,
            'data' => ApplicationData::flatten($application),
            'institute' => Institution::current(),
        ]);
    }

    public function step(Application $application, int $step): View|RedirectResponse
    {
        Gate::authorize('view', $application);

        if (! $application->isEditable()) {
            return redirect()->route('student.applications.show', $application);
        }

        $this->loadAll($application);

        return view('student.applications.step', [
            'application' => $application,
            'step' => $step,
            'data' => ApplicationData::flatten($application),
            'institute' => Institution::current(),
            'docTypes' => $step === 5 ? collect() : ApplicationRules::documentTypes($application, $step),
            'documents' => $application->documents->keyBy('document_type_id'),
            'options' => [
                'blood' => FormOptions::BLOOD, 'religion' => FormOptions::RELIGION, 'gender' => FormOptions::GENDER,
                'occupation' => FormOptions::OCCUPATION, 'guardian_type' => FormOptions::GUARDIAN_TYPE,
            ],
        ]);
    }

    public function saveStep(ApplicationStepRequest $request, Application $application, ApplicationService $service, int $step): RedirectResponse
    {
        $files = [];
        foreach (ApplicationRules::documentTypes($application, $step) as $type) {
            if ($file = $request->file('doc_' . $type->id)) {
                $files[$type->id] = $file;
            }
        }

        $service->saveStep(
            $application,
            $step,
            Arr::only($request->validated(), ApplicationData::stepKeys($step)),
            $files,
            array_map('intval', (array) $request->input('remove_docs', []))
        );

        return redirect()->route('student.applications.step', [$application, $step + 1]);
    }

    public function submit(SubmitApplicationRequest $request, Application $application, ApplicationService $service): RedirectResponse
    {
        $submitted = $service->submit($request->user(), $application, (string) $request->ip());

        return redirect()->route('student.applications.show', $submitted)
            ->with('status', 'Application submitted. Your application number is ' . $submitted->application_no . '.');
    }

    private function loadAll(Application $application): void
    {
        $application->load([
            'circular', 'offering.schoolClass', 'offering.session', 'offering.documents', 'version', 'shift', 'group', 'category',
            'profile', 'guardians', 'addresses', 'previous', 'documents.type', 'histories.actor',
        ]);
    }
}
