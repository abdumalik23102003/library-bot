<?php

namespace App\Telegram\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Broadcast auditoriyalari: faqat ro'yxatdan o'tgan va faol foydalanuvchilar (bloklangan, kutilayotgan va adminning o'zi yo'q). */
final class Broadcasts
{
    public const AUDIENCES = ['all', 'teacher', 'student', 'moderator'];

    public static function valid(string $audience): bool
    {
        return in_array($audience, self::AUDIENCES, true);
    }

    public static function recipients(User $admin, string $audience): Builder
    {
        $query = User::query()->active()->whereNotNull('registered_at')->whereKeyNot($admin->id);

        return match ($audience) {
            'teacher' => $query->ofRole(Role::Teacher),
            'student' => $query->ofRole(Role::Student),
            'moderator' => $query->ofRole(Role::Moderator),
            default => $query->ofRole(Role::Teacher, Role::Student, Role::Moderator),
        };
    }

    public static function count(User $admin, string $audience): int
    {
        return self::recipients($admin, $audience)->count();
    }

    public static function label(string $audience): string
    {
        return __('msg.bc.aud.' . (self::valid($audience) ? $audience : 'all'));
    }
}
