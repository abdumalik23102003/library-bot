<?php

namespace App\Telegram\Navigation;

use App\Telegram\Screens\HelpScreen;
use App\Telegram\Screens\HomeScreen;
use App\Telegram\Screens\ProfileScreen;
use App\Telegram\Screens\RegPendingScreen;
use App\Telegram\Screens\RegProfileScreen;
use App\Telegram\Screens\RegRoleScreen;
use App\Telegram\Screens\TeacherApplicationScreen;

/**
 * Ekran kaliti -> sinf. Kalitlar faqat kichik lotin harflari (callback_data da ":" ishlatiladi).
 * "reg" bilan boshlanuvchi kalitlar faqat ro'yxatdan o'tmagan/tasdiqlanmagan foydalanuvchilar uchun (Entry::allows).
 */
final class ScreenRegistry
{
    private const MAP = [
        'home' => HomeScreen::class,
        'help' => HelpScreen::class,
        'profile' => ProfileScreen::class,
        'regrole' => RegRoleScreen::class,
        'regprofile' => RegProfileScreen::class,
        'regpending' => RegPendingScreen::class,
        'tapp' => TeacherApplicationScreen::class,
    ];

    public static function make(string $key): ?Screen
    {
        $class = self::MAP[$key] ?? null;

        return $class ? app($class) : null;
    }
}
