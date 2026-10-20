<?php

namespace App\Telegram\Support;

use App\Enums\RequestStatus;
use App\Models\Classroom;
use App\Models\ProtectedAccess;
use App\Models\TeacherStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ustoz-o'quvchi aloqasi va himoyalangan (protected) kirish mantiqining YAGONA joyi.
 * Protected kirish faqat tasdiqlangan aloqa bo'lsa beriladi. Kirishni tekshirish: Material::visibleTo().
 */
final class TeacherLinks
{
    public static function link(User $teacher, User $student): ?TeacherStudent
    {
        return TeacherStudent::query()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->first();
    }

    public static function access(User $teacher, User $student): ?ProtectedAccess
    {
        return ProtectedAccess::query()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->first();
    }

    public static function isLinked(User $teacher, User $student): bool
    {
        return self::link($teacher, $student)?->status === RequestStatus::Approved;
    }

    // ---- Qo'shilish so'rovi -------------------------------------------------------------------------

    /** @return string  sent | pending | approved | invalid */
    public static function requestJoin(User $teacher, User $student): string
    {
        if (! $teacher->isTeacher() || ! $teacher->isActive() || ! $student->isStudent() || ! $student->isActive()) {
            return 'invalid';
        }

        $status = self::link($teacher, $student)?->status;

        if ($status === RequestStatus::Approved) {
            return 'approved';
        }

        if ($status === RequestStatus::Pending) {
            return 'pending';
        }

        TeacherStudent::query()->updateOrCreate(
            ['teacher_id' => $teacher->id, 'student_id' => $student->id],
            ['status' => RequestStatus::Pending, 'decided_at' => null],
        );

        return 'sent';
    }

    /** Faqat kutilayotgan so'rovni bekor qiladi (yozuv o'chiriladi). */
    public static function cancelJoin(User $teacher, User $student): bool
    {
        return TeacherStudent::query()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->where('status', RequestStatus::Pending->value)
            ->delete() > 0;
    }

    /** Kutilayotgan so'rovni qabul qiladi/rad etadi. false = so'rov kutilmayapti. */
    public static function decideJoin(User $teacher, User $student, bool $accept): bool
    {
        $link = self::link($teacher, $student);

        if ($link?->status !== RequestStatus::Pending) {
            return false;
        }

        $link->update([
            'status' => $accept ? RequestStatus::Approved : RequestStatus::Rejected,
            'decided_at' => now(),
        ]);

        return true;
    }

    /** O'quvchini ro'yxatdan chiqaradi: protected kirish va ustozning sinflaridan ham olib tashlanadi. */
    public static function removeStudent(User $teacher, User $student): void
    {
        DB::transaction(function () use ($teacher, $student) {
            $removed = ['status' => RequestStatus::Removed->value, 'decided_at' => now()];

            TeacherStudent::query()
                ->where('teacher_id', $teacher->id)
                ->where('student_id', $student->id)
                ->update($removed);

            ProtectedAccess::query()
                ->where('teacher_id', $teacher->id)
                ->where('student_id', $student->id)
                ->update($removed);

            DB::table('classroom_student')
                ->whereIn('classroom_id', Classroom::withTrashed()->where('teacher_id', $teacher->id)->select('id'))
                ->where('student_id', $student->id)
                ->delete();
        });
    }

    // ---- Protected kirish ------------------------------------------------------------------------------

    /** @return string  sent | pending | approved | not_linked */
    public static function requestProtected(User $teacher, User $student): string
    {
        if (! self::isLinked($teacher, $student)) {
            return 'not_linked';
        }

        $status = self::access($teacher, $student)?->status;

        if ($status === RequestStatus::Approved) {
            return 'approved';
        }

        if ($status === RequestStatus::Pending) {
            return 'pending';
        }

        ProtectedAccess::query()->updateOrCreate(
            ['teacher_id' => $teacher->id, 'student_id' => $student->id],
            ['status' => RequestStatus::Pending, 'decided_at' => null],
        );

        return 'sent';
    }

    public static function cancelProtected(User $teacher, User $student): bool
    {
        return ProtectedAccess::query()
            ->where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->where('status', RequestStatus::Pending->value)
            ->delete() > 0;
    }

    /** Kutilayotgan kirish so'rovini qabul qiladi/rad etadi. false = so'rov kutilmayapti. */
    public static function decideProtected(User $teacher, User $student, bool $grant): bool
    {
        $access = self::access($teacher, $student);

        if ($access?->status !== RequestStatus::Pending) {
            return false;
        }

        $access->update([
            'status' => $grant ? RequestStatus::Approved : RequestStatus::Rejected,
            'decided_at' => now(),
        ]);

        return true;
    }

    /** Ustoz so'rovsiz ham kirish bera oladi (faqat o'z o'quvchisiga). */
    public static function grantProtected(User $teacher, User $student): bool
    {
        if (! self::isLinked($teacher, $student)) {
            return false;
        }

        ProtectedAccess::query()->updateOrCreate(
            ['teacher_id' => $teacher->id, 'student_id' => $student->id],
            ['status' => RequestStatus::Approved, 'decided_at' => now()],
        );

        return true;
    }

    public static function revokeProtected(User $teacher, User $student): bool
    {
        $access = self::access($teacher, $student);

        if ($access?->status !== RequestStatus::Approved) {
            return false;
        }

        $access->update(['status' => RequestStatus::Removed, 'decided_at' => now()]);

        return true;
    }
}
