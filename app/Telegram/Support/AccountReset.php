<?php

namespace App\Telegram\Support;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Download;
use App\Models\MaterialShare;
use App\Models\ProtectedAccess;
use App\Models\TeacherStudent;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Screens\WrScreen;
use Illuminate\Support\Facades\DB;

/**
 * Profilni o'chirish va qaytadan ro'yxatdan o'tish.
 * O'chirilgan foydalanuvchi qaytib kelsa (EnsureUser), fresh() bilan toza holatda tiklanadi.
 * Materiallar o'chmaydi (egasi o'sha qatorda qoladi), shuning uchun ustoz qaytib kelsa materiallari bilan uchrashadi.
 */
final class AccountReset
{
    /** Foydalanuvchining o'z profilini o'chirishi. */
    public static function deleteSelf(User $user): void
    {
        AuditLog::record($user, 'user.self_deleted', $user);

        self::wipe($user);
        $user->delete();
    }

    /** Foydalanuvchi bilan bog'liq holatni tozalaydi: aloqalar, kirishlar, sinflar, yuborilganlar, yuklab olishlar, navigatsiya. */
    public static function wipe(User $user): void
    {
        DB::transaction(function () use ($user) {
            TeacherStudent::query()
                ->where(fn ($q) => $q->where('teacher_id', $user->id)->orWhere('student_id', $user->id))
                ->delete();

            ProtectedAccess::query()
                ->where(fn ($q) => $q->where('teacher_id', $user->id)->orWhere('student_id', $user->id))
                ->delete();

            DB::table('classroom_student')->where('student_id', $user->id)->delete();
            Classroom::query()->where('teacher_id', $user->id)->delete();
            MaterialShare::query()->where('student_id', $user->id)->delete();
            Download::query()->where('user_id', $user->id)->delete();
        });

        Navigator::resetFor($user->telegram_id);
        WrScreen::forget($user);
    }

    /** O'chirilgan foydalanuvchi qaytganda: ro'yxatdan o'tmagan, toza holat (rol tanlashdan boshlanadi). */
    public static function fresh(User $user): void
    {
        $user->forceFill([
            'role' => Role::Student,
            'status' => UserStatus::Pending,
            'registered_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'blocked_reason' => null,
            'full_name' => null,
            'phone' => null,
            'subject' => null,
            'bio' => null,
        ])->save();
    }
}
