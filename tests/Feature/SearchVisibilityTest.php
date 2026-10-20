<?php

namespace Tests\Feature;

use App\Enums\Access;
use App\Enums\RequestStatus;
use App\Models\Material;
use App\Models\ProtectedAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Qidiruvda o'quvchi public+protected ni ko'radi, private ni hech qachon; ochish esa visibleTo() bilan. */
class SearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function mat(User $owner, Access $access): Material
    {
        return Material::factory()->create(['owner_id' => $owner->id, 'access' => $access]);
    }

    private function listed(User $viewer): array
    {
        return Material::query()->searchableBy($viewer)->pluck('id')->sort()->values()->all();
    }

    public function test_student_sees_public_and_protected_but_never_private_in_search(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $pub = $this->mat($teacher, Access::Public);
        $prot = $this->mat($teacher, Access::Protected);
        $this->mat($teacher, Access::Private);

        $this->assertSame([$pub->id, $prot->id], $this->listed($student));
    }

    public function test_protected_is_listed_but_not_openable_without_access(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $prot = $this->mat($teacher, Access::Protected);

        $this->assertContains($prot->id, $this->listed($student));
        $this->assertFalse(Material::query()->visibleTo($student)->whereKey($prot->id)->exists());

        ProtectedAccess::query()->create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'status' => RequestStatus::Approved,
        ]);

        $this->assertTrue(Material::query()->visibleTo($student)->whereKey($prot->id)->exists());
    }

    public function test_other_teacher_does_not_see_foreign_protected_or_private(): void
    {
        $owner = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $pub = $this->mat($owner, Access::Public);
        $this->mat($owner, Access::Protected);
        $this->mat($owner, Access::Private);

        $this->assertSame([$pub->id], $this->listed($other));
    }
}
