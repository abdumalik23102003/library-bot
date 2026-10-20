<?php

namespace App\Telegram\Screens;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\TeacherStudent;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use SergiX44\Nutgram\Nutgram;

/** O'quvchi uchun ustozlar ro'yxati (Barchasi / Mening ustozlarim). */
class TchsScreen extends Screen
{
    private const ICONS = ['approved' => '✅', 'pending' => '⏳'];

    public function render(Nutgram $bot, array $params): Page
    {
        $student = CurrentUser::get();

        if (! $student->isStudent()) {
            return Page::make(__('links.err.forbidden'), back: true);
        }

        $filter = ($params['filter'] ?? 'all') === 'mine' ? 'mine' : 'all';

        $query = User::query()
            ->ofRole(Role::Teacher)
            ->active()
            ->whereNotNull('registered_at')
            ->orderBy('full_name');

        if ($filter === 'mine') {
            $query->whereIn('id', TeacherStudent::query()
                ->select('teacher_id')
                ->where('student_id', $student->id)
                ->where('status', RequestStatus::Approved->value));
        }

        [$items, $page, $pages, $total] = Pager::slice($query, (int) ($params['page'] ?? 1));

        $toggle = [
            [($filter === 'all' ? '• ' : '') . __('links.tchs.btn_all'), 'tchs:f:all'],
            [($filter === 'mine' ? '• ' : '') . __('links.tchs.btn_mine'), 'tchs:f:mine'],
        ];

        if ($total === 0) {
            return Page::make(__($filter === 'mine' ? 'links.tchs.empty_mine' : 'links.tchs.empty_all'), [$toggle], back: true);
        }

        $links = TeacherStudent::query()
            ->where('student_id', $student->id)
            ->whereIn('teacher_id', $items->pluck('id'))
            ->get()
            ->keyBy('teacher_id');

        $lines = [
            __($filter === 'mine' ? 'links.tchs.title_mine' : 'links.tchs.title', ['total' => $total, 'page' => $page, 'pages' => $pages]),
            '',
        ];
        $buttons = [];

        foreach ($items as $i => $teacher) {
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;
            $icon = self::ICONS[$links->get($teacher->id)?->status->value ?? ''] ?? '';

            $lines[] = sprintf('%d. %s — %s %s', $n, e($teacher->displayName()), e($teacher->subject ?? '—'), $icon);
            $buttons[] = [(string) $n, "tchs:o:{$teacher->id}"];
        }

        $rows = [$toggle, ...array_chunk($buttons, 4)];

        if ($nav = Pager::navRow('tchs:p', $page, $pages, ":{$filter}")) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        match ($action) {
            'f' => Navigator::replace($bot, 'tchs', ['filter' => $this->filter($args[0] ?? '')]),
            'p' => Navigator::replace($bot, 'tchs', ['page' => max(1, (int) ($args[0] ?? 1)), 'filter' => $this->filter($args[1] ?? '')]),
            'o' => $this->open($bot, (int) ($args[0] ?? 0)),
            default => Navigator::refresh($bot),
        };
    }

    private function filter(string $value): string
    {
        return $value === 'mine' ? 'mine' : 'all';
    }

    private function open(Nutgram $bot, int $id): void
    {
        $exists = User::query()->ofRole(Role::Teacher)->active()->whereNotNull('registered_at')->whereKey($id)->exists();

        $exists ? Navigator::go($bot, 'tch', ['id' => $id]) : Navigator::refresh($bot);
    }
}
