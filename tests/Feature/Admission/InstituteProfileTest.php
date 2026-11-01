<?php

namespace Tests\Feature\Admission;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class InstituteProfileTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->super = User::factory()->staff()->create();
    }

    private function requireGd(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('The GD PHP extension is required (enable extension=gd in php.ini).');
        }
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Sample Public School & College',
            'short_name' => 'SPSC',
            'address' => 'Chattogram',
            'phone' => '01712345678',
            'email' => 'info@example.test',
            'website' => 'www.example.test',
            'image_max_kb' => 300,
            'file_max_kb' => 1024,
            'file_types' => ['pdf', 'jpg'],
        ], $override);
    }

    public function test_one_institute_exists_after_migration(): void
    {
        $this->assertSame(1, Institution::count());
    }

    public function test_page_renders_for_viewers_and_hides_saving_without_edit_permission(): void
    {
        $this->actingAs($this->super)->get(route('admin.institute.edit'))->assertOk()->assertSee('Save Profile');

        $reader = $this->staffWith(['institute.view']);
        $this->actingAs($reader)->get(route('admin.institute.edit'))->assertOk()->assertDontSee('Save Profile')->assertSee('view the profile only');
        $this->actingAs($reader)->put(route('admin.institute.update'), $this->payload())->assertForbidden();
    }

    public function test_profile_is_saved_and_audited_and_the_website_gets_https(): void
    {
        $this->actingAs($this->super)->put(route('admin.institute.update'), $this->payload())
            ->assertRedirect(route('admin.institute.edit'));

        $institute = Institution::first();
        $this->assertSame('Sample Public School & College', $institute->name);
        $this->assertSame('https://www.example.test', $institute->website);
        $this->assertSame(['pdf', 'jpg'], $institute->setting('file_types'));
        $this->assertSame(300, $institute->setting('image_max_kb'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'institute.updated']);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $actor = $this->actingAs($this->super);

        $actor->put(route('admin.institute.update'), $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $actor->put(route('admin.institute.update'), $this->payload(['phone' => 'abc']))->assertSessionHasErrors('phone');
        $actor->put(route('admin.institute.update'), $this->payload(['phone' => '123']))->assertSessionHasErrors('phone');
        $actor->put(route('admin.institute.update'), $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $actor->put(route('admin.institute.update'), $this->payload(['website' => 'javascript:alert(1)']))->assertSessionHasErrors('website');
        $actor->put(route('admin.institute.update'), $this->payload(['file_types' => ['exe']]))->assertSessionHasErrors('file_types.0');
        $actor->put(route('admin.institute.update'), $this->payload(['file_types' => []]))->assertSessionHasErrors('file_types');
        $actor->put(route('admin.institute.update'), $this->payload(['image_max_kb' => 99999]))->assertSessionHasErrors('image_max_kb');
    }

    public function test_logo_is_stored_privately_reencoded_and_served_through_the_branding_route(): void
    {
        $this->requireGd();

        $this->actingAs($this->super)->put(route('admin.institute.update'), $this->payload() + [
            'logo' => UploadedFile::fake()->image('logo.jpg', 1200, 800),
        ])->assertRedirect(route('admin.institute.edit'));

        $path = Institution::first()->logo_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('branding/logo-', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith("\x89PNG", Storage::disk('local')->get($path));

        auth()->logout();
        $this->get(route('branding.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_svg_and_oversized_logos_are_refused(): void
    {
        $this->requireGd();

        $actor = $this->actingAs($this->super);

        $actor->put(route('admin.institute.update'), $this->payload() + [
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertSessionHasErrors('logo');

        $actor->put(route('admin.institute.update'), $this->payload() + [
            'logo' => UploadedFile::fake()->image('big.png', 400, 400)->size(2048),
        ])->assertSessionHasErrors('logo');

        $this->assertNull(Institution::first()->logo_path);
    }

    public function test_logo_can_be_replaced_and_removed_and_old_files_are_deleted(): void
    {
        $this->requireGd();

        $this->actingAs($this->super)->put(route('admin.institute.update'), $this->payload() + ['logo' => UploadedFile::fake()->image('a.png', 100, 100)]);
        $first = Institution::first()->logo_path;

        $this->actingAs($this->super)->put(route('admin.institute.update'), $this->payload() + ['logo' => UploadedFile::fake()->image('b.png', 100, 100)]);
        $second = Institution::first()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->actingAs($this->super)->put(route('admin.institute.update'), $this->payload() + ['remove_logo' => 1]);
        $this->assertNull(Institution::first()->logo_path);
        Storage::disk('local')->assertMissing($second);

        auth()->logout();
        $this->get(route('branding.logo'))->assertNotFound();
    }
}
