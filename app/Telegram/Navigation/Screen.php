<?php

namespace App\Telegram\Navigation;

use SergiX44\Nutgram\Nutgram;

/**
 * Har bir ekran: ko'rinish (render), tugma bosilishi (action), matn (input) va fayl (file) kiritish.
 */
abstract class Screen
{
    /** Ekran ko'rinishini qaytaradi. $params navigatsiya stekida saqlanadi. */
    abstract public function render(Nutgram $bot, array $params): Page;

    /**
     * Inline tugma bosilganda ("{ekran}:{amal}:{args}") chaqiriladi.
     *
     * @param  array<int, string>  $args
     */
    public function action(Nutgram $bot, string $action, array $args): void
    {
    }

    /** Navigator::awaitInput() dan keyin foydalanuvchi matn yozganda chaqiriladi. */
    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
    }

    /**
     * Matn kutilayotganda foydalanuvchi fayl yuborganda chaqiriladi (faqat field = "material" bo'lsa).
     *
     * @param  array{type: string, file_id: string, uid: ?string, name: ?string, mime: ?string, size: ?int, caption: ?string}  $file
     */
    public function file(Nutgram $bot, string $field, array $file, array $params): void
    {
    }

    /** Ekran chizilgandan keyin chaqiriladi (masalan, matn kutishni yoqish uchun). */
    public function mounted(Nutgram $bot, array $params): void
    {
    }
}
