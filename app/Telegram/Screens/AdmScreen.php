<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\UserAdmin;
use SergiX44\Nutgram\Nutgram;

/** Admin/moderator boshqaruv paneli: statistika va bo'limlar (adm:{apps|usrs|mats|mods}). */
class AdmScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();

        if (! $actor->isStaff()) {
            return Page::make(__('adm.err.forbidden'), back: true);
        }

        $s = UserAdmin::stats();

        $rows = [
            [[__('adm.panel.btn_apps', ['n' => $s['pending']]), 'adm:apps']],
            [[__('adm.panel.btn_users'), 'adm:usrs'], [__('adm.panel.btn_mats'), 'adm:mats']],
        ];

        $rows[] = [[__('dcat.btn_panel'), 'adm:dcats']];
        $rows[] = [[__('msg.bc.btn_panel'), 'adm:bc']];

        if ($actor->isAdmin()) {
            $rows[] = [[__('adm.panel.btn_mods'), 'adm:mods']];
            $rows[] = [[__('sys.btn_audit'), 'adm:aud'], [__('sys.btn_settings'), 'adm:set']];
        }

        return Page::make(__('adm.panel.title', $s), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();

        if (! $actor->isStaff()) {
            Navigator::refresh($bot);

            return;
        }

        match (true) {
            $action === 'apps' => Navigator::go($bot, 'apps'),
            $action === 'usrs' => Navigator::go($bot, 'usrs', ['filter' => 'all']),
            $action === 'mats' => Navigator::go($bot, 'amats'),
            $action === 'mods' && $actor->isAdmin() => Navigator::go($bot, 'usrs', ['filter' => 'mod']),
            $action === 'dcats' => Navigator::go($bot, 'dcats'),
            $action === 'aud' && $actor->isAdmin() => Navigator::go($bot, 'aud'),
            $action === 'set' && $actor->isAdmin() => Navigator::go($bot, 'set'),
            $action === 'bc' => Navigator::go($bot, 'bc'),
            default => Navigator::refresh($bot),
        };
    }
}
