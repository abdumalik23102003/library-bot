<?php

namespace App\Telegram\Screens;

use App\Enums\RequestStatus;
use App\Models\ProtectedAccess;
use App\Models\TeacherStudent;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use SergiX44\Nutgram\Nutgram;

/**
 * Ustozning o'quvchilar ro'yxati. kind: req (qo'shilish so'rovlari), list (o'quvchilar), prot (himoyalangan kirish so'rovlari).
 */
class StulScreen extends Screen
{
    private const KINDS = ['req', 'list', 'prot'];

    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = CurrentUser::get();

        if (! $teacher->isTeacher()) {
            return Page::make(__('links.err.forbidden'), back: true);
        }

        $kind = $this->kind($params['kind'] ?? '');

        $query = match ($kind) {
            'prot' => ProtectedAccess::query()->where('status', RequestStatus::Pending->value),
            'req' => TeacherStudent::query()->where('status', RequestStatus::Pending->value),
            default => TeacherStudent::query()->where('status', RequestStatus::Approved->value),
        };

        $query->where('teacher_id', $teacher->id)->whereHas('student')->with('student')->latest('id');

        [$items, $page, $pages, $total] = Pager::slice($query, (int) ($params['page'] ?? 1));

        if ($total === 0) {
            return Page::make(__("links.stul.empty_{$kind}"), back: true);
        }

        $lines = [__("links.stul.title_{$kind}", ['total' => $total, 'page' => $page, 'pages' => $pages]), ''];
        $buttons = [];

        foreach ($items as $i => $row) {
            $student = $row->student;
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;

            $lines[] = sprintf('%d. %s%s', $n, e($student->displayName()), $student->username ? ' · @' . e($student->username) : '');
            $buttons[] = [(string) $n, "stul:o:{$student->id}"];
        }

        $rows = array_chunk($buttons, 4);

        if ($nav = Pager::navRow('stul:p', $page, $pages, ":{$kind}")) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $teacher = CurrentUser::get();

        if (! $teacher->isTeacher()) {
            Navigator::refresh($bot);

            return;
        }

        if ($action === 'p') {
            Navigator::replace($bot, 'stul', [
                'page' => max(1, (int) ($args[0] ?? 1)),
                'kind' => $this->kind($args[1] ?? ''),
            ]);

            return;
        }

        if ($action === 'o') {
            $id = (int) ($args[0] ?? 0);
            $has = TeacherStudent::query()->where('teacher_id', $teacher->id)->where('student_id', $id)->exists();

            $has ? Navigator::go($bot, 'stu', ['id' => $id]) : Navigator::refresh($bot);

            return;
        }

        Navigator::refresh($bot);
    }

    private function kind(string $value): string
    {
        return in_array($value, self::KINDS, true) ? $value : 'list';
    }
}
