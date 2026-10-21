<?php

namespace App\Telegram\Screens;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use App\Telegram\Support\UserAdmin;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Foydalanuvchilar ro'yxati: filtr (hammasi/ustoz/o'quvchi/moderator/bloklangan) + qidiruv.
 * Callback: usrs:f:{filtr}, usrs:p:{sahifa}:{filtr}, usrs:o:{id}, usrs:s (qidirish), usrs:x (tozalash), usrs:cancel.
 */
class UsrsScreen extends Screen
{
    private const ROLE_ICONS = ['admin' => '👑', 'moderator' => '🛡', 'teacher' => '🎓', 'student' => '🎒'];

    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();

        if (! $actor->isStaff()) {
            return Page::make(__('adm.err.forbidden'), back: true);
        }

        if (($params['ask'] ?? null) === 'q') {
            return Page::make(__('adm.usrs.ask_q'), [[[__('bot.cancel'), 'usrs:cancel']]]);
        }

        $filter = $this->filter($actor, (string) ($params['filter'] ?? 'all'));
        $q = trim((string) ($params['q'] ?? ''));

        $query = UserAdmin::visible($actor)->orderBy('full_name')->orderBy('id');

        match ($filter) {
            'teacher' => $query->ofRole(Role::Teacher),
            'student' => $query->ofRole(Role::Student),
            'mod' => $query->ofRole(Role::Moderator),
            'blocked' => $query->where('status', UserStatus::Blocked->value),
            default => null,
        };

        if ($q !== '') {
            UserAdmin::search($query, $q);
        }

        [$items, $page, $pages, $total] = Pager::slice($query, (int) ($params['page'] ?? 1));

        $rows = [$this->filterRow($actor, $filter)];
        $tools = [[__('adm.usrs.btn_search'), 'usrs:s']];

        if ($q !== '') {
            $tools[] = [__('adm.usrs.btn_clear'), 'usrs:x'];
        }

        $rows[] = $tools;

        if ($total === 0) {
            return Page::make(__('adm.usrs.empty'), $rows, back: true);
        }

        $lines = [
            $q !== ''
                ? __('adm.usrs.title_q', ['q' => e($q), 'total' => $total, 'page' => $page, 'pages' => $pages])
                : __('adm.usrs.title', ['total' => $total, 'page' => $page, 'pages' => $pages]),
            '',
        ];
        $buttons = [];

        /** @var User $user */
        foreach ($items as $i => $user) {
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;
            $mark = match ($user->status) {
                UserStatus::Blocked => ' 🚫',
                UserStatus::Pending => ' ⏳',
                default => '',
            };

            $lines[] = sprintf(
                '%d. %s %s — %s%s',
                $n,
                self::ROLE_ICONS[$user->role->value] ?? '',
                e(Str::limit($user->displayName(), 40)),
                $user->username ? '@' . e($user->username) : 'ID ' . $user->telegram_id,
                $mark,
            );
            $buttons[] = [(string) $n, "usrs:o:{$user->id}"];
        }

        $lines[] = '';
        $lines[] = __('adm.usrs.pick_hint');

        array_push($rows, ...array_chunk($buttons, 4));

        if ($nav = Pager::navRow('usrs:p', $page, $pages, ":{$filter}")) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $current = Navigator::current($bot);

        if (! $actor->isStaff() || ($current['screen'] ?? '') !== 'usrs') {
            Navigator::refresh($bot);

            return;
        }

        $params = $current['params'] ?? [];
        $filter = $this->filter($actor, (string) ($params['filter'] ?? 'all'));
        $keep = filled($params['q'] ?? null) ? ['q' => $params['q']] : [];

        match ($action) {
            'f' => Navigator::replace($bot, 'usrs', ['filter' => $this->filter($actor, (string) ($args[0] ?? ''))] + $keep),
            'p' => Navigator::replace($bot, 'usrs', ['page' => max(1, (int) ($args[0] ?? 1)), 'filter' => $this->filter($actor, (string) ($args[1] ?? $filter))] + $keep),
            'o' => $this->open($bot, $actor, (int) ($args[0] ?? 0)),
            's' => $this->ask($bot, $filter),
            'x' => Navigator::replace($bot, 'usrs', ['filter' => $filter]),
            'cancel' => Navigator::replace($bot, 'usrs', ['filter' => $filter] + $keep),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $filter = $this->filter(CurrentUser::get(), (string) ($params['filter'] ?? 'all'));

        if ($field !== 'q') {
            Navigator::refresh($bot);

            return;
        }

        $q = mb_substr(trim($text), 0, 60);

        Navigator::replace($bot, 'usrs', ['filter' => $filter] + ($q !== '' && $q !== '-' ? ['q' => $q] : []));
    }

    private function ask(Nutgram $bot, string $filter): void
    {
        Navigator::replace($bot, 'usrs', ['filter' => $filter, 'ask' => 'q']);
        Navigator::awaitInput($bot, 'q');
    }

    private function open(Nutgram $bot, User $actor, int $id): void
    {
        $exists = UserAdmin::visible($actor)->whereKey($id)->exists();

        $exists ? Navigator::go($bot, 'usr', ['id' => $id]) : Navigator::refresh($bot);
    }

    private function filter(User $actor, string $value): string
    {
        if ($value === 'mod' && ! $actor->isAdmin()) {
            return 'all';
        }

        return in_array($value, ['all', 'teacher', 'student', 'mod', 'blocked'], true) ? $value : 'all';
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function filterRow(User $actor, string $current): array
    {
        $keys = $actor->isAdmin()
            ? ['all', 'teacher', 'student', 'mod', 'blocked']
            : ['all', 'teacher', 'student', 'blocked'];

        return array_map(
            fn (string $key) => [($key === $current ? '• ' : '') . __("adm.usrs.filter.{$key}"), "usrs:f:{$key}"],
            $keys,
        );
    }
}
