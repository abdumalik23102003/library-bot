<?php

namespace App\Telegram\Keyboards;

use App\Models\User;
use App\Telegram\Support\CurrentUser;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

final class MainMenu
{
    /**
     * Menyu bandlari: kalit => [matn kaliti, ekran, "Orqaga" qaytadigan ekranlar].
     * Yangi band: shu yerga qo'shing va BY_ROLE dagi kerakli rollarga kalitini yozing.
     */
    private const ITEMS = [
        'home' => ['bot.menu.home', 'home', []],
        'upload' => ['mat.menu', 'up', ['home']],
        'cats' => ['bot.menu.cats', 'cats', ['home']],
        'mats' => ['mats.menu', 'mats', ['home']],
        'profile' => ['bot.menu.profile', 'profile', ['home']],
        'help' => ['bot.menu.help', 'help', ['home']],
    ];

    /** Rol -> menyu bandlari (tartib bilan). */
    private const BY_ROLE = [
        'admin' => ['home', 'profile', 'help'],
        'moderator' => ['home', 'profile', 'help'],
        'teacher' => ['home', 'upload', 'mats', 'cats', 'profile', 'help'],
        'student' => ['home', 'profile', 'help'],
    ];

    /** @return array<int, string> */
    private static function keysFor(User $user): array
    {
        return self::BY_ROLE[$user->role->value] ?? self::BY_ROLE['student'];
    }

    public static function keyboard(?User $user = null): ReplyKeyboardMarkup
    {
        $user ??= CurrentUser::get();
        $markup = ReplyKeyboardMarkup::make(resize_keyboard: true);

        foreach (array_chunk(self::keysFor($user), 2) as $chunk) {
            $markup->addRow(...array_map(
                fn (string $key) => KeyboardButton::make(__(self::ITEMS[$key][0])),
                $chunk,
            ));
        }

        return $markup;
    }

    /**
     * Tugma matnidan ekranni topadi. $user berilsa, faqat uning roliga ruxsat etilgan bandlar qaraladi.
     *
     * @return array{screen: string, parents: array<int, string>}|null
     */
    public static function resolve(string $text, ?User $user = null): ?array
    {
        $allowed = $user ? self::keysFor($user) : array_keys(self::ITEMS);

        foreach ($allowed as $key) {
            [$label, $screen, $parents] = self::ITEMS[$key];

            if ($text === __($label)) {
                return ['screen' => $screen, 'parents' => $parents];
            }
        }

        return null;
    }
}
