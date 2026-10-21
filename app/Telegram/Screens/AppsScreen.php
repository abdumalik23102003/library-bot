<?php

namespace App\Telegram\Screens;

use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use App\Telegram\Support\UserAdmin;
use SergiX44\Nutgram\Nutgram;

/** Kutilayotgan ustoz arizalari ro'yxati (apps:p:{sahifa}, apps:o:{id}). */
class AppsScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        if (! CurrentUser::get()->isStaff()) {
            return Page::make(__('adm.err.forbidden'), back: true);
        }

        [$items, $page, $pages, $total] = Pager::slice(
            UserAdmin::pendingApplications()->orderBy('id'),
            (int) ($params['page'] ?? 1),
        );

        if ($total === 0) {
            return Page::make(__('adm.apps.empty'), back: true);
        }

        $lines = [__('adm.apps.title', ['total' => $total, 'page' => $page, 'pages' => $pages]), ''];
        $buttons = [];

        /** @var User $teacher */
        foreach ($items as $i => $teacher) {
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;

            $lines[] = sprintf('%d. %s — %s', $n, e($teacher->displayName()), e($teacher->subject ?: '—'));
            $buttons[] = [(string) $n, "apps:o:{$teacher->id}"];
        }

        $lines[] = '';
        $lines[] = __('adm.apps.pick_hint');

        $rows = array_chunk($buttons, 4);

        if ($nav = Pager::navRow('apps:p', $page, $pages)) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        if (! CurrentUser::get()->isStaff() || (Navigator::current($bot)['screen'] ?? '') !== 'apps') {
            Navigator::refresh($bot);

            return;
        }

        $id = (int) ($args[0] ?? 0);

        match ($action) {
            'p' => Navigator::replace($bot, 'apps', ['page' => max(1, $id)]),
            'o' => UserAdmin::pendingApplications()->whereKey($id)->exists()
                ? Navigator::go($bot, 'app', ['id' => $id])
                : Navigator::refresh($bot),
            default => Navigator::refresh($bot),
        };
    }
}
