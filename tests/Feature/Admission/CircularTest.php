<?php

namespace Tests\Feature\Admission;

use App\Models\AdmissionCircular;
use App\Models\User;
use App\Services\OfferingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsCirculars;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class CircularTest extends TestCase
{
    use BuildsCirculars, CreatesStaff, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->staff()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_list_shows_published_circulars_and_the_toggle_reveals_the_rest(): void
    {
        $this->makeCircular(['title' => 'Live Circular']);
        $this->makeCircular(['title' => 'Hidden Draft'], 'draft');
        $this->makeCircular(['title' => 'Old Archive'], 'archived');

        $this->actingAs($this->super)->get(route('admin.circulars.index'))->assertOk()->assertSee('Live Circular')->assertDontSee('Hidden Draft')->assertDontSee('Old Archive');
        $this->actingAs($this->super)->get(route('admin.circulars.index', ['inactive' => 1]))->assertSee('Live Circular')->assertSee('Hidden Draft')->assertSee('Old Archive');
    }

    public function test_a_circular_is_created_as_published_or_draft_from_the_active_switch(): void
    {
        $this->actingAs($this->super)->post(route('admin.circulars.store'), $this->circularPayload())->assertRedirect();
        $this->actingAs($this->super)->post(route('admin.circulars.store'), $this->circularPayload(['title' => 'Draft one', 'is_active' => 0]))->assertRedirect();

        $live = AdmissionCircular::where('title', 'Admission Circular - 2028')->firstOrFail();
        $this->assertSame('published', $live->status);
        $this->assertSame('school', $live->type);
        $this->assertNotNull($live->institution_id);
        $this->assertSame($this->super->id, $live->created_by);
        $this->assertSame('draft', AdmissionCircular::where('title', 'Draft one')->value('status'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'circular.created']);
    }

    public function test_status_and_type_cannot_be_forced_from_the_browser(): void
    {
        $this->actingAs($this->super)->post(route('admin.circulars.store'), $this->circularPayload(['status' => 'archived', 'created_by' => 999, 'institution_id' => 999, 'is_active' => 0]));

        $circular = AdmissionCircular::firstOrFail();
        $this->assertSame('draft', $circular->status);
        $this->assertNotSame(999, $circular->created_by);
        $this->assertNotSame(999, $circular->institution_id);

        $this->actingAs($this->super)->post(route('admin.circulars.store'), $this->circularPayload(['type' => 'university']))->assertSessionHasErrors('type');
    }

    public function test_validation_rules(): void
    {
        $actor = $this->actingAs($this->super);

        $actor->post(route('admin.circulars.store'), $this->circularPayload(['title' => '']))->assertSessionHasErrors('title');
        $actor->post(route('admin.circulars.store'), $this->circularPayload(['closes_at' => '2028-01-01T00:00']))->assertSessionHasErrors('closes_at');
        $actor->post(route('admin.circulars.store'), $this->circularPayload(['closes_at' => '2028-01-05T00:00']))->assertSessionHasErrors('closes_at');
        $actor->post(route('admin.circulars.store'), $this->circularPayload(['circular_url' => 'javascript:alert(1)']))->assertSessionHasErrors('circular_url');
        $actor->post(route('admin.circulars.store'), $this->circularPayload(['circular_url' => 'ftp://example.test/a.pdf']))->assertSessionHasErrors('circular_url');
        $actor->post(route('admin.circulars.store'), $this->circularPayload(['video_url' => 'https://evil.example/video']))->assertSessionHasErrors('video_url');
        $actor->post(route('admin.circulars.store'), $this->circularPayload(['portal_host' => 'not a domain']))->assertSessionHasErrors('portal_host');
        $actor->post(route('admin.circulars.store'), $this->circularPayload(['portal_host' => '']))->assertSessionHasErrors('portal_host');

        $this->assertSame(0, AdmissionCircular::count());

        $actor->post(route('admin.circulars.store'), $this->circularPayload(['video_url' => 'https://www.youtube.com/watch?v=abcdefghijk']))->assertSessionHasNoErrors();
        $this->assertSame(1, AdmissionCircular::count());
    }

    public function test_update_keeps_the_type_and_maps_the_active_switch_to_the_status(): void
    {
        $circular = $this->makeCircular(['type' => 'college']);

        $this->actingAs($this->super)->put(route('admin.circulars.update', $circular), $this->circularPayload(['type' => 'school', 'title' => 'Renamed', 'is_active' => 0]))
            ->assertRedirect();

        $circular->refresh();
        $this->assertSame('college', $circular->type);
        $this->assertSame('Renamed', $circular->title);
        $this->assertSame('draft', $circular->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'circular.updated', 'subject_id' => $circular->id]);

        $this->actingAs($this->super)->put(route('admin.circulars.update', $circular), $this->circularPayload(['is_active' => 1]));
        $this->assertSame('published', $circular->fresh()->status);
    }

    public function test_an_archived_circular_stays_archived_when_edited_and_can_be_restored_as_a_draft(): void
    {
        $circular = $this->makeCircular();

        $this->actingAs($this->super)->patch(route('admin.circulars.archive', $circular))->assertRedirect();
        $this->assertSame('archived', $circular->fresh()->status);

        $this->actingAs($this->super)->put(route('admin.circulars.update', $circular), $this->circularPayload(['is_active' => 1]));
        $this->assertSame('archived', $circular->fresh()->status);

        $this->actingAs($this->super)->patch(route('admin.circulars.restore', $circular))->assertRedirect();
        $this->assertSame('draft', $circular->fresh()->status);

        $this->actingAs($this->super)->patch(route('admin.circulars.restore', $circular))->assertStatus(422);
    }

    public function test_effective_status_follows_the_dates(): void
    {
        $circular = $this->makeCircular(['opens_at' => '2027-03-01 09:00:00', 'closes_at' => '2027-03-31 17:00:00']);

        Carbon::setTestNow('2027-02-28 12:00:00');
        $this->assertSame('scheduled', $circular->effectiveStatus());
        $this->assertFalse($circular->isOpen());

        Carbon::setTestNow('2027-03-15 12:00:00');
        $this->assertSame('open', $circular->effectiveStatus());
        $this->assertTrue($circular->isOpen());
        $this->assertSame(1, AdmissionCircular::open()->count());

        Carbon::setTestNow('2027-04-01 00:00:00');
        $this->assertSame('closed', $circular->effectiveStatus());
        $this->assertSame(0, AdmissionCircular::open()->count());

        $circular->status = 'draft';
        $this->assertSame('draft', $circular->effectiveStatus());
        $this->assertFalse($circular->isOpen());
    }

    public function test_delete_is_blocked_once_classes_are_set_up(): void
    {
        $withClasses = $this->makeCircular();
        app(OfferingService::class)->setup($withClasses, $this->setupPayload());
        $empty = $this->makeCircular(['title' => 'Empty one']);

        $this->actingAs($this->super)->delete(route('admin.circulars.destroy', $withClasses))->assertSessionHasErrors('circular');
        $this->assertDatabaseHas('admission_circulars', ['id' => $withClasses->id]);

        $this->actingAs($this->super)->delete(route('admin.circulars.destroy', $empty))->assertRedirect();
        $this->assertDatabaseMissing('admission_circulars', ['id' => $empty->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'circular.deleted']);
    }

    public function test_permissions_are_enforced(): void
    {
        $reader = $this->staffWith(['circular.view']);
        $circular = $this->makeCircular();

        $this->actingAs($reader)->get(route('admin.circulars.index'))->assertOk()->assertDontSee('New Circular');
        $this->actingAs($reader)->get(route('admin.circulars.show', $circular))->assertOk();
        $this->actingAs($reader)->post(route('admin.circulars.store'), $this->circularPayload())->assertForbidden();
        $this->actingAs($reader)->put(route('admin.circulars.update', $circular), $this->circularPayload())->assertForbidden();
        $this->actingAs($reader)->patch(route('admin.circulars.archive', $circular))->assertForbidden();
        $this->actingAs($reader)->delete(route('admin.circulars.destroy', $circular))->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('admin.circulars.index'))->assertForbidden();
    }
}
