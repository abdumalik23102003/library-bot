<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Telegram\Support\UserAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin/moderator qoidalari: kim kimni boshqaradi, bloklash, moderator tayinlash, ariza qarori. */
class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_cannot_manage_staff_but_admin_can_manage_moderator(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $other = User::factory()->moderator()->create();
        $student = User::factory()->create();

        $this->assertTrue(UserAdmin::can($mod, $student));
        $this->assertFalse(UserAdmin::can($mod, $other));
        $this->assertFalse(UserAdmin::can($mod, $admin));
        $this->assertFalse(UserAdmin::can($mod, $mod));
        $this->assertTrue(UserAdmin::can($admin, $mod));
        $this->assertFalse(UserAdmin::can($student, $mod));
    }

    public function test_block_and_unblock_restore_correct_status(): void
    {
        $mod = User::factory()->moderator()->create();
        $student = User::factory()->create();
        $unapproved = User::factory()->teacher()->pending()->create();

        $this->assertTrue(UserAdmin::block($mod, $student, 'Spam'));
        $this->assertSame(UserStatus::Blocked, $student->refresh()->status);
        $this->assertSame('Spam', $student->blocked_reason);
        $this->assertFalse(UserAdmin::block($mod, $student), "Ikkinchi marta bloklab bo'lmaydi");

        $this->assertTrue(UserAdmin::unblock($mod, $student));
        $this->assertSame(UserStatus::Active, $student->refresh()->status);
        $this->assertNull($student->blocked_reason);

        UserAdmin::block($mod, $unapproved);
        UserAdmin::unblock($mod, $unapproved);
        $this->assertSame(UserStatus::Pending, $unapproved->refresh()->status, "Tasdiqlanmagan ustoz faol bo'lib qolmasligi kerak");

        $this->assertSame(4, AuditLog::query()->whereIn('action', ['user.blocked', 'user.unblocked'])->count());
    }

    public function test_only_admin_appoints_and_removes_moderators(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $teacher = User::factory()->teacher()->create();
        $blocked = User::factory()->blocked()->create();

        $this->assertFalse(UserAdmin::makeModerator($mod, $teacher));
        $this->assertFalse(UserAdmin::makeModerator($admin, $blocked));
        $this->assertTrue(UserAdmin::makeModerator($admin, $teacher));
        $this->assertSame(Role::Moderator, $teacher->refresh()->role);

        $this->assertFalse(UserAdmin::removeModerator($mod, $teacher));
        $this->assertTrue(UserAdmin::removeModerator($admin, $teacher));
        $this->assertSame(Role::Student, $teacher->refresh()->role);
    }

    public function test_application_decision(): void
    {
        $mod = User::factory()->moderator()->create();
        $approved = User::factory()->teacher()->pending()->create();
        $rejected = User::factory()->teacher()->pending()->create();

        $this->assertSame(2, UserAdmin::pendingApplications()->count());

        $this->assertTrue(UserAdmin::decideApplication($mod, $approved, true));
        $this->assertSame(UserStatus::Active, $approved->refresh()->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertFalse(UserAdmin::decideApplication($mod, $approved, false), "Qayta qaror bo'lmaydi");

        $this->assertTrue(UserAdmin::decideApplication($mod, $rejected, false));
        $rejected->refresh();
        $this->assertSame(Role::Student, $rejected->role);
        $this->assertNull($rejected->registered_at);
        $this->assertSame(0, UserAdmin::pendingApplications()->count());
    }

    public function test_visible_users_and_search(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $student = User::factory()->create(['full_name' => 'Aziz Karimov', 'username' => 'aziz_k']);
        User::factory()->create(['registered_at' => null]);

        $this->assertEqualsCanonicalizing([$mod->id, $student->id], UserAdmin::visible($admin)->pluck('id')->all());
        $this->assertSame([$student->id], UserAdmin::visible($mod)->pluck('id')->all());

        $this->assertSame([$student->id], UserAdmin::search(UserAdmin::visible($admin), 'karim')->pluck('id')->all());
        $this->assertSame([$student->id], UserAdmin::search(UserAdmin::visible($admin), '@aziz_k')->pluck('id')->all());
        $this->assertSame([$student->id], UserAdmin::search(UserAdmin::visible($admin), (string) $student->telegram_id)->pluck('id')->all());
    }
}
