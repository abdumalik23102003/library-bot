<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use SergiX44\Nutgram\Nutgram;

class HomeScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        return Page::make(__('bot.home.text'), [
            [[__('bot.home.btn_help'), 'home:help']],
            [[__('bot.home.btn_echo'), 'home:echo']],
        ]);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        match ($action) {
            'help' => Navigator::go($bot, 'help'),
            'echo' => $this->openEcho($bot),
            default => Navigator::refresh($bot),
        };
    }

    private function openEcho(Nutgram $bot): void
    {
        Navigator::go($bot, 'echo');
        Navigator::awaitInput($bot, 'text');
    }
}
