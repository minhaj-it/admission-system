<?php

namespace App\Services;

use App\Models\AdmissionCircular;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusHistory;
use App\Models\CircularOffering;
use App\Models\DocumentType;
use App\Models\User;
use App\Support\ApplicationData;
use App\Support\ApplicationRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    public function __construct(private DocumentService $documents)
    {
    }

    /**
     * Starts (or resumes) a draft. Everything that decides what the student is applying for is re-checked here:
     * the browser only sends ids.
     *
     * @return array{0: Application, 1: bool} the application and whether it was newly created
     */
    public function start(User $user, AdmissionCircular $circular, CircularOffering $offering, array $data): array
    {
        if (! $circular->isOpen()) {
            throw ValidationException::withMessages(['circular' => 'This admission circular is not open for applications.']);
        }

        if ($offering->circular_id !== $circular->id || ! $offering->is_active) {
            throw ValidationException::withMessages(['offering' => 'This class is not open for admission.']);
        }

        $versionId = (int) $data['version_id'];
        $shiftId = (int) $data['shift_id'];
        $categoryId = (int) $data['admission_category_id'];
        $groupId = ($data['study_group_id'] ?? null) ? (int) $data['study_group_id'] : null;

        if (! $offering->versions()->whereKey($versionId)->exists()) {
            throw ValidationException::withMessages(['version_id' => 'This version is not available for the selected class.']);
        }
        if (! $offering->shifts()->whereKey($shiftId)->exists()) {
            throw ValidationException::withMessages(['shift_id' => 'This shift is not available for the selected class.']);
        }
        if (! $offering->categories()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['admission_category_id' => 'This category is not available for the selected class.']);
        }

        if ($offering->groups()->exists()) {
            if (! $groupId || ! $offering->groups()->whereKey($groupId)->exists()) {
                throw ValidationException::withMessages(['study_group_id' => 'Select a group.']);
            }
        } else {
            $groupId = null;
        }

        $existing = Application::where('user_id', $user->id)->where('circular_offering_id', $offering->id)->where('version_id', $versionId)->first();
        if ($existing) {
            return [$existing, false];
        }

        for ($attempt = 0; $attempt < 6; $attempt++) {
            try {
                $application = new Application();
                $application->uuid = (string) \Illuminate\Support\Str::uuid();
                $application->tracking_no = random_int(10000000, 99999999);
                $application->user_id = $user->id;
                $application->circular_id = $circular->id;
                $application->circular_offering_id = $offering->id;
                $application->version_id = $versionId;
                $application->shift_id = $shiftId;
                $application->study_group_id = $groupId;
                $application->admission_category_id = $categoryId;
                $application->status = Application::DRAFT;
                $application->current_step = 1;
                $application->save();

                ApplicationStatusHistory::create([
                    'application_id' => $application->id, 'from_status' => null, 'to_status' => Application::DRAFT, 'actor_id' => $user->id,
                ]);

                return [$application, true];
            } catch (UniqueConstraintViolationException $e) {
                // a double click created it first, or the random tracking number collided: look again, then retry
                $existing = Application::where('user_id', $user->id)->where('circular_offering_id', $offering->id)->where('version_id', $versionId)->first();
                if ($existing) {
                    return [$existing, false];
                }
            }
        }

        throw ValidationException::withMessages(['offering' => 'Could not start the application. Please try again.']);
    }

    /**
     * Saves one validated step. $files maps document type id => uploaded file; $remove lists document ids to delete.
     *
     * @param array<int,UploadedFile> $files
     * @param list<int> $remove
     */
    public function saveStep(Application $application, int $step, array $data, array $files = [], array $remove = []): void
    {
        abort_unless($application->isEditable(), 403);

        DB::transaction(function () use ($application, $step, $data, $files, $remove) {
            match ($step) {
                1 => $this->saveProfile($application, $data),
                2 => $this->saveGuardians($application, $data),
                3 => $this->saveAddresses($application, $data),
                4 => $this->savePrevious($application, $data),
            };

            $types = ApplicationRules::documentTypes($application, $step)->keyBy('id');

            foreach ($remove as $documentId) {
                $document = $application->documents()->whereKey($documentId)->first();
                if ($document && $types->has($document->document_type_id)) {
                    $this->documents->remove($document);
                }
            }

            foreach ($files as $typeId => $file) {
                if ($file instanceof UploadedFile && $types->has($typeId)) {
                    $this->documents->store($application, $types[$typeId], $file);
                }
            }

            $done = array_values(array_unique(array_merge($application->steps_done ?? [], [$step])));
            sort($done);
            $application->steps_done = $done;
            $application->current_step = max($application->current_step, min($step + 1, 5));
            $application->save();
        });
    }

    /**
     * Final submission: transactional and idempotent. A second call on an already submitted application changes nothing.
     */
    public function submit(User $user, Application $application, string $ip): Application
    {
        try {
            return DB::transaction(function () use ($user, $application, $ip) {
                $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isEditable()) {
                    return $locked;
                }

                $locked->load(['profile', 'guardians', 'addresses', 'previous', 'documents', 'offering.documents', 'offering.versions', 'circular']);
                $this->assertReadyToSubmit($locked);

                $from = $locked->status;
                $offering = $locked->offering;
                $data = ApplicationData::flatten($locked);

                if ($locked->circular->unique_birth_id && ! empty($data['brn'])) {
                    $locked->dedupe_key = Application::dedupeKeyFor($data['brn']);
                }

                if (! $locked->application_no) {
                    $locked->application_no = $locked->nextApplicationNumber();
                }

                $locked->fee_snapshot = ['currency' => 'BDT', 'lines' => $offering->feeLines(), 'total' => $offering->payableMinor()];
                $locked->payable_minor = $offering->payableMinor();
                $locked->status = Application::SUBMITTED;
                $locked->submitted_at = $locked->submitted_at ?? now();
                $locked->consent_at = now();
                $locked->consent_ip = $ip;
                $locked->save();

                ApplicationStatusHistory::create([
                    'application_id' => $locked->id, 'from_status' => $from, 'to_status' => Application::SUBMITTED, 'actor_id' => $user->id,
                    'remark' => $from === Application::CORRECTION ? 'Corrected application resubmitted' : null,
                ]);

                return $locked;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['brn' => 'An application with this birth registration number already exists for this circular.']);
        }
    }

    /** Re-validates everything from the stored data: the saved steps are not trusted, the rules are applied again. */
    private function assertReadyToSubmit(Application $application): void
    {
        $circular = $application->circular;
        $offering = $application->offering;

        if (! $circular->isOpen() && $application->status === Application::DRAFT) {
            throw ValidationException::withMessages(['circular' => 'The application period of this circular is over.']);
        }

        if (! $offering->is_active) {
            throw ValidationException::withMessages(['circular' => 'This class is no longer open for admission.']);
        }

        $data = ApplicationData::flatten($application);
        $errors = [];

        foreach ([1, 2, 3, 4] as $step) {
            $validator = Validator::make($data, ApplicationRules::forStep($step, $application));
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[$field] = $messages[0];
            }
            if ($validator->errors()->isNotEmpty()) {
                $errors["step_{$step}"] = "Step {$step} is incomplete.";
            }
        }

        foreach (ApplicationRules::eligibilityErrors($offering, $data) as $field => $message) {
            $errors[$field] = $message;
        }

        $have = $application->documents->pluck('document_type_id')->all();
        foreach ($offering->documents as $type) {
            if ($type->pivot->is_required && ! in_array($type->id, $have, true)) {
                $errors['documents'] = 'Upload the required document: ' . $type->name . '.';
                break;
            }
        }

        if ($circular->unique_birth_id && ! empty($data['brn'])) {
            $duplicate = Application::where('circular_id', $circular->id)
                ->where('dedupe_key', Application::dedupeKeyFor($data['brn']))
                ->whereKeyNot($application->id)->exists();
            if ($duplicate) {
                $errors['brn'] = 'An application with this birth registration number already exists for this circular.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function saveProfile(Application $application, array $data): void
    {
        $values = [];
        foreach (ApplicationData::PROFILE as $key => $column) {
            $values[$column] = $data[$key] ?? null;
        }
        $application->profile()->updateOrCreate([], $values);
    }

    private function saveGuardians(Application $application, array $data): void
    {
        foreach (ApplicationData::ROLES as $role) {
            $values = [];
            foreach (ApplicationData::PERSON as $key => $column) {
                $values[$column] = $data["{$role}_{$key}"] ?? null;
            }
            if ($role === 'guardian') {
                $values['guardian_type'] = $data['guardian_type'] ?? null;
                $values['relation'] = $data['guardian_rel'] ?? null;
            }
            $application->guardians()->updateOrCreate(['role' => $role], $values);
        }
    }

    private function saveAddresses(Application $application, array $data): void
    {
        foreach (ApplicationData::ADDRESS as $key => $kind) {
            $application->addresses()->updateOrCreate(['kind' => $kind], ['address' => $data[$key] ?? null]);
        }
    }

    private function savePrevious(Application $application, array $data): void
    {
        $values = [];
        foreach (ApplicationData::PREVIOUS as $key => $column) {
            $values[$column] = $data[$key] ?? null;
        }
        $application->previous()->updateOrCreate([], $values);
    }
}
