<?php

namespace Tests\Feature;

use App\Models\User;
use App\Telegram\Support\Broadcasts;
use App\Telegram\Support\Messaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Bot ichidagi xabarlar va broadcast qoidalari (Telegramga yubormasdan). */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_who_can_write_to_whom(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $blocked = User::factory()->blocked()->create();
        $unregistered = User::factory()->create(['registered_at' => null]);

        $this->assertTrue(Messaging::canWrite($student, $admin));
        $this->assertTrue(Messaging::canWrite($teacher, $mod));
        $this->assertTrue(Messaging::canWrite($admin, $teacher));
        $this->assertTrue(Messaging::canWrite($mod, $student));

        $this->assertFalse(Messaging::canWrite($student, $teacher), "O'quvchi ustozga yoza olmaydi");
        $this->assertFalse(Messaging::canWrite($teacher, $student), "Ustoz o'quvchiga bot orqali yoza olmaydi");
        $this->assertFalse(Messaging::canWrite($student, $student));
        $this->assertFalse(Messaging::canWrite($blocked, $admin), 'Bloklangan yoza olmaydi');
        $this->assertFalse(Messaging::canWrite($admin, $unregistered));
        $this->assertFalse(Messaging::canWrite($unregistered, $admin));
    }

    public function test_staff_pool_contains_only_active_admin_and_moderators(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        User::factory()->moderator()->blocked()->create();
        User::factory()->teacher()->create();
        User::factory()->create();

        $this->assertEqualsCanonicalizing([$admin->id, $mod->id], Messaging::staff()->pluck('id')->all());
    }

    public function test_text_validation(): void
    {
        $this->assertFalse(Messaging::validateText('   ')[0]);
        $this->assertFalse(Messaging::validateText(str_repeat('a', Messaging::MAX_TEXT + 1))[0]);
        $this->assertSame([true, 'Salom'], Messaging::validateText('  Salom '));
    }

    public function test_hourly_limit_applies_only_to_non_staff(): void
    {
        $student = User::factory()->create();
        $mod = User::factory()->moderator()->create();
        RateLimiter::clear("msg:send:{$student->id}");

        $this->assertNull(Messaging::tooMany($student));

        for ($i = 0; $i < Messaging::HOURLY_LIMIT; $i++) {
            Messaging::hit($student);
            Messaging::hit($mod);
        }

        $this->assertNotNull(Messaging::tooMany($student));
        $this->assertNull(Messaging::tooMany($mod), 'Xodimga chegara yo\'q');
    }

    public function test_broadcast_audiences_include_only_active_registered_users(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        User::factory()->blocked()->create();
        User::factory()->teacher()->pending()->create();
        User::factory()->create(['registered_at' => null]);

        $ids = fn (string $audience) => Broadcasts::recipients($admin, $audience)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$mod->id, $teacher->id, $student->id], $ids('all'));
        $this->assertSame([$teacher->id], $ids('teacher'));
        $this->assertSame([$student->id], $ids('student'));
        $this->assertSame([$mod->id], $ids('moderator'));
        $this->assertSame(3, Broadcasts::count($admin, 'all'));
        $this->assertFalse(Broadcasts::valid('everyone'));
    }
}
