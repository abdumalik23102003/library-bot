<?php

namespace App\Telegram\Screens;

use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\LinkNotifier;
use App\Telegram\Support\UserAdmin;
use SergiX44\Nutgram\Nutgram;

/**
 * Foydalanuvchi kartochkasi va boshqaruv tugmalari (usr:{amal}:{id}).
 * Amallar: block, unblock, mod, unmod, del, delyes, mats, cancel. Holatlar params: ask (reason), confirm (delete), flash.
 */
class UsrScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();
        $target = User::find((int) ($params['id'] ?? 0));

        if ($target === null || ! UserAdmin::can($actor, $target)) {
            return Page::make(__('adm.err.not_found'), back: true);
        }

        $id = $target->id;
        $lines = [];

        if (! empty($params['flash'])) {
            $lines[] = __("adm.flash.{$params['flash']}");
            $lines[] = '';
        }

        $lines[] = $this->card($target);

        if (($params['ask'] ?? null) === 'reason') {
            $lines[] = '';
            $lines[] = __('adm.usr.ask_reason');

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), "usr:cancel:{$id}"]]]);
        }

        if (! empty($params['confirm'])) {
            return Page::make(__('adm.usr.confirm_delete', ['name' => e($target->displayName())]), [[
                [__('adm.usr.btn_delete_yes'), "usr:delyes:{$id}"],
                [__('adm.usr.btn_delete_no'), "usr:cancel:{$id}"],
            ]]);
        }

        $rows = [];

        $rows[] = [$target->isBlocked()
            ? [__('adm.usr.btn_unblock'), "usr:unblock:{$id}"]
            : [__('adm.usr.btn_block'), "usr:block:{$id}"]];

        if ($actor->isAdmin()) {
            if ($target->isModerator()) {
                $rows[] = [[__('adm.usr.btn_unmod'), "usr:unmod:{$id}"]];
            } elseif ($target->isActive() && $target->registered_at !== null) {
                $rows[] = [[__('adm.usr.btn_mod'), "usr:mod:{$id}"]];
            }
        }

        if ($target->isTeacher()) {
            $rows[] = [[__('adm.usr.btn_mats'), "usr:mats:{$id}"]];
        }

        $rows[] = [[__('adm.usr.btn_delete'), "usr:del:{$id}"]];

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);
        $target = User::find($id);

        if ($target === null || ! UserAdmin::can($actor, $target)) {
            $this->show($bot, $id);

            return;
        }

        match ($action) {
            'block' => $this->askReason($bot, $id),
            'unblock' => $this->done($bot, $id, UserAdmin::unblock($actor, $target), 'unblocked', $target, 'unblocked'),
            'mod' => $this->done($bot, $id, UserAdmin::makeModerator($actor, $target), 'mod_added', $target, 'mod_added'),
            'unmod' => $this->done($bot, $id, UserAdmin::removeModerator($actor, $target), 'mod_removed', $target, 'mod_removed'),
            'del' => $this->show($bot, $id, ['confirm' => true]),
            'delyes' => $this->delete($bot, $actor, $target),
            'mats' => Navigator::go($bot, 'amats', ['owner' => $id]),
            'cancel' => $this->show($bot, $id),
            default => $this->show($bot, $id),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);

        if ($field !== 'reason' || $target === null) {
            Navigator::refresh($bot);

            return;
        }

        $reason = trim($text);
        $reason = $reason === '-' ? '' : mb_substr($reason, 0, 200);

        $ok = UserAdmin::block($actor, $target, $reason);

        if ($ok) {
            UserAdmin::notify($bot, $target, 'blocked', $reason);
        }

        $this->show($bot, $id, ['flash' => $ok ? 'blocked' : 'invalid']);
    }

    private function askReason(Nutgram $bot, int $id): void
    {
        Navigator::replace($bot, 'usr', ['id' => $id, 'ask' => 'reason']);
        Navigator::awaitInput($bot, 'reason');
    }

    private function done(Nutgram $bot, int $id, bool $ok, string $flash, User $target, string $notify): void
    {
        if ($ok) {
            UserAdmin::notify($bot, $target->refresh(), $notify);
        }

        $this->show($bot, $id, ['flash' => $ok ? $flash : 'invalid']);
    }

    private function delete(Nutgram $bot, User $actor, User $target): void
    {
        UserAdmin::delete($actor, $target)
            ? Navigator::back($bot)
            : $this->show($bot, $target->id, ['flash' => 'invalid']);
    }

    private function card(User $target): string
    {
        $lines = [__('adm.usr.card', [
            'name' => e($target->displayName()),
            'role' => e($target->role->label()),
            'status' => e($target->status->label()),
            'tg' => LinkNotifier::tg($target),
        ])];

        if (filled($target->phone)) {
            $lines[] = __('adm.usr.phone', ['value' => e($target->phone)]);
        }

        if ($target->isTeacher() && filled($target->subject)) {
            $lines[] = __('adm.usr.subject', ['value' => e($target->subject)]);
        }

        if ($target->registered_at !== null) {
            $lines[] = __('adm.usr.registered', ['value' => $target->registered_at->format('d.m.Y')]);
        }

        if ($target->isBlocked() && filled($target->blocked_reason)) {
            $lines[] = __('adm.usr.reason', ['value' => e($target->blocked_reason)]);
        }

        $stats = UserAdmin::userStats($target);

        if ($target->isTeacher()) {
            $lines[] = '';
            $lines[] = __('adm.usr.stats_teacher', $stats);
        } elseif ($target->role->value === 'student') {
            $lines[] = '';
            $lines[] = __('adm.usr.stats_student', $stats);
        }

        return implode("\n", $lines);
    }

    private function show(Nutgram $bot, int $id, array $extra = []): void
    {
        $params = ['id' => $id] + $extra;

        (Navigator::current($bot)['screen'] ?? null) === 'usr'
            ? Navigator::replace($bot, 'usr', $params)
            : Navigator::go($bot, 'usr', $params);
    }
}
