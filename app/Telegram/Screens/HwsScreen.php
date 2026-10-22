<?php

namespace App\Telegram\Screens;

use App\Jobs\SendHomework;
use App\Models\HomeworkAssignment;
use App\Models\User;
use App\Services\HomeworkGrader;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\TeacherLinks;
use App\Telegram\Support\TelegramFiles;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * O'quvchi uy ishini topshiradi: rasm (bir nechta bo'lishi mumkin), PDF, matn yoki boshqa fayl yuboradi.
 * Callback: hws:task (vazifani qayta olish), hws:clr (yuklanganlarni tozalash). params: a (topshiriq id), flash.
 * Qayta yuklash: yangi fayl qo'shiladi; "🗑 tozalash" bilan boshidan boshlash mumkin. Yuklangandan keyin eski AI natijasi o'chadi.
 */
class HwsScreen extends Screen
{
    public const MAX_FILES = 10;

    public const MAX_TEXT = 6000;

    public function render(Nutgram $bot, array $params): Page
    {
        $a = $this->assignment(CurrentUser::get(), (int) ($params['a'] ?? 0));

        if ($a === null) {
            return Page::make(__('hw.err.not_found'), back: true);
        }

        $hw = $a->homework;
        $lines = [
            __('hw.stu.title', ['title' => e($hw->title), 'teacher' => e($hw->teacher?->displayName() ?? '—')]),
            __('hw.stu.status', ['status' => $a->icon() . ' ' . __("hw.status.{$a->status}")]),
        ];

        if (filled($hw->note)) {
            $lines[] = '';
            $lines[] = e($hw->note);
        }

        $count = $a->files->count();
        $lines[] = '';
        $lines[] = $count > 0 ? __('hw.stu.uploaded', ['n' => $count, 'max' => self::MAX_FILES]) : __('hw.stu.nothing');

        if (! empty($params['flash'])) {
            $lines[] = '';
            $lines[] = __("hw.flash.{$params['flash']}");
        }

        $lines[] = '';
        $lines[] = $hw->closed ? __('hw.stu.closed') : __('hw.stu.ask');

        $rows = [[[__('hw.btn.task_again'), 'hws:task']]];

        if ($count > 0 && ! $hw->closed) {
            $rows[] = [[__('hw.btn.clear'), 'hws:clr']];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function mounted(Nutgram $bot, array $params): void
    {
        $a = $this->assignment(CurrentUser::get(), (int) ($params['a'] ?? 0));

        if ($a !== null && ! $a->homework->closed) {
            Navigator::awaitInput($bot, 'material');
        }
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $top = Navigator::current($bot);
        $id = (int) ($top['params']['a'] ?? 0);
        $a = ($top['screen'] ?? '') === 'hws' ? $this->assignment($actor, $id) : null;

        if ($a === null) {
            Navigator::refresh($bot);

            return;
        }

        if ($action === 'task') {
            try {
                (new SendHomework($a->homework_id))->deliver($bot, $a->homework, $a->homework->teacher, $a, $actor);
            } catch (Throwable $e) {
                report($e);
            }

            Navigator::refresh($bot);

            return;
        }

        if ($action === 'clr' && ! $a->homework->closed) {
            $a->files()->delete();
            $a->update(['status' => HomeworkAssignment::PENDING, 'submitted_at' => null] + $this->resetResult());
            Navigator::replace($bot, 'hws', ['a' => $id, 'flash' => 'cleared']);

            return;
        }

        Navigator::refresh($bot);
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $a = $this->assignment(CurrentUser::get(), (int) ($params['a'] ?? 0));
        $text = trim($text);

        if ($a === null || $field !== 'material') {
            Navigator::refresh($bot);

            return;
        }

        if ($text === '' || mb_strlen($text) > self::MAX_TEXT) {
            $this->flash($bot, $a, 'bad_text');

            return;
        }

        $this->store($bot, $a, ['kind' => 'text', 'body' => $text], true);
    }

    public function file(Nutgram $bot, string $field, array $file, array $params): void
    {
        $a = $this->assignment(CurrentUser::get(), (int) ($params['a'] ?? 0));

        if ($a === null || $field !== 'material') {
            Navigator::refresh($bot);

            return;
        }

        $source = ['kind' => $file['type'], 'name' => $file['name'] ?? null, 'mime' => $file['mime'] ?? null];
        $readable = HomeworkGrader::readable($source) && ($file['size'] ?? 0) <= TelegramFiles::MAX_BYTES;

        $this->store($bot, $a, [
            'kind' => (string) $file['type'],
            'telegram_file_id' => (string) $file['file_id'],
            'file_name' => $file['name'] ?? null,
            'mime_type' => $file['mime'] ?? null,
            'file_size' => $file['size'] ?? null,
            'body' => $file['caption'] ?? null,
        ], $readable);
    }

    // ---- Ichki ----------------------------------------------------------------------------------

    private function store(Nutgram $bot, HomeworkAssignment $a, array $data, bool $readable): void
    {
        if ($a->homework->closed) {
            $this->flash($bot, $a, 'closed');

            return;
        }

        if ($a->files->count() >= self::MAX_FILES) {
            $this->flash($bot, $a, 'full');

            return;
        }

        $a->files()->create($data);
        $a->update(['status' => HomeworkAssignment::SUBMITTED, 'submitted_at' => now()] + $this->resetResult());

        $this->flash($bot, $a, $readable ? 'ok' : 'ok_unreadable');
    }

    /** Yangi yechim yuklanganda eski AI natijasi eskirgan hisoblanadi. */
    private function resetResult(): array
    {
        return ['score' => null, 'feedback' => null, 'mistakes' => null, 'needs_teacher' => false, 'reason' => null, 'checked_at' => null];
    }

    private function flash(Nutgram $bot, HomeworkAssignment $a, string $key): void
    {
        Navigator::replace($bot, 'hws', ['a' => $a->id, 'flash' => $key]);
    }

    /** Faqat o'zining topshirig'i; ustoz bilan aloqa tasdiqlangan va o'quvchi faol bo'lishi shart. */
    private function assignment(User $student, int $id): ?HomeworkAssignment
    {
        if (! $student->isStudent() || ! $student->isActive()) {
            return null;
        }

        $a = HomeworkAssignment::query()->where('student_id', $student->id)->with(['homework.teacher', 'files'])->find($id);

        if ($a === null || $a->homework === null || $a->homework->teacher === null || ! TeacherLinks::isLinked($a->homework->teacher, $student)) {
            return null;
        }

        return $a;
    }
}
