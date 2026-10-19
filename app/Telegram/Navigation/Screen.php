<?php

namespace App\Telegram\Navigation;

use SergiX44\Nutgram\Nutgram;

/**
 * Har bir ekran: ko'rinish (render), tugma bosilishi (action) va matn kiritish (input).
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
}
