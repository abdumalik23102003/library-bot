<?php

namespace App\Telegram\Screens;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use SergiX44\Nutgram\Nutgram;

class RegRoleScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        return Page::make(__('reg.role.text'), [[
            [__('reg.role.teacher'), 'regrole:pick:teacher'],
            [__('reg.role.student'), 'regrole:pick:student'],
        ]]);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        if ($action !== 'pick') {
            Navigator::refresh($bot);

            return;
        }

        $role = match ($args[0] ?? '') {
            'teacher' => Role::Teacher,
            'student' => Role::Student,
            default => null,
        };

        if ($role === null) {
            Navigator::refresh($bot);

            return;
        }

        $user = CurrentUser::get();
        $user->role = $role;
        $user->status = UserStatus::Pending;
        $user->save();

        Navigator::go($bot, 'regprofile');
    }
}
