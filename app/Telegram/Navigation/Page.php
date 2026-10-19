<?php

namespace App\Telegram\Navigation;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Ekranning tayyor ko'rinishi: HTML matn + (ixtiyoriy) inline klaviatura.
 */
final class Page
{
    public function __construct(
        public readonly string $text,
        public readonly ?InlineKeyboardMarkup $keyboard = null,
    ) {
    }

    /**
     * @param  array<int, array<int, array{0: string, 1: string}>>  $rows  [[ [matn, callback_data], ... ], ...]
     */
    public static function make(string $text, array $rows = [], bool $back = false): self
    {
        $keyboard = ($rows === [] && ! $back) ? null : Kb::inline($rows, $back);

        return new self($text, $keyboard);
    }
}
