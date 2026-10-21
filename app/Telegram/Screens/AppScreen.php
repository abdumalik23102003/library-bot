<?php

namespace App\Telegram\Screens;

use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\StaffNotifier;
use App\Telegram\Support\UserAdmin;
use SergiX44\Nutgram\Nutgram;

/** Bitta ustoz arizasi kartochkasi (app:approve:{id}, app:reject:{id}). Qaror ustozga xabar bilan boradi. */
class AppScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();
        $teacher = User::find((int) ($params['id'] ?? 0));

        if (! $actor->isStaff() || $teacher === null) {
            return Page::make(__('adm.app.not_found'), back: true);
        }

        $lines = [];

        if (! empty($params['flash'])) {
            $lines[] = __("adm.app.{$params['flash']}");
            $lines[] = '';
        }

        $lines[] = StaffNotifier::applicationCard($teacher);

        $open = UserAdmin::pendingApplications()->whereKey($teacher->id)->exists();
        $rows = $open ? [[
            [__('adm.app.btn_approve'), "app:approve:{$teacher->id}"],
            [__('adm.app.btn_reject'), "app:reject:{$teacher->id}"],
        ]] : [];

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);
        $teacher = User::find($id);

        if (! in_array($action, ['approve', 'reject'], true) || $teacher === null) {
            Navigator::refresh($bot);

            return;
        }

        $approve = $action === 'approve';
        $done = UserAdmin::decideApplication($actor, $teacher, $approve);

        if ($done) {
            StaffNotifier::notifyDecision($bot, $teacher->refresh(), $approve);
        }

        Navigator::replace($bot, 'app', [
            'id' => $id,
            'flash' => $done ? ($approve ? 'approved' : 'rejected') : 'already',
        ]);
    }
}
