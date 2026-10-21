<?php

namespace App\Telegram\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Broadcast auditoriyalari: faqat ro'yxatdan o'tgan va faol foydalanuvchilar (bloklangan, kutilayotgan va yuboruvchining o'zi yo'q).
 * Admin: hammaga/ustozlarga/o'quvchilarga/moderatorlarga. Moderator: faqat ustozlar va o'quvchilar.
 */
final class Broadcasts
{
    public const AUDIENCES = ['all', 'teacher', 'student', 'moderator'];

    /** @return array<int, string> */
    public static function audiences(User $actor): array
    {
        return $actor->isAdmin() ? self::AUDIENCES : ['all', 'teacher', 'student'];
    }

    /** $actor berilsa, shu foydalanuvchi uchun ruxsat etilgan auditoriya ekani ham tekshiriladi. */
    public static function valid(string $audience, ?User $actor = null): bool
    {
        return in_array($audience, $actor ? self::audiences($actor) : self::AUDIENCES, true);
    }

    public static function recipients(User $actor, string $audience): Builder
    {
        $query = User::query()->active()->whereNotNull('registered_at')->whereKeyNot($actor->id);

        if (! $actor->isStaff() || ! self::valid($audience, $actor)) {
            return $query->whereRaw('1 = 0');
        }

        return match ($audience) {
            'teacher' => $query->ofRole(Role::Teacher),
            'student' => $query->ofRole(Role::Student),
            'moderator' => $query->ofRole(Role::Moderator),
            default => $actor->isAdmin()
                ? $query->ofRole(Role::Teacher, Role::Student, Role::Moderator)
                : $query->ofRole(Role::Teacher, Role::Student),
        };
    }

    public static function count(User $actor, string $audience): int
    {
        return self::recipients($actor, $audience)->count();
    }

    public static function label(string $audience): string
    {
        return __('msg.bc.aud.' . (in_array($audience, self::AUDIENCES, true) ? $audience : 'all'));
    }
}
