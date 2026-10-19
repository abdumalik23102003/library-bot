<?php

namespace App\Telegram\Keyboards;

use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

final class MainMenu
{
    public static function keyboard(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true)->addRow(
            KeyboardButton::make(__('bot.menu.home')),
            KeyboardButton::make(__('bot.menu.help')),
        );
    }

    /**
     * Tugma matnidan ekranni topadi. "parents" - "Orqaga" qaytadigan ekranlar.
     *
     * @return array{screen: string, parents: array<int, string>}|null
     */
    public static function resolve(string $text): ?array
    {
        return match ($text) {
            __('bot.menu.home') => ['screen' => 'home', 'parents' => []],
            __('bot.menu.help') => ['screen' => 'help', 'parents' => ['home']],
            default => null,
        };
    }
}
