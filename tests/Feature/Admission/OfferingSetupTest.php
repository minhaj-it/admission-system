<?php

namespace Tests\Feature\Admission;

use App\Models\AdmissionCircular;
use App\Models\CircularOffering;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Version;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCirculars;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class OfferingSetupTest extends TestCase
{
    use BuildsCirculars, CreatesStaff, RefreshDatabase;

    private User $super;
    private AdmissionCircular $circular;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->staff()->create();
        $this->circular = $this->makeCircular();
    }

    private function submit(array $payload)
    {
        return $this->actingAs($this->super)->post(route('admin.circulars.offerings.store', $this->circular), $payload);
    }

    public function test_the_class_page_renders_empty_and_with_setups(): void
    {
        $this->actingAs($this->super)->get(route('admin.circulars.show', $this->circular))->assertOk()->assertSee('No classes set up yet');

        $this->submit($this->setupPayload());

        $this->actingAs($this->super)->get(route('admin.circulars.show', $this->circular))->assertOk()->assertSee('One')->assertSee('2027');
    }

    public function test_one_submission_sets_up_several_classes_with_all_options(): void
    {
        $this->submit($this->setupPayload([
            'programs' => [$this->classId('One'), $this->classId('Two')],
            'categories' => [$this->categoryId('Civil'), $this->categoryId('Army')],
            'quotas' => [$this->categoryId('Civil') => 60, $this->categoryId('Army') => 10],
            'seats' => 100,
            'documents' => [DocumentType::where('key', 'birth_certificate')->value('id')],
            'roll_prefix' => 'bk',
            'gender_rule' => 'female',
        ]))->assertRedirect(route('admin.circulars.show', $this->circular));

        $this->assertSame(2, $this->circular->offerings()->count());

        $offering = $this->circular->offerings()->where('school_class_id', $this->classId('One'))->firstOrFail();
        $this->assertSame('BK', $offering->roll_prefix);
        $this->assertSame('female', $offering->gender_rule);
        $this->assertSame(100, $offering->seats);
        $this->assertCount(2, $offering->versions);
        $this->assertCount(1, $offering->shifts);
        $this->assertCount(2, $offering->categories);
        $this->assertSame(60, (int) $offering->categories->firstWhere('id', $this->categoryId('Civil'))->pivot->quota_seats);
        $this->assertSame(['Birth Registration Certificate'], $offering->documents->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'circular.class_setup', 'subject_id' => $this->circular->id]);
    }

    public function test_saving_the_same_class_and_session_again_updates_instead_of_duplicating(): void
    {
        $this->submit($this->setupPayload());
        $this->submit($this->setupPayload(['form_fee' => '650', 'versions' => [$this->versionId('English')], 'is_active' => 0]));

        $this->assertSame(1, CircularOffering::count());
        $offering = CircularOffering::firstOrFail();
        $this->assertSame(65000, Money::toMinor($offering->form_fee));
        $this->assertCount(1, $offering->versions);
        $this->assertFalse($offering->is_active);

        $this->submit($this->setupPayload(['academic_session_id' => $this->sessionId('2028')]));
        $this->assertSame(2, CircularOffering::count());
    }

    public function test_groups_are_required_for_group_classes_and_ignored_for_the_others(): void
    {
        $this->submit($this->setupPayload(['programs' => [$this->classId('Nine')]]))->assertSessionHasErrors('groups');
        $this->assertSame(0, CircularOffering::count());

        $this->submit($this->setupPayload(['programs' => [$this->classId('Nine'), $this->classId('One')], 'groups' => [$this->groupId('Science')]]))->assertSessionHasNoErrors();

        $nine = CircularOffering::where('school_class_id', $this->classId('Nine'))->firstOrFail();
        $one = CircularOffering::where('school_class_id', $this->classId('One'))->firstOrFail();
        $this->assertCount(1, $nine->groups);
        $this->assertCount(0, $one->groups);
    }

    public function test_the_payable_amount_only_counts_filled_in_fee_lines(): void
    {
        // school: admission and uniform fees left empty (collected after selection)
        $this->submit($this->setupPayload(['form_fee' => '500', 'bank_charge' => '30']));
        $school = CircularOffering::firstOrFail();
        $this->assertSame(53000, $school->payableMinor());
        $this->assertSame(['Application form fee', 'Bank / gateway charge'], array_keys($school->feeLines()));

        // college: everything filled in
        $this->submit($this->setupPayload(['programs' => [$this->classId('Eleven')], 'groups' => [$this->groupId('Science')], 'form_fee' => '500', 'admission_fee' => '2000', 'uniform_fee' => '1500.50', 'bank_charge' => '30']));
        $college = CircularOffering::where('school_class_id', $this->classId('Eleven'))->firstOrFail();
        $this->assertSame(403050, $college->payableMinor());
        $this->assertSame('৳4,030.50', Money::format($college->payableMinor()));

        // explicit zero is the same as empty
        $this->submit($this->setupPayload(['programs' => [$this->classId('Two')], 'admission_fee' => '0', 'uniform_fee' => '']));
        $this->assertSame(53000, CircularOffering::where('school_class_id', $this->classId('Two'))->firstOrFail()->payableMinor());
    }

    public function test_required_selections_and_formats_are_validated(): void
    {
        $this->submit($this->setupPayload(['programs' => []]))->assertSessionHasErrors('programs');
        $this->submit($this->setupPayload(['shifts' => []]))->assertSessionHasErrors('shifts');
        $this->submit($this->setupPayload(['versions' => []]))->assertSessionHasErrors('versions');
        $this->submit($this->setupPayload(['categories' => []]))->assertSessionHasErrors('categories');
        $this->submit($this->setupPayload(['form_fee' => '']))->assertSessionHasErrors('form_fee');
        $this->submit($this->setupPayload(['form_fee' => '-5']))->assertSessionHasErrors('form_fee');
        $this->submit($this->setupPayload(['form_fee' => '10.555']))->assertSessionHasErrors('form_fee');
        $this->submit($this->setupPayload(['uniform_fee' => 'abc']))->assertSessionHasErrors('uniform_fee');
        $this->submit($this->setupPayload(['roll_prefix' => 'bad prefix!']))->assertSessionHasErrors('roll_prefix');
        $this->submit($this->setupPayload(['roll_scope' => 'everything']))->assertSessionHasErrors('roll_scope');
        $this->submit($this->setupPayload(['gender_rule' => 'x']))->assertSessionHasErrors('gender_rule');
        $this->submit($this->setupPayload(['academic_session_id' => 99999]))->assertSessionHasErrors('academic_session_id');

        $this->assertSame(0, CircularOffering::count());
    }

    public function test_age_limit_fields_are_required_only_when_the_limit_is_on(): void
    {
        $this->submit($this->setupPayload())->assertSessionHasNoErrors();

        $this->submit($this->setupPayload(['programs' => [$this->classId('Two')], 'limit_age' => 1]))
            ->assertSessionHasErrors(['age_on', 'min_age_months', 'max_age_months']);

        $this->submit($this->setupPayload(['programs' => [$this->classId('Two')], 'limit_age' => 1, 'age_on' => '2027-01-01', 'min_age_months' => 60, 'max_age_months' => 48]))
            ->assertSessionHasErrors('max_age_months');

        $this->submit($this->setupPayload(['programs' => [$this->classId('Two')], 'limit_age' => 1, 'age_on' => '2027-01-01', 'min_age_months' => 48, 'max_age_months' => 60]))
            ->assertSessionHasNoErrors();
        $two = CircularOffering::where('school_class_id', $this->classId('Two'))->firstOrFail();
        $this->assertTrue($two->limit_age);
        $this->assertSame(60, $two->max_age_months);

        // switching the limit off clears the stored values
        $this->submit($this->setupPayload(['programs' => [$this->classId('Two')], 'limit_age' => 0]));
        $this->assertNull($two->fresh()->min_age_months);
    }

    public function test_quotas_must_match_the_selected_categories_and_fit_the_seats(): void
    {
        $civil = $this->categoryId('Civil');
        $navy = $this->categoryId('Navy');

        $this->submit($this->setupPayload(['quotas' => [$navy => 5]]))->assertSessionHasErrors('quotas');
        $this->submit($this->setupPayload(['seats' => 10, 'categories' => [$civil, $navy], 'quotas' => [$civil => 8, $navy => 5]]))->assertSessionHasErrors('seats');
        $this->assertSame(0, CircularOffering::count());

        $this->submit($this->setupPayload(['seats' => 10, 'categories' => [$civil, $navy], 'quotas' => [$civil => 6, $navy => 4]]))->assertSessionHasNoErrors();
        $this->assertSame(1, CircularOffering::count());
    }

    public function test_inactive_master_records_cannot_be_used_in_a_new_setup(): void
    {
        Version::where('name', 'English')->update(['is_active' => false]);

        $this->submit($this->setupPayload())->assertSessionHasErrors('versions.1');
        $this->assertSame(0, CircularOffering::count());
    }

    public function test_an_archived_circular_cannot_be_changed(): void
    {
        $this->circular->status = 'archived';
        $this->circular->save();

        $this->submit($this->setupPayload())->assertForbidden();
        $this->assertSame(0, CircularOffering::count());
    }

    public function test_download_settings_are_saved(): void
    {
        $this->actingAs($this->super)->put(route('admin.circulars.downloads', $this->circular), [
            'admit_card' => 1, 'application_form' => 1, 'id_card' => 1, 'admit_instruction' => 'Bring this card.',
        ])->assertRedirect(route('admin.circulars.show', $this->circular));

        $circular = $this->circular->fresh();
        $this->assertTrue($circular->downloadEnabled('admit_card'));
        $this->assertTrue($circular->downloadEnabled('id_card'));
        $this->assertFalse($circular->downloadEnabled('bus_card'));
        $this->assertSame('Bring this card.', $circular->admit_instruction);

        // without the admit card the instruction is dropped
        $this->actingAs($this->super)->put(route('admin.circulars.downloads', $this->circular), ['application_form' => 1, 'admit_instruction' => 'ignored']);
        $circular = $this->circular->fresh();
        $this->assertFalse($circular->downloadEnabled('admit_card'));
        $this->assertNull($circular->admit_instruction);
        $this->assertDatabaseHas('audit_logs', ['action' => 'circular.downloads_updated']);
    }

    public function test_permissions_are_enforced(): void
    {
        $reader = $this->staffWith(['circular.view']);

        $this->actingAs($reader)->get(route('admin.circulars.show', $this->circular))->assertOk()->assertDontSee('Class Setup');
        $this->actingAs($reader)->post(route('admin.circulars.offerings.store', $this->circular), $this->setupPayload())->assertForbidden();
        $this->actingAs($reader)->put(route('admin.circulars.downloads', $this->circular), ['admit_card' => 1])->assertForbidden();
        $this->assertSame(0, CircularOffering::count());
    }
}
