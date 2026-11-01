<?php

namespace Tests\Feature\Admission;

use App\Models\AcademicSession;
use App\Models\AdmissionCategory;
use App\Models\DocumentType;
use App\Models\SchoolClass;
use App\Models\Shift;
use App\Models\StudyGroup;
use App\Models\User;
use App\Models\Version;
use App\Services\OfferingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCirculars;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use BuildsCirculars, CreatesStaff, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->staff()->create();
    }

    public function test_the_lists_from_the_supplied_ui_are_seeded(): void
    {
        $this->assertSame(15, SchoolClass::count());
        $this->assertTrue(SchoolClass::where('name', 'Nine')->value('requires_group'));
        $this->assertFalse(SchoolClass::where('name', 'One')->value('requires_group'));
        $this->assertEqualsCanonicalizing(['Bengali', 'English'], Version::pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Morning', 'Day', 'Evening'], Shift::pluck('name')->all());
        $this->assertSame(3, StudyGroup::count());
        $this->assertSame(8, AdmissionCategory::count());
        $this->assertTrue(AcademicSession::where('name', '2027')->exists());
        $this->assertTrue(DocumentType::where('key', 'birth_certificate')->exists());
    }

    public function test_every_tab_renders_and_unknown_tabs_are_404(): void
    {
        foreach (['class', 'version', 'session', 'shift', 'group', 'category', 'document'] as $type) {
            $this->actingAs($this->super)->get(route('admin.master.index', $type))->assertOk()->assertSee('Add');
        }

        $this->actingAs($this->super)->get('/admin/settings/master')->assertOk()->assertSee('Kinder Garten (KG)');
        $this->actingAs($this->super)->get('/admin/settings/master/bogus')->assertNotFound();
    }

    public function test_items_can_be_added_edited_toggled_and_deleted(): void
    {
        $this->actingAs($this->super)->post(route('admin.master.store', 'shift'), ['name' => 'Night'])
            ->assertRedirect(route('admin.master.index', 'shift'));
        $shift = Shift::where('name', 'Night')->firstOrFail();
        $this->assertTrue($shift->is_active);
        $this->assertSame(4, $shift->sort_order);

        $this->actingAs($this->super)->put(route('admin.master.update', ['shift', $shift->id]), ['name' => 'Late'])->assertRedirect();
        $this->assertSame('Late', $shift->fresh()->name);

        $this->actingAs($this->super)->patch(route('admin.master.toggle', ['shift', $shift->id]))->assertRedirect();
        $this->assertFalse($shift->fresh()->is_active);

        $this->actingAs($this->super)->delete(route('admin.master.destroy', ['shift', $shift->id]))->assertRedirect();
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);

        foreach (['master.created', 'master.updated', 'master.deactivated', 'master.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_class_and_document_specific_fields(): void
    {
        $this->actingAs($this->super)->post(route('admin.master.store', 'class'), ['name' => 'Thirteen', 'requires_group' => 1]);
        $this->assertTrue(SchoolClass::where('name', 'Thirteen')->value('requires_group'));

        $this->actingAs($this->super)->post(route('admin.master.store', 'document'), ['name' => 'Vaccination Card', 'kind' => 'file', 'step' => 4]);
        $document = DocumentType::where('name', 'Vaccination Card')->firstOrFail();
        $this->assertSame('vaccination_card', $document->key);
        $this->assertSame('file', $document->kind);
        $this->assertSame(4, $document->step);

        $this->actingAs($this->super)->post(route('admin.master.store', 'document'), ['name' => 'Bad', 'kind' => 'video', 'step' => 4])->assertSessionHasErrors('kind');
    }

    public function test_duplicate_and_empty_names_are_rejected(): void
    {
        $this->actingAs($this->super)->post(route('admin.master.store', 'version'), ['name' => 'English'])->assertSessionHasErrors('name');
        $this->actingAs($this->super)->post(route('admin.master.store', 'version'), ['name' => '   '])->assertSessionHasErrors('name');
        $this->actingAs($this->super)->put(route('admin.master.update', ['version', $this->versionId('Bengali')]), ['name' => 'English'])->assertSessionHasErrors('name');
        $this->actingAs($this->super)->put(route('admin.master.update', ['version', $this->versionId('Bengali')]), ['name' => 'Bengali'])->assertRedirect();
    }

    public function test_an_item_used_by_a_circular_setup_cannot_be_deleted(): void
    {
        $circular = $this->makeCircular();
        app(OfferingService::class)->setup($circular, $this->setupPayload());

        $this->actingAs($this->super)->delete(route('admin.master.destroy', ['class', $this->classId('One')]))->assertSessionHasErrors('item');
        $this->actingAs($this->super)->delete(route('admin.master.destroy', ['version', $this->versionId('Bengali')]))->assertSessionHasErrors('item');
        $this->assertDatabaseHas('school_classes', ['name' => 'One']);

        // it can still be taken out of use
        $this->actingAs($this->super)->patch(route('admin.master.toggle', ['class', $this->classId('One')]))->assertRedirect();
        $this->assertFalse(SchoolClass::where('name', 'One')->value('is_active'));
    }

    public function test_permissions_are_enforced(): void
    {
        $reader = $this->staffWith(['classes.view']);
        $this->actingAs($reader)->get(route('admin.master.index', 'class'))->assertOk()->assertDontSee('id="addItem"', false);
        $this->actingAs($reader)->post(route('admin.master.store', 'class'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($reader)->patch(route('admin.master.toggle', ['class', 1]))->assertForbidden();
        $this->actingAs($reader)->delete(route('admin.master.destroy', ['class', 1]))->assertForbidden();

        $this->actingAs(User::factory()->create())->get(route('admin.master.index', 'class'))->assertForbidden();
    }
}
