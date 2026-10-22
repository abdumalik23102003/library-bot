<?php

namespace App\Telegram\Screens;

use App\Models\Homework;
use App\Models\HomeworkAssignment;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * "📝 Uy ishi" bo'limi. Ustoz: o'z uy ishlari + yangisini berish. O'quvchi: unga berilgan uy ishlari.
 * Callback: hw:n (yangi), hw:v:{id} (ustoz kartochkasi), hw:s:{topshiriqId} (o'quvchi), hw:p:{sahifa}, hw:open:{topshiriqId} (xabardagi [Topshirish]).
 */
class HwScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();
        $page = (int) ($params['page'] ?? 1);

        if ($actor->isTeacher()) {
            return $this->teacherPage($actor->id, $page);
        }

        if ($actor->isStudent()) {
            return $this->studentPage($actor->id, $page);
        }

        return Page::make(__('hw.err.forbidden'), back: true);
    }

    private function teacherPage(int $teacherId, int $page): Page
    {
        $query = Homework::query()
            ->where('teacher_id', $teacherId)
            ->visible()
            ->withCount(['assignments', 'assignments as submitted_count' => fn ($q) => $q->where('status', '!=', HomeworkAssignment::PENDING)])
            ->latest('id');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        $rows = [[[__('hw.btn.new'), 'hw:n']]];

        foreach ($items as $hw) {
            $rows[] = [[
                '📝 ' . Str::limit($hw->title, 26) . " · 📥 {$hw->submitted_count}/{$hw->assignments_count}" . ($hw->closed ? ' 🔒' : ''),
                "hw:v:{$hw->id}",
            ]];
        }

        if ($nav = Pager::navRow('hw:p', $page, $pages)) {
            $rows[] = $nav;
        }

        $text = $total === 0 ? __('hw.hub.teacher_empty') : __('hw.hub.teacher', ['total' => $total, 'page' => $page, 'pages' => $pages]);

        return Page::make($text, $rows, back: true);
    }

    private function studentPage(int $studentId, int $page): Page
    {
        $query = HomeworkAssignment::query()->where('student_id', $studentId)->whereHas('homework', fn ($q) => $q->visible())->with('homework')->latest('id');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        if ($total === 0) {
            return Page::make(__('hw.hub.student_empty'), back: true);
        }

        $rows = [];

        foreach ($items as $a) {
            $rows[] = [["{$a->icon()} " . ($a->homework->closed ? '🔒 ' : '') . Str::limit($a->homework->title, 34), "hw:s:{$a->id}"]];
        }

        if ($nav = Pager::navRow('hw:p', $page, $pages)) {
            $rows[] = $nav;
        }

        return Page::make(__('hw.hub.student', ['total' => $total, 'page' => $page, 'pages' => $pages]), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);

        if ($action === 'open') {
            Navigator::start($bot, 'hws', ['a' => $id], ['home', 'hw']);

            return;
        }

        if ((Navigator::current($bot)['screen'] ?? '') !== 'hw') {
            Navigator::refresh($bot);

            return;
        }

        match (true) {
            $action === 'p' => Navigator::replace($bot, 'hw', ['page' => max(1, $id)]),
            $action === 'n' && $actor->isTeacher() => $this->startNew($bot, $actor),
            $action === 'v' && $actor->isTeacher() && Homework::query()->where('teacher_id', $actor->id)->whereKey($id)->exists() => Navigator::go($bot, 'hwk', ['id' => $id]),
            $action === 's' && $actor->isStudent() && HomeworkAssignment::query()->where('student_id', $actor->id)->whereKey($id)->exists() => Navigator::go($bot, 'hws', ['a' => $id]),
            default => Navigator::refresh($bot),
        };
    }

    private function startNew(Nutgram $bot, $teacher): void
    {
        HwnScreen::forget($teacher);
        Navigator::go($bot, 'hwn');
    }
}
