<?php

namespace App\Jobs;

use App\Models\Homework;
use App\Models\HomeworkAssignment;
use App\Services\DocxText;
use App\Services\HomeworkGrader;
use App\Telegram\Navigation\Kb;
use App\Telegram\Support\HomeworkReport;
use App\Telegram\Support\TelegramFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * "🤖 AI tekshir": topshirilgan yechimlarni Gemini bilan tekshiradi, natijani saqlaydi va ustozga umumiy hisobot yuboradi.
 * Har bir o'quvchi alohida: biri o'qilmasa yoki xato bersa, qolganlari tekshirilaveradi, u ustozga "ko'rib chiqing" deb belgilanadi.
 * $all=false: faqat yangi (tekshirilmagan) va xato bilan tugaganlar; $all=true: hammasi qayta.
 */
class CheckHomework implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    /** Oxirgi o'qib bo'lmaslik sababi (parts() to'ldiradi). */
    private string $why = '';

    public function __construct(public int $homeworkId, public bool $all = false)
    {
    }

    public function handle(Nutgram $bot, HomeworkGrader $grader): void
    {
        $homework = Homework::query()->with('teacher')->find($this->homeworkId);
        $teacher = $homework?->teacher;

        if ($homework === null || $teacher === null || ! $teacher->isTeacher() || ! $teacher->isActive()) {
            return;
        }

        // Bir vaqtda ikkita tekshiruv ishlamasin (navbat qayta yuborsa ham).
        $lock = "hw:check:{$homework->id}";

        if (! Cache::add($lock, true, now()->addHours(2))) {
            return;
        }

        try {
            $this->run($bot, $grader, $homework);
        } finally {
            Cache::forget($lock);
        }
    }

    private function run(Nutgram $bot, HomeworkGrader $grader, Homework $homework): void
    {
        $teacherChat = (int) $homework->teacher->telegram_id;
        $startedAt = now();

        if (! $grader->enabled()) {
            $this->say($bot, $teacherChat, __('hw.check.no_key'));

            return;
        }

        $task = (array) $homework->task;
        $taskParts = $this->parts($bot, $task, __('hw.check.task_label'));

        if ($taskParts === null && blank($homework->note)) {
            $this->say($bot, $teacherChat, __('hw.check.task_unreadable', ['title' => e($homework->title), 'reason' => e($this->why)]));

            return;
        }

        $key = (array) ($homework->key ?? []);
        $keyParts = $key !== [] ? ($this->parts($bot, $key, __('hw.check.key_label')) ?? []) : [];

        $statuses = $this->all
            ? [HomeworkAssignment::SUBMITTED, HomeworkAssignment::CHECKED, HomeworkAssignment::UNREADABLE, HomeworkAssignment::ERROR]
            : [HomeworkAssignment::SUBMITTED, HomeworkAssignment::ERROR];

        $assignments = $homework->assignments()->whereIn('status', $statuses)->with(['student', 'files'])->orderBy('id')->get();

        $this->say($bot, $teacherChat, __('hw.check.started', ['title' => e($homework->title), 'n' => $assignments->count()]));

        $interval = max(0, (int) config('services.gemini.interval', 4));

        foreach ($assignments as $a) {
            $this->checkOne($bot, $grader, $homework, $a, $taskParts ?? [], $keyParts);

            if ($interval > 0) {
                sleep($interval);
            }
        }

        $checked = $homework->assignments()->where('status', HomeworkAssignment::CHECKED)->get();
        $summary = $grader->summarize($homework, $checked->pluck('mistakes')->map(fn ($m) => (array) $m)->filter()->values()->all());

        $chunks = HomeworkReport::build($homework, $summary);
        $homework->forceFill(['report' => implode("\n\x1E", $chunks), 'checked_at' => now()])->save();

        foreach ($chunks as $chunk) {
            $this->say($bot, $teacherChat, $chunk);
        }

        $this->sendAttentionCards($bot, $homework, $teacherChat);
        $this->notifyStudents($bot, $homework, $startedAt);

        if (! $homework->closed) {
            $homework->forceFill(['closed' => true, 'closed_at' => now()])->save();
        }
    }

    private function checkOne(Nutgram $bot, HomeworkGrader $grader, Homework $homework, HomeworkAssignment $a, array $taskParts, array $keyParts): void
    {
        $studentParts = [];
        $skipped = 0;

        foreach ($a->files as $file) {
            if ($file->kind === 'text') {
                $studentParts[] = ['text' => "O'quvchi matni:\n" . $file->body];

                continue;
            }

            $source = ['kind' => $file->kind, 'name' => $file->file_name, 'mime' => $file->mime_type];
            $parts = HomeworkGrader::readable($source) ? $this->parts($bot, $source + ['file_id' => $file->telegram_file_id], '') : null;

            if ($parts === null) {
                $skipped++;

                continue;
            }

            $studentParts = array_merge($studentParts, $parts);

            if (filled($file->body)) {
                $studentParts[] = ['text' => "O'quvchi izohi:\n" . $file->body];
            }
        }

        if ($studentParts === []) {
            $a->update([
                'status' => HomeworkAssignment::UNREADABLE,
                'needs_teacher' => true,
                'reason' => $a->files->isEmpty() ? __('hw.check.no_files') : __('hw.check.files_unreadable', ['n' => $skipped]) . ($this->why !== '' ? " ({$this->why})" : ''),
                'checked_at' => now(),
            ]);

            return;
        }

        try {
            $r = $grader->grade($homework, $taskParts, $keyParts, $studentParts);
        } catch (Throwable $e) {
            report($e);
            $a->update([
                'status' => HomeworkAssignment::ERROR,
                'needs_teacher' => true,
                'reason' => __('hw.check.ai_error', ['msg' => mb_substr($e->getMessage(), 0, 120)]),
                'checked_at' => now(),
            ]);

            return;
        }

        if (! $r['readable']) {
            $a->update([
                'status' => HomeworkAssignment::UNREADABLE,
                'needs_teacher' => true,
                'reason' => $r['reason'] ?: __('hw.check.ai_unreadable'),
                'checked_at' => now(),
            ]);

            return;
        }

        $reasons = array_filter([$r['reason'], $skipped > 0 ? __('hw.check.some_skipped', ['n' => $skipped]) : null]);

        $a->update([
            'status' => HomeworkAssignment::CHECKED,
            'score' => $r['score'],
            'feedback' => $r['feedback'],
            'mistakes' => $r['mistakes'],
            'needs_teacher' => $r['needs_teacher'] || $skipped > 0,
            'reason' => $reasons === [] ? null : implode('; ', $reasons),
            'checked_at' => now(),
        ]);
    }

    /** Ustoz o'zi ko'rishi kerak bo'lgan o'quvchilar uchun kartochka: sabab, ball va [📎 Fayllar] / [👁 Batafsil]. */
    private function sendAttentionCards(Nutgram $bot, Homework $homework, int $chatId): void
    {
        $list = $homework->assignments()
            ->where(fn ($q) => $q->whereIn('status', [HomeworkAssignment::UNREADABLE, HomeworkAssignment::ERROR])->orWhere('needs_teacher', true))
            ->with('student')
            ->orderBy('id')
            ->get();

        foreach ($list->take(15) as $a) {
            $lines = [__('hw.report.card', [
                'name' => e($a->student?->displayName() ?? '—'),
                'reason' => e(Str::limit($a->reason ?: __('hw.report.no_reason'), 200)),
            ])];

            if ($a->status === HomeworkAssignment::CHECKED) {
                $lines[] = __('hw.detail.score', ['score' => $a->score, 'max' => $homework->max_score]);
            }

            try {
                $bot->sendMessage(
                    text: implode("\n", $lines),
                    chat_id: $chatId,
                    parse_mode: ParseMode::HTML,
                    reply_markup: Kb::inline([[
                        [__('hw.btn.get_files'), "hwk:fls:{$a->id}"],
                        [__('hw.btn.detail'), "hwk:card:{$a->id}"],
                    ]]),
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($list->count() > 15) {
            $this->say($bot, $chatId, __('hw.report.more_cards', ['n' => $list->count() - 15]));
        }
    }

    /**
     * Manbani Gemini bo'laklariga aylantiradi. null = o'qib bo'lmadi (sababi $this->why da).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function parts(Nutgram $bot, array $source, string $label): ?array
    {
        $this->why = '';

        if (! HomeworkGrader::readable($source)) {
            $this->why = __('hw.check.why_format');

            return null;
        }

        $head = $label !== '' ? [['text' => $label]] : [];

        if (($source['kind'] ?? '') === 'text') {
            return array_merge($head, [['text' => (string) $source['text']]]);
        }

        $bytes = filled($source['file_id'] ?? null) ? TelegramFiles::fetch($bot, (string) $source['file_id']) : null;

        if ($bytes === null) {
            $this->why = __('hw.check.why_fetch', ['msg' => (string) TelegramFiles::$lastError]);

            return null;
        }

        $mime = ($source['kind'] ?? '') === 'photo' ? 'image/jpeg' : HomeworkGrader::mime($source);

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $text = DocxText::extract($bytes);

            if ($text === null) {
                $this->why = __('hw.check.why_docx');

                return null;
            }

            return array_merge($head, [['text' => mb_substr($text, 0, 60000)]]);
        }

        if (in_array($mime, ['text/plain', 'text/markdown', 'text/csv'], true)) {
            return array_merge($head, [['text' => mb_substr(mb_convert_encoding($bytes, 'UTF-8', 'UTF-8'), 0, 60000)]]);
        }

        return array_merge($head, [['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]]]);
    }

    /** Tekshirilgan o'quvchilarga baho va xatolar xabari (AI ishonmaganlarga yuborilmaydi, ularni ustoz ko'radi). */
    private function notifyStudents(Nutgram $bot, Homework $homework, $since): void
    {
        $list = $homework->assignments()
            ->where('status', HomeworkAssignment::CHECKED)
            ->where('needs_teacher', false)
            ->where('checked_at', '>=', $since)
            ->with('student')
            ->get();

        foreach ($list as $a) {
            $chatId = $a->student?->telegram_id;

            if (! $chatId) {
                continue;
            }

            $lines = [
                __('hw.result.title', ['title' => e($homework->title)]),
                '',
                __('hw.result.score', ['score' => $a->score, 'max' => $homework->max_score]),
            ];

            if (filled($a->feedback)) {
                $lines[] = '';
                $lines[] = e($a->feedback);
            }

            if (! empty($a->mistakes)) {
                $lines[] = '';
                $lines[] = __('hw.result.fix');

                foreach (array_slice((array) $a->mistakes, 0, 8) as $m) {
                    $lines[] = '• ' . e($m);
                }
            }

            $this->say($bot, (int) $chatId, implode("\n", $lines));
            usleep(70_000);
        }
    }

    public function failed(Throwable $e): void
    {
        report($e);
        Cache::forget("hw:check:{$this->homeworkId}");

        $teacher = Homework::query()->with('teacher')->find($this->homeworkId)?->teacher;

        if ($teacher !== null) {
            $this->say(app(Nutgram::class), (int) $teacher->telegram_id, __('hw.check.crashed'));
        }
    }

    private function say(Nutgram $bot, int $chatId, string $text): void
    {
        try {
            $bot->sendMessage(text: $text, chat_id: $chatId, parse_mode: ParseMode::HTML);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
