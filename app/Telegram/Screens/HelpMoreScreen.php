<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use SergiX44\Nutgram\Nutgram;

class HelpMoreScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        return Page::make(__('bot.help_more.text'), back: true);
    }
}
