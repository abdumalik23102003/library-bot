<?php

namespace App\Telegram\Navigation;

use App\Telegram\Screens\CatNewScreen;
use App\Telegram\Screens\CatScreen;
use App\Telegram\Screens\CatsScreen;
use App\Telegram\Screens\DlsScreen;
use App\Telegram\Screens\HelpScreen;
use App\Telegram\Screens\MatScreen;
use App\Telegram\Screens\MatsScreen;
use App\Telegram\Screens\HomeScreen;
use App\Telegram\Screens\ProfileScreen;
use App\Telegram\Screens\RegPendingScreen;
use App\Telegram\Screens\RegProfileScreen;
use App\Telegram\Screens\RegRoleScreen;
use App\Telegram\Screens\TeacherApplicationScreen;
use App\Telegram\Screens\UpScreen;
use App\Telegram\Screens\ReqScreen;
use App\Telegram\Screens\SrchScreen;
use App\Telegram\Screens\StuScreen;
use App\Telegram\Screens\StulScreen;
use App\Telegram\Screens\StusScreen;
use App\Telegram\Screens\TchScreen;
use App\Telegram\Screens\TchsScreen;

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
        'cats' => CatsScreen::class,
        'cat' => CatScreen::class,
        'catnew' => CatNewScreen::class,
        'up' => UpScreen::class,
        'mats' => MatsScreen::class,
        'mat' => MatScreen::class,
        'regrole' => RegRoleScreen::class,
        'regprofile' => RegProfileScreen::class,
        'regpending' => RegPendingScreen::class,
        'tapp' => TeacherApplicationScreen::class,
        'tchs' => TchsScreen::class,
        'tch' => TchScreen::class,
        'stus' => StusScreen::class,
        'stul' => StulScreen::class,
        'stu' => StuScreen::class,
        'req' => ReqScreen::class,
        'srch' => SrchScreen::class,
        'dls' => DlsScreen::class,
    ];

    public static function make(string $key): ?Screen
    {
        $class = self::MAP[$key] ?? null;

        return $class ? app($class) : null;
    }
}
