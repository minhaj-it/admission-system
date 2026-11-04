<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPayments;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use BuildsPayments, CreatesStaff, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->staff()->create();
    }

    private function method(string $key): PaymentMethod
    {
        return PaymentMethod::where('key', $key)->firstOrFail();
    }

    public function test_the_five_methods_exist_and_none_is_active_at_first(): void
    {
        $this->assertSame(['bkash', 'nagad', 'sslcommerz', 'shurjopay', 'manual'], PaymentMethod::orderBy('sort_order')->pluck('key')->all());
        $this->assertSame(0, PaymentMethod::where('is_active', true)->count());

        $this->actingAs($this->super)->get(route('admin.payment-methods.index'))->assertOk()->assertSee('ShurjoPay')->assertSee('Not available yet');
    }

    public function test_manual_needs_instructions_before_it_can_be_switched_on(): void
    {
        $manual = $this->method('manual');

        $this->actingAs($this->super)->patch(route('admin.payment-methods.activate', $manual))->assertSessionHasErrors('method');
        $this->assertFalse($manual->fresh()->is_active);

        $this->actingAs($this->super)->put(route('admin.payment-methods.instructions', $manual), ['instructions' => 'Pay to 01700000000'])->assertRedirect();
        $this->actingAs($this->super)->patch(route('admin.payment-methods.activate', $manual))->assertRedirect();

        $this->assertTrue($manual->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_method.activated']);
    }

    public function test_shurjopay_needs_its_credentials_in_the_env_file_and_they_are_never_shown(): void
    {
        $shurjo = $this->method('shurjopay');
        config(['payments.shurjopay.username' => null, 'payments.shurjopay.password' => null, 'payments.shurjopay.prefix' => null]);

        $this->actingAs($this->super)->patch(route('admin.payment-methods.activate', $shurjo))->assertSessionHasErrors('method');
        $this->actingAs($this->super)->get(route('admin.payment-methods.index'))->assertSee('SHURJOPAY_PASSWORD')->assertSee('is missing');

        $this->shurjoConfig();
        $page = $this->actingAs($this->super)->get(route('admin.payment-methods.index'))->assertSee('is set');
        $page->assertDontSee('api_secret_value')->assertDontSee('api_user');

        $this->actingAs($this->super)->patch(route('admin.payment-methods.activate', $shurjo))->assertRedirect();
        $this->assertTrue($shurjo->fresh()->is_active);
    }

    public function test_only_one_method_is_active_at_a_time(): void
    {
        $this->activateMethod('manual');
        $this->shurjoConfig();

        $this->actingAs($this->super)->patch(route('admin.payment-methods.activate', $this->method('shurjopay')))->assertRedirect();

        $this->assertTrue($this->method('shurjopay')->is_active);
        $this->assertFalse($this->method('manual')->is_active);
        $this->assertSame(1, PaymentMethod::where('is_active', true)->count());
    }

    public function test_methods_that_are_not_built_yet_cannot_be_switched_on(): void
    {
        foreach (['bkash', 'nagad', 'sslcommerz'] as $key) {
            $this->actingAs($this->super)->patch(route('admin.payment-methods.activate', $this->method($key)))->assertSessionHasErrors('method');
        }

        $this->assertSame(0, PaymentMethod::where('is_active', true)->count());
    }

    public function test_a_method_can_be_switched_off_and_emptied_instructions_switch_manual_off(): void
    {
        $this->activateMethod('manual');

        $this->actingAs($this->super)->put(route('admin.payment-methods.instructions', $this->method('manual')), ['instructions' => ''])->assertRedirect();
        $this->assertFalse($this->method('manual')->is_active, 'an active manual method must keep its instructions');

        $this->activateMethod('manual');
        $this->actingAs($this->super)->patch(route('admin.payment-methods.deactivate', $this->method('manual')))->assertRedirect();
        $this->assertSame(0, PaymentMethod::where('is_active', true)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_method.deactivated']);
    }

    public function test_instructions_only_exist_for_the_manual_method(): void
    {
        $this->actingAs($this->super)->put(route('admin.payment-methods.instructions', $this->method('shurjopay')), ['instructions' => 'x'])->assertNotFound();
    }

    public function test_permissions_are_enforced(): void
    {
        $viewer = $this->staffWith(['payment-method.view']);

        $this->actingAs($viewer)->get(route('admin.payment-methods.index'))->assertOk()->assertDontSee('Make active');
        $this->actingAs($viewer)->patch(route('admin.payment-methods.activate', $this->method('manual')))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.payment-methods.instructions', $this->method('manual')), ['instructions' => 'x'])->assertForbidden();

        $this->actingAs(User::factory()->staff('accountant')->create())->get(route('admin.payment-methods.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.payment-methods.index'))->assertForbidden();
    }
}
