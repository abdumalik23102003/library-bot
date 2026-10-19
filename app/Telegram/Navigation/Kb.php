<?php

namespace App\Telegram\Navigation;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class Kb
{
    /**
     * callback_data formati: "{ekran}:{amal}:{arg1}:{arg2}" (64 baytdan oshmasin), "b" = orqaga.
     *
     * @param  array<int, array<int, array{0: string, 1: string}>>  $rows
     */
    public static function inline(array $rows, bool $back = false): InlineKeyboardMarkup
    {
        $markup = InlineKeyboardMarkup::make();

        foreach ($rows as $row) {
            $markup->addRow(...array_map(
                fn (array $button) => InlineKeyboardButton::make($button[0], callback_data: $button[1]),
                $row,
            ));
        }

        if ($back) {
            $markup->addRow(InlineKeyboardButton::make(__('bot.back'), callback_data: 'b'));
        }

        return $markup;
    }
}
