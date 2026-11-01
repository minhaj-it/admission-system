<?php

namespace Tests\Feature\Admission;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsApplications;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class ApplicationDocumentsTest extends TestCase
{
    use BuildsApplications, CreatesStaff, RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function requireGd(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('The GD PHP extension is required (enable extension=gd in php.ini).');
        }
    }

    private function offeringWithDocs(): \App\Models\CircularOffering
    {
        return $this->openOffering(['documents' => [$this->docId('student_photo'), $this->docId('birth_certificate')]]);
    }

    private function uploadBirthCertificate(User $student, Application $application, ?UploadedFile $file = null)
    {
        return $this->saveStep($student, $application, 1, $this->step1([
            'doc_' . $this->docId('birth_certificate') => $file ?? UploadedFile::fake()->createWithContent('anything.pdf', self::PDF),
        ]));
    }

    public function test_a_pdf_is_stored_privately_under_a_random_name(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->offeringWithDocs());

        $this->uploadBirthCertificate($student, $application)->assertSessionHasNoErrors();

        $document = $application->documents()->firstOrFail();
        $this->assertSame('application/pdf', $document->mime);
        $this->assertStringStartsWith('applications/' . $application->uuid . '/', $document->path);
        $this->assertStringEndsWith('.pdf', $document->path);
        $this->assertStringNotContainsString('anything', $document->path);
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame(64, strlen($document->sha256));
    }

    public function test_a_photograph_is_decoded_and_reencoded_as_png(): void
    {
        $this->requireGd();
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->offeringWithDocs());

        $this->saveStep($student, $application, 1, $this->step1(['doc_' . $this->docId('student_photo') => UploadedFile::fake()->image('me.jpg', 1600, 1200)]))
            ->assertSessionHasNoErrors();

        $document = $application->documents()->firstOrFail();
        $this->assertSame('image/png', $document->mime);
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($document->path));
        [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($document->path));
        $this->assertLessThanOrEqual(800, max($width, $height));
    }

    public function test_wrong_files_are_refused(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->offeringWithDocs());
        $cert = 'doc_' . $this->docId('birth_certificate');
        $photo = 'doc_' . $this->docId('student_photo');

        // a text file renamed to .jpg / .pdf
        $this->uploadBirthCertificate($student, $application, UploadedFile::fake()->createWithContent('cert.pdf', 'just some text'))->assertSessionHasErrors($cert);
        // an executable / script
        $this->uploadBirthCertificate($student, $application, UploadedFile::fake()->createWithContent('cert.php', '<?php echo 1;'))->assertSessionHasErrors($cert);
        // a PDF offered as a photograph
        $this->saveStep($student, $application, 1, $this->step1([$photo => UploadedFile::fake()->createWithContent('photo.pdf', self::PDF)]))->assertSessionHasErrors($photo);

        $this->assertSame(0, $application->documents()->count());
    }

    public function test_the_size_limits_from_the_institute_profile_apply(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->offeringWithDocs());
        $institute = Institution::current();
        $institute->settings = ['image_max_kb' => 50, 'file_max_kb' => 100, 'file_types' => ['pdf']];
        $institute->save();
        Institution::forgetCurrent();

        $big = UploadedFile::fake()->createWithContent('big.pdf', self::PDF . str_repeat('x', 150 * 1024));
        $this->uploadBirthCertificate($student, $application, $big)->assertSessionHasErrors('doc_' . $this->docId('birth_certificate'));

        // only the formats the institute allows
        $this->uploadBirthCertificate($student, $application, UploadedFile::fake()->createWithContent('scan.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')))
            ->assertSessionHasErrors('doc_' . $this->docId('birth_certificate'));
    }

    public function test_replacing_and_removing_a_document_deletes_the_old_file(): void
    {
        $student = User::factory()->create();
        $application = $this->startApplication($student, $this->offeringWithDocs());

        $this->uploadBirthCertificate($student, $application);
        $first = $application->documents()->firstOrFail();
        $firstPath = $first->path;
        $first->forceFill(['verified_status' => 'verified'])->save();

        $this->uploadBirthCertificate($student, $application, UploadedFile::fake()->createWithContent('new.pdf', self::PDF . "\n% second"));
        $second = $application->documents()->firstOrFail();
        $this->assertSame(1, $application->documents()->count());
        $this->assertNotSame($firstPath, $second->path);
        Storage::disk('local')->assertMissing($firstPath);
        $this->assertSame('pending', $second->verified_status, 'a replaced file must be checked again');

        $this->saveStep($student, $application, 1, $this->step1(['remove_docs' => [$second->id]]));
        $this->assertSame(0, $application->documents()->count());
        Storage::disk('local')->assertMissing($second->path);
    }

    public function test_a_student_cannot_remove_someone_elses_document(): void
    {
        $offering = $this->offeringWithDocs();
        $owner = User::factory()->create();
        $first = $this->startApplication($owner, $offering);
        $this->uploadBirthCertificate($owner, $first);
        $foreign = $first->documents()->firstOrFail();

        $intruder = User::factory()->create();
        $second = $this->startApplication($intruder, $offering);
        $this->saveStep($intruder, $second, 1, $this->step1(['remove_docs' => [$foreign->id]]));

        $this->assertDatabaseHas('application_documents', ['id' => $foreign->id]);
        Storage::disk('local')->assertExists($foreign->path);
    }

    public function test_only_the_owner_and_authorised_staff_can_open_a_document(): void
    {
        $owner = User::factory()->create();
        $application = $this->startApplication($owner, $this->offeringWithDocs());
        $this->uploadBirthCertificate($owner, $application);
        $document = $application->documents()->firstOrFail();
        $url = route('documents.show', [$application, $document]);

        $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        // a draft is private even from staff who may see submitted applications
        $this->actingAs(User::factory()->staff('admission-officer')->create())->get($url)->assertForbidden();
    }

    public function test_staff_can_open_documents_of_submitted_applications_and_it_is_audited(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering(['documents' => [$this->docId('birth_certificate')]]);
        $application = $this->startApplication($student, $offering);
        $this->uploadBirthCertificate($student, $application);
        $this->completeSteps($student, $application);
        $this->submit($student, $application)->assertSessionHasNoErrors();
        $document = $application->documents()->firstOrFail();
        $url = route('documents.show', [$application, $document]);

        $this->actingAs(User::factory()->staff('admission-officer')->create())->get($url)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.viewed', 'subject_id' => $application->id]);

        $this->actingAs($this->staffWith(['dashboard.view']))->get($url)->assertForbidden();
    }

    public function test_a_document_id_from_another_application_does_not_work_under_a_different_application(): void
    {
        $offering = $this->offeringWithDocs();
        $a = User::factory()->create();
        $appA = $this->startApplication($a, $offering);
        $this->uploadBirthCertificate($a, $appA);
        $b = User::factory()->create();
        $appB = $this->startApplication($b, $offering);

        $this->actingAs($b)->get(route('documents.show', [$appB, $appA->documents()->firstOrFail()]))->assertNotFound();
    }

    public function test_staff_can_verify_and_reject_documents_and_a_remark_is_needed_to_reject(): void
    {
        $student = User::factory()->create();
        $offering = $this->openOffering(['documents' => [$this->docId('birth_certificate')]]);
        $application = $this->startApplication($student, $offering);
        $this->uploadBirthCertificate($student, $application);
        $this->completeSteps($student, $application);
        $this->submit($student, $application);
        $document = $application->documents()->firstOrFail();
        $officer = User::factory()->staff('admission-officer')->create();
        $route = route('admin.applicants.document', [$application, $document]);

        $this->actingAs($officer)->patch($route, ['verified_status' => 'rejected'])->assertSessionHasErrors('remark');
        $this->assertSame('pending', $document->fresh()->verified_status);

        $this->actingAs($officer)->patch($route, ['verified_status' => 'rejected', 'remark' => 'Blurred'])->assertRedirect();
        $this->assertSame('rejected', $document->fresh()->verified_status);
        $this->assertSame($officer->id, $document->fresh()->verified_by);

        $this->actingAs($officer)->patch($route, ['verified_status' => 'verified'])->assertRedirect();
        $this->assertSame('verified', $document->fresh()->verified_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.verified']);

        $this->actingAs($this->staffWith(['applicant-list.view']))->patch($route, ['verified_status' => 'verified'])->assertForbidden();
    }

    public function test_verification_fields_cannot_be_mass_assigned(): void
    {
        $document = new ApplicationDocument(['document_type_id' => 1, 'path' => 'x', 'mime' => 'y', 'size' => 1, 'sha256' => 'z', 'verified_status' => 'verified', 'verified_by' => 1]);

        $this->assertNull($document->verified_by);
        $this->assertNull($document->verified_status);
    }
}
