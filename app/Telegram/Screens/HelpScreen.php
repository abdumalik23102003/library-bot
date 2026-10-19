<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use SergiX44\Nutgram\Nutgram;

class HelpScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $role = CurrentUser::get()->role->value;

        return Page::make(__("bot.help.{$role}"), back: true);
    }
}
