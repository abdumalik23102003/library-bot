<?php

namespace App\Telegram\Screens;

use App\Jobs\CheckHomework;
use App\Models\Homework;
use App\Models\HomeworkAssignment;
use App\Models\User;
use App\Services\HomeworkGrader;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Messaging;
use App\Telegram\Support\Pager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Ustoz uchun uy ishi kartochkasi: holat, o'quvchilar ro'yxati, "🤖 AI tekshir", hisobot, yopish/o'chirish.
 * Callback: hwk:l (ro'yxat), hwk:p:{sahifa}, hwk:st:{topshiriqId}, hwk:c (kartochka), hwk:chk:{new|all}, hwk:rep, hwk:fl:{topshiriqId},
 *           hwk:tog (yopish/ochish), hwk:del, hwk:delyes.
 * params: id, view (card|list|st|del), page, a, flash.
 */
class HwkScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $hw = $this->homework(CurrentUser::get(), (int) ($params['id'] ?? 0));

        if ($hw === null) {
            return Page::make(__('hw.err.not_found'), back: true);
        }

        return match ($params['view'] ?? 'card') {
            'list' => $this->listPage($hw, (int) ($params['page'] ?? 1)),
            'st' => $this->detailPage($hw, (int) ($params['a'] ?? 0)),
            'del' => Page::make(__('hw.card.delete_confirm', ['title' => e($hw->title)]), [[
                [__('hw.btn.delete_yes'), 'hwk:delyes'],
                [__('bot.cancel'), 'hwk:c'],
            ]]),
            default => $this->cardPage($hw, (string) ($params['flash'] ?? '')),
        };
    }

    private function cardPage(Homework $hw, string $flash): Page
    {
        $count = fn (array $statuses) => $hw->assignments()->whereIn('status', $statuses)->count();
        $total = $hw->assignments()->count();
        $pending = $count([HomeworkAssignment::PENDING]);
        $checked = $count([HomeworkAssignment::CHECKED]);
        $attention = $hw->assignments()->where(fn ($q) => $q->whereIn('status', [HomeworkAssignment::UNREADABLE, HomeworkAssignment::ERROR])->orWhere('needs_teacher', true))->count();
        $new = $count([HomeworkAssignment::SUBMITTED, HomeworkAssignment::ERROR]);

        $lines = [
            __('hw.card.title', ['title' => e($hw->title)]),
            __('hw.card.task', ['name' => e($this->sourceName((array) $hw->task))]),
        ];

        if (! empty($hw->key)) {
            $lines[] = __('hw.card.key', ['name' => e($this->sourceName((array) $hw->key))]);
        }

        if (filled($hw->note)) {
            $lines[] = __('hw.card.note', ['text' => e(Str::limit($hw->note, 200))]);
        }

        $lines[] = '';
        $lines[] = __('hw.card.stats', ['total' => $total, 'submitted' => $total - $pending, 'checked' => $checked, 'attention' => $attention, 'new' => $new]);

        if ($hw->closed) {
            $lines[] = __('hw.card.closed');
        }

        if ($hw->checked_at !== null) {
            $lines[] = __('hw.card.last_check', ['at' => $hw->checked_at->format('d.m H:i')]);
        }

        if ($flash !== '') {
            $lines[] = '';
            $lines[] = __("hw.flash.{$flash}", ['n' => $new]);
        }

        $rows = [
            [[__('hw.btn.list'), 'hwk:l']],
            [[__('hw.btn.check', ['n' => $new]), 'hwk:chk:new']],
            [[__('hw.btn.check_all'), 'hwk:chk:all']],
        ];

        if (filled($hw->report)) {
            $rows[] = [[__('hw.btn.report'), 'hwk:rep']];
        }

        $rows[] = [[__($hw->closed ? 'hw.btn.open' : 'hw.btn.close'), 'hwk:tog'], [__('hw.btn.delete'), 'hwk:del']];

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    private function listPage(Homework $hw, int $page): Page
    {
        $query = HomeworkAssignment::query()->where('homework_id', $hw->id)->with('student')->join('users', 'users.id', '=', 'homework_assignments.student_id')
            ->orderBy('users.full_name')->orderBy('homework_assignments.id')->select('homework_assignments.*');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        $rows = [];

        foreach ($items as $a) {
            $score = $a->status === HomeworkAssignment::CHECKED ? " · {$a->score}/{$hw->max_score}" : '';
            $rows[] = [["{$a->icon()} " . Str::limit($a->student?->displayName() ?? '—', 28) . $score, "hwk:st:{$a->id}"]];
        }

        if ($nav = Pager::navRow('hwk:p', $page, $pages)) {
            $rows[] = $nav;
        }

        $rows[] = [[__('bot.back'), 'hwk:c']];

        return Page::make(__('hw.list.title', ['title' => e($hw->title), 'total' => $total, 'page' => $page, 'pages' => $pages]), $rows);
    }

    private function detailPage(Homework $hw, int $assignmentId): Page
    {
        $a = $hw->assignments()->with(['student', 'files'])->find($assignmentId);

        if ($a === null) {
            return Page::make(__('hw.err.not_found'), [[[__('bot.back'), 'hwk:l']]]);
        }

        $lines = [
            __('hw.detail.title', ['name' => e($a->student?->displayName() ?? '—'), 'icon' => $a->icon(), 'status' => __("hw.status.{$a->status}")]),
            __('hw.detail.files', ['n' => $a->files->count()]),
        ];

        if ($a->status === HomeworkAssignment::CHECKED) {
            $lines[] = __('hw.detail.score', ['score' => $a->score, 'max' => $hw->max_score]);

            if (filled($a->feedback)) {
                $lines[] = '';
                $lines[] = __('hw.detail.feedback', ['text' => e($a->feedback)]);
            }

            if (! empty($a->mistakes)) {
                $lines[] = '';
                $lines[] = __('hw.detail.mistakes');

                foreach ($a->mistakes as $mistake) {
                    $lines[] = '• ' . e($mistake);
                }
            }
        }

        if ($a->needs_teacher || in_array($a->status, [HomeworkAssignment::UNREADABLE, HomeworkAssignment::ERROR], true)) {
            $lines[] = '';
            $lines[] = __('hw.detail.attention', ['reason' => e($a->reason ?: __('hw.report.no_reason'))]);
        }

        $rows = [];

        if ($a->files->isNotEmpty()) {
            $rows[] = [[__('hw.btn.get_files'), "hwk:fl:{$a->id}"]];
        }

        $rows[] = [[__('bot.back'), 'hwk:l']];

        return Page::make(implode("\n", $lines), $rows);
    }

    // ---- Tugmalar -------------------------------------------------------------------------------

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        // Hisobot kartochkalaridagi tugmalar istalgan ekrandan ishlaydi.
        if (in_array($action, ['card', 'fls'], true)) {
            $this->fromCard($bot, $actor, $action, (int) ($args[0] ?? 0));

            return;
        }

        $top = Navigator::current($bot);
        $id = (int) ($top['params']['id'] ?? 0);
        $hw = ($top['screen'] ?? '') === 'hwk' ? $this->homework($actor, $id) : null;

        if ($hw === null) {
            Navigator::refresh($bot);

            return;
        }

        $show = fn (array $extra = []) => Navigator::replace($bot, 'hwk', ['id' => $id] + $extra);

        match ($action) {
            'l' => $show(['view' => 'list', 'page' => 1]),
            'p' => $show(['view' => 'list', 'page' => max(1, (int) ($args[0] ?? 1))]),
            'st' => $show(['view' => 'st', 'a' => (int) ($args[0] ?? 0)]),
            'c' => $show(),
            'chk' => $show(['flash' => $this->check($hw, ($args[0] ?? '') === 'all')]),
            'rep' => $this->sendReport($bot, $actor, $hw, $show),
            'fl' => $this->sendFiles($bot, $actor, $hw, (int) ($args[0] ?? 0), $show),
            'tog' => $this->toggle($hw, $show),
            'del' => $show(['view' => 'del']),
            'delyes' => $this->delete($bot, $hw),
            default => Navigator::refresh($bot),
        };
    }

    /** @return string  flash kaliti */
    private function check(Homework $hw, bool $all): string
    {
        if (! app(HomeworkGrader::class)->enabled()) {
            return 'no_key';
        }

        $statuses = $all
            ? [HomeworkAssignment::SUBMITTED, HomeworkAssignment::CHECKED, HomeworkAssignment::UNREADABLE, HomeworkAssignment::ERROR]
            : [HomeworkAssignment::SUBMITTED, HomeworkAssignment::ERROR];

        if (! $hw->assignments()->whereIn('status', $statuses)->exists()) {
            return 'nothing';
        }

        if (Cache::has("hw:check:{$hw->id}") || ! Cache::add("hw:queue:{$hw->id}", true, now()->addSeconds(30))) {
            return 'already';
        }

        CheckHomework::dispatch($hw->id, $all);

        return 'queued';
    }

    private function sendReport(Nutgram $bot, User $actor, Homework $hw, callable $show): void
    {
        if (blank($hw->report)) {
            $show(['flash' => 'no_report']);

            return;
        }

        foreach (explode("\n\x1E", (string) $hw->report) as $chunk) {
            try {
                $bot->sendMessage(text: $chunk, chat_id: $actor->telegram_id, parse_mode: ParseMode::HTML);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $show();
    }

    /** O'quvchi yuborgan fayllarni ustoz chatiga yuboradi (qo'lda ko'rib chiqish uchun). */
    private function sendFiles(Nutgram $bot, User $actor, Homework $hw, int $assignmentId, callable $show): void
    {
        $a = $hw->assignments()->with(['student', 'files'])->find($assignmentId);

        foreach ($a?->files ?? [] as $file) {
            $caption = e($a->student?->displayName() ?? '') . ($file->kind !== 'text' && filled($file->body) ? "\n" . e(Str::limit($file->body, 600)) : '');

            try {
                $file->kind === 'text'
                    ? Messaging::push($bot, $actor->telegram_id, 'text', null, $caption . "\n\n" . e($file->body))
                    : Messaging::push($bot, $actor->telegram_id, $file->kind, $file->telegram_file_id, Str::limit($caption, 1000, '…'));
            } catch (Throwable $e) {
                report($e);
            }
        }

        $show(['view' => 'st', 'a' => $assignmentId]);
    }

    private function toggle(Homework $hw, callable $show): void
    {
        $closed = ! $hw->closed;
        $hw->update(['closed' => $closed, 'closed_at' => $closed ? now() : null]);
        $show(['flash' => $hw->closed ? 'now_closed' : 'now_open']);
    }

    private function delete(Nutgram $bot, Homework $hw): void
    {
        $hw->delete();
        Navigator::back($bot);
    }

    /** Ustozga yuborilgan kartochka tugmalari: fayllarni olish yoki o'quvchi kartochkasini ochish. */
    private function fromCard(Nutgram $bot, User $actor, string $action, int $assignmentId): void
    {
        $a = HomeworkAssignment::query()->find($assignmentId);
        $hw = $a === null ? null : $this->homework($actor, (int) $a->homework_id);

        if ($a === null || $hw === null) {
            return;
        }

        if ($action === 'card') {
            Navigator::start($bot, 'hwk', ['id' => $hw->id, 'view' => 'st', 'a' => $a->id], ['home', 'hw']);

            return;
        }

        $this->sendFiles($bot, $actor, $hw, $a->id, fn () => null);
    }

    // ---- Ichki ----------------------------------------------------------------------------------

    private function homework(User $teacher, int $id): ?Homework
    {
        return $teacher->isTeacher() && $teacher->isActive()
            ? Homework::query()->where('teacher_id', $teacher->id)->find($id)
            : null;
    }

    private function sourceName(array $source): string
    {
        $name = (string) ($source['name'] ?? '');

        if ($name === '') {
            $name = (string) __("enums.type.{$source['kind']}");
        }

        return Str::limit($name, 50) . (HomeworkGrader::readable($source) ? '' : ' ⚠️');
    }
}
