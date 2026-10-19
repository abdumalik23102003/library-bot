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

        // Keyingi bosqichlarda rolga xos tugmalar shu yerga qo'shiladi.
        return Page::make($text, [
            [[__('bot.home.btn_profile'), 'home:profile']],
            [[__('bot.home.btn_help'), 'home:help']],
        ]);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        match ($action) {
            'profile' => Navigator::go($bot, 'profile'),
            'help' => Navigator::go($bot, 'help'),
            default => Navigator::refresh($bot),
        };
    }
}
