<?php

namespace App\Telegram\Support;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Foydalanuvchi qaysi ekranlarga kira olishini belgilaydi.
 * Ro'yxatdan o'tmagan yoki tasdiqlanmagan ustoz faqat "reg*" ekranlarini ko'radi.
 */
final class Entry
{
    /** Gate dan mustasno ekranlar: ruxsatni ekranning o'zi tekshiradi (tapp = ustoz arizasi tugmalari). */
    private const OPEN_SCREENS = ['tapp'];

    /** @return array{screen: string, parents: array<int, string>}|null  null = to'liq kirish bor */
    public static function gate(User $user): ?array
    {
        if ($user->isStaff()) {
            return null;
        }

        if ($user->registered_at === null) {
            return ['screen' => 'regrole', 'parents' => []];
        }

        if ($user->isTeacher() && $user->status === UserStatus::Pending) {
            return ['screen' => 'regpending', 'parents' => []];
        }

        return null;
    }

    public static function allows(User $user, string $screenKey): bool
    {
        if (in_array($screenKey, self::OPEN_SCREENS, true)) {
            return true;
        }

        return (self::gate($user) !== null) === str_starts_with($screenKey, 'reg');
    }
}
