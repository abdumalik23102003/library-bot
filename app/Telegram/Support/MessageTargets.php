<?php

namespace App\Telegram\Support;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\TeacherStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kim kimga yoza oladi (yagona manba):
 *  - admin/moderator: ro'yxatdan o'tgan har bir faol foydalanuvchiga;
 *  - ustoz: administratsiyaga va O'ZINING tasdiqlangan o'quvchilariga;
 *  - o'quvchi: administratsiyaga va O'ZINING tasdiqlangan ustozlariga.
 */
final class MessageTargets
{
    public const FILTERS = ['all', 'staff', 'teacher', 'student'];

    /** Bir yuborishda eng ko'pi bilan qabul qiluvchilar. */
    public const MAX = 200;

    /** Shuncha va undan kam qabul qiluvchiga darhol, ko'proqqa navbat orqali yuboriladi. */
    public const SYNC_LIMIT = 10;

    /** Foydalanuvchi yoza oladigan barcha odamlar. */
    public static function pool(User $actor): Builder
    {
        $query = User::query()->active()->whereNotNull('registered_at')->whereKeyNot($actor->id);

        if ($actor->isStaff()) {
            return $query;
        }

        $linked = TeacherStudent::query()->where('status', RequestStatus::Approved->value);

        if ($actor->isTeacher()) {
            $linkedIds = $linked->where('teacher_id', $actor->id)->select('student_id');
            $linkedRole = Role::Student;
        } else {
            $linkedIds = $linked->where('student_id', $actor->id)->select('teacher_id');
            $linkedRole = Role::Teacher;
        }

        return $query->where(function (Builder $q) use ($linkedIds, $linkedRole) {
            $q->whereIn('role', [Role::Admin->value, Role::Moderator->value])
                ->orWhere(fn (Builder $w) => $w->ofRole($linkedRole)->whereIn('id', $linkedIds));
        });
    }

    /** Tanlash ekranidagi filtrlar (faqat foydalanuvchi uchun ma'noli bo'lganlari). */
    public static function filters(User $actor): array
    {
        return match (true) {
            $actor->isStaff() => ['all', 'staff', 'teacher', 'student'],
            $actor->isTeacher() => ['all', 'staff', 'student'],
            default => ['all', 'staff', 'teacher'],
        };
    }

    /** Filtr va qidiruv bo'yicha ro'yxat (ism, so'ng id bo'yicha tartiblangan). */
    public static function search(User $actor, string $filter = 'all', string $q = ''): Builder
    {
        $query = self::pool($actor);

        match ($filter) {
            'staff' => $query->ofRole(Role::Admin, Role::Moderator),
            'teacher' => $query->ofRole(Role::Teacher),
            'student' => $query->ofRole(Role::Student),
            default => null,
        };

        $q = trim($q);

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(fn (Builder $w) => $w->where('full_name', 'ilike', $like)->orWhere('username', 'ilike', $like));
        }

        return $query->orderBy('full_name')->orderBy('id');
    }

    public static function allows(User $actor, User $to): bool
    {
        return self::pool($actor)->whereKey($to->id)->exists();
    }
}
