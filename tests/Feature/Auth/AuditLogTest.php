<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public function test_entries_cannot_be_updated_or_deleted(): void
    {
        $log = AuditLog::record('test.action');

        try {
            $log->update(['action' => 'changed']);
            $this->fail('update should be refused');
        } catch (LogicException) {
            $this->assertSame('test.action', $log->fresh()->action);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_secrets_are_scrubbed_from_values(): void
    {
        $log = AuditLog::record('test.scrub', null, ['password' => 'secret', 'name' => 'A'], ['token' => 'abc']);

        $this->assertSame(['name' => 'A'], $log->old_values);
        $this->assertSame([], $log->new_values);
    }

    public function test_actor_and_ip_are_recorded(): void
    {
        $user = User::factory()->staff()->create();
        $this->actingAs($user);

        $log = AuditLog::record('test.actor');

        $this->assertSame($user->id, $log->actor_id);
    }

    public function test_only_users_with_audit_view_can_read_the_log(): void
    {
        AuditLog::record('user.created');

        $this->actingAs($this->staffWith(['audit.view']))->get(route('admin.audit.index'))->assertOk()->assertSee('user.created');
        $this->actingAs($this->staffWith(['audit.view']))->get(route('admin.audit.index', ['q' => 'role.']))->assertOk()->assertDontSee('user.created');
        $this->actingAs(User::factory()->staff('admin')->create())->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertForbidden();
    }
}
