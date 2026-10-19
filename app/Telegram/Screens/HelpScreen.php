<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use SergiX44\Nutgram\Nutgram;

class HelpScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        return Page::make(__('bot.help.text'), [
            [[__('bot.help.btn_more'), 'help:more']],
        ], back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        match ($action) {
            'more' => Navigator::go($bot, 'helpmore'),
            default => Navigator::refresh($bot),
        };
    }
}
