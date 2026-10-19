<?php

namespace App\Telegram\Navigation;

use App\Telegram\Screens\EchoScreen;
use App\Telegram\Screens\HelpMoreScreen;
use App\Telegram\Screens\HelpScreen;
use App\Telegram\Screens\HomeScreen;

/**
 * Ekran kaliti -> sinf. Kalitlar faqat kichik lotin harflari (callback_data da ":" ishlatiladi).
 */
final class ScreenRegistry
{
    private const MAP = [
        'home' => HomeScreen::class,
        'help' => HelpScreen::class,
        'helpmore' => HelpMoreScreen::class,
        'echo' => EchoScreen::class,
    ];

    public static function make(string $key): ?Screen
    {
        $class = self::MAP[$key] ?? null;

        return $class ? app($class) : null;
    }
}
