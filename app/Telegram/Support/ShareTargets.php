<?php

namespace App\Telegram\Support;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Classroom;
use App\Models\TeacherStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Material yuborish qabul qiluvchilari: faqat ustozning TASDIQLANGAN (approved) va faol o'quvchilari.
 * Qabul qiluvchilar ro'yxati har safar bazadan qayta hisoblanadi (eskirgan tugmalardan himoya).
 */
final class ShareTargets
{
    public const MODES = ['one', 'cls', 'all'];

    /** Ustozning tasdiqlangan, faol o'quvchilari. */
    public static function students(User $teacher): Builder
    {
        return User::query()
            ->ofRole(Role::Student)
            ->active()
            ->whereIn('id', TeacherStudent::query()
                ->select('student_id')
                ->where('teacher_id', $teacher->id)
                ->where('status', RequestStatus::Approved->value));
    }

    /**
     * @param  string  $mode  one (o'quvchi id) | cls (sinf id) | all
     * @return array<int, int>  o'quvchilar id lari
     */
    public static function resolve(User $teacher, string $mode, int $arg = 0): array
    {
        $query = self::students($teacher);

        if ($mode === 'one') {
            $query->whereKey($arg);
        } elseif ($mode === 'cls') {
            $owned = Classroom::query()->where('teacher_id', $teacher->id)->whereKey($arg)->exists();

            if (! $owned) {
                return [];
            }

            $query->whereIn('id', DB::table('classroom_student')->select('student_id')->where('classroom_id', $arg));
        } elseif ($mode !== 'all') {
            return [];
        }

        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
