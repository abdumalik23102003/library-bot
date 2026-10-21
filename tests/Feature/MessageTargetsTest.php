<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\TeacherStudent;
use App\Models\User;
use App\Telegram\Support\Broadcasts;
use App\Telegram\Support\MessageTargets;
use App\Telegram\Support\Messaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kim kimga yoza oladi va broadcast auditoriyalari (Telegramga yubormasdan). */
class MessageTargetsTest extends TestCase
{
    use RefreshDatabase;

    private function link(User $teacher, User $student, RequestStatus $status = RequestStatus::Approved): void
    {
        TeacherStudent::query()->create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'status' => $status->value,
        ]);
    }

    public function test_student_reaches_staff_and_own_approved_teachers_only(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $own = User::factory()->teacher()->create();
        $pendingLink = User::factory()->teacher()->create();
        $stranger = User::factory()->teacher()->create();
        $classmate = User::factory()->create();
        $student = User::factory()->create();

        $this->link($own, $student);
        $this->link($pendingLink, $student, RequestStatus::Pending);

        $ids = MessageTargets::pool($student)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$admin->id, $mod->id, $own->id], $ids);
        $this->assertTrue(Messaging::canWrite($student, $own));
        $this->assertFalse(Messaging::canWrite($student, $pendingLink));
        $this->assertFalse(Messaging::canWrite($student, $stranger));
        $this->assertFalse(Messaging::canWrite($student, $classmate));
    }

    public function test_teacher_reaches_staff_and_own_approved_students_only(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $mine = User::factory()->create();
        $blockedMine = User::factory()->blocked()->create();
        $other = User::factory()->create();
        $otherTeacher = User::factory()->teacher()->create();

        $this->link($teacher, $mine);
        $this->link($teacher, $blockedMine);

        $this->assertEqualsCanonicalizing([$admin->id, $mine->id], MessageTargets::pool($teacher)->pluck('id')->all());
        $this->assertFalse(Messaging::canWrite($teacher, $other));
        $this->assertFalse(Messaging::canWrite($teacher, $otherTeacher));
        $this->assertTrue(Messaging::canWrite($mine, $admin));
    }

    public function test_staff_reaches_every_active_registered_user(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        User::factory()->blocked()->create();
        User::factory()->create(['registered_at' => null]);

        $this->assertEqualsCanonicalizing([$mod->id, $teacher->id, $student->id], MessageTargets::pool($admin)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$admin->id, $teacher->id, $student->id], MessageTargets::pool($mod)->pluck('id')->all());
    }

    public function test_filters_and_search(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create(['full_name' => 'Aziza Karimova']);
        $student = User::factory()->create(['full_name' => 'Bobur Aliyev']);

        $this->assertSame([$teacher->id], MessageTargets::search($admin, 'teacher')->pluck('id')->all());
        $this->assertSame([$student->id], MessageTargets::search($admin, 'student')->pluck('id')->all());
        $this->assertSame([$teacher->id], MessageTargets::search($admin, 'all', 'aziz')->pluck('id')->all());
        $this->assertSame([], MessageTargets::search($admin, 'all', '%')->pluck('id')->all(), '% belgisi maxsus ma\'no bermaydi');
        $this->assertSame(['all', 'staff', 'teacher'], MessageTargets::filters($student));
        $this->assertSame(['all', 'staff', 'student'], MessageTargets::filters($teacher));
    }

    public function test_moderator_broadcast_goes_to_teachers_and_students_only(): void
    {
        $admin = User::factory()->admin()->create();
        $mod = User::factory()->moderator()->create();
        $otherMod = User::factory()->moderator()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();

        $ids = fn (User $actor, string $a) => Broadcasts::recipients($actor, $a)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$teacher->id, $student->id], $ids($mod, 'all'));
        $this->assertSame([], $ids($mod, 'moderator'), 'Moderator moderatorlarga yubora olmaydi');
        $this->assertFalse(Broadcasts::valid('moderator', $mod));
        $this->assertTrue(Broadcasts::valid('moderator', $admin));
        $this->assertEqualsCanonicalizing([$mod->id, $otherMod->id, $teacher->id, $student->id], $ids($admin, 'all'));
        $this->assertSame([], $ids($teacher, 'all'), "Ustoz broadcast yubora olmaydi");
    }

    public function test_language_keys_resolve(): void
    {
        foreach (['msg.menu', 'msg.hub.title', 'msg.wr.pick_title', 'msg.wr.f.staff', 'msg.inb.title', 'msg.bc.count_line', 'msg.wr.report'] as $key) {
            $this->assertNotSame($key, __($key), "Til kaliti topilmadi: {$key}");
        }
    }
}
