<?php

namespace Tests\Feature;

use App\Enums\Access;
use App\Enums\RequestStatus;
use App\Models\Material;
use App\Models\MaterialShare;
use App\Models\ProtectedAccess;
use App\Models\TeacherStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Material yuborish faqat yetkazadi: yuborilgan protected material o'quvchiga kirish BERMAYDI. */
class SharedMaterialAccessTest extends TestCase
{
    use RefreshDatabase;

    private function approvedStudent(User $teacher): User
    {
        $student = User::factory()->create();

        TeacherStudent::query()->create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'status' => RequestStatus::Approved,
        ]);

        return $student;
    }

    private function share(Material $material, User $student): void
    {
        MaterialShare::query()->create([
            'material_id' => $material->id,
            'student_id' => $student->id,
            'sent_by' => $material->owner_id,
        ]);
    }

    private function visible(User $viewer, Material $material): bool
    {
        return Material::query()->visibleTo($viewer)->whereKey($material->id)->exists();
    }

    public function test_sending_a_protected_material_does_not_grant_access(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = $this->approvedStudent($teacher);
        $material = Material::factory()->create(['owner_id' => $teacher->id, 'access' => Access::Protected]);

        $this->share($material, $student);

        $this->assertFalse($this->visible($student, $material));
        $this->assertSame(0, ProtectedAccess::query()->count());
    }

    public function test_protected_material_is_visible_only_with_real_protected_access(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = $this->approvedStudent($teacher);
        $material = Material::factory()->create(['owner_id' => $teacher->id, 'access' => Access::Protected]);

        ProtectedAccess::query()->create([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'status' => RequestStatus::Approved,
        ]);

        $this->assertTrue($this->visible($student, $material));
    }

    public function test_private_material_is_never_visible_to_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = $this->approvedStudent($teacher);
        $material = Material::factory()->create(['owner_id' => $teacher->id, 'access' => Access::Private]);

        $this->share($material, $student);

        $this->assertFalse($this->visible($student, $material));
    }
}
