<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use SergiX44\Nutgram\Nutgram;

class HomeScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();

        $text = __('bot.home.text', [
            'name' => e($user->displayName()),
            'role' => e($user->role->label()),
        ]);

        $rows = [];

        if ($user->isTeacher()) {
            $rows[] = [[__('bot.home.btn_cats'), 'home:cats']];
        }

        $rows[] = [[__('bot.home.btn_profile'), 'home:profile']];
        $rows[] = [[__('bot.home.btn_help'), 'home:help']];

        return Page::make($text, $rows);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        match (true) {
            $action === 'profile' => Navigator::go($bot, 'profile'),
            $action === 'help' => Navigator::go($bot, 'help'),
            $action === 'cats' && CurrentUser::get()->isTeacher() => Navigator::go($bot, 'cats'),
            default => Navigator::refresh($bot),
        };
    }
}
