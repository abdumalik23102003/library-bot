<?php

namespace App\Jobs;

use App\Models\Homework;
use App\Models\HomeworkAssignment;
use App\Models\User;
use App\Telegram\Navigation\Kb;
use App\Telegram\Support\Messaging;
use App\Telegram\Support\TeacherLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Uy ishini o'quvchilarga yuboradi (vazifa fayli + [📤 Topshirish] tugmasi) va ustozga qisqa hisobot beradi.
 * Yuborib bo'lmagan o'quvchida ham topshiriq ro'yxatda qoladi (u "📝 Uy ishi" bo'limidan ko'ra oladi).
 */
class SendHomework implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $homeworkId)
    {
    }

    public function handle(Nutgram $bot): void
    {
        $homework = Homework::query()->with('teacher')->find($this->homeworkId);
        $teacher = $homework?->teacher;

        if ($homework === null || $teacher === null || ! $teacher->isTeacher() || ! $teacher->isActive()) {
            return;
        }

        $sent = 0;
        $failed = [];

        $homework->assignments()->with('student')->orderBy('id')->each(function (HomeworkAssignment $a) use ($bot, $homework, $teacher, &$sent, &$failed) {
            $student = $a->student;

            if ($student === null || ! $student->isActive() || ! TeacherLinks::isLinked($teacher, $student)) {
                $failed[] = $student?->displayName() ?? '—';

                return;
            }

            try {
                Messaging::attempt(fn () => $this->deliver($bot, $homework, $teacher, $a, $student));
                $sent++;
            } catch (Throwable) {
                $failed[] = $student->displayName();
            }

            usleep(70_000);
        });

        $text = __('hw.send.report', ['title' => e($homework->title), 'sent' => $sent, 'failed' => count($failed)]);

        if ($failed !== []) {
            $text .= "\n\n" . __('hw.send.failed_list', ['names' => implode(', ', array_map('e', array_slice($failed, 0, 10)))]);
        }

        try {
            $bot->sendMessage(text: $text, chat_id: $teacher->telegram_id, parse_mode: ParseMode::HTML);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function deliver(Nutgram $bot, Homework $homework, User $teacher, HomeworkAssignment $a, User $student): void
    {
        $task = (array) $homework->task;
        $caption = __('hw.send.card', ['title' => e($homework->title), 'teacher' => e($teacher->displayName())]);

        if (filled($homework->note)) {
            $caption .= "\n\n" . e(Str::limit($homework->note, 500));
        }

        $kind = (string) ($task['kind'] ?? 'text');
        $fileId = $task['file_id'] ?? null;
        $markup = Kb::inline([[[__('hw.btn.submit'), "hw:open:{$a->id}"]]]);

        // Matn/havola turidagi vazifa: matn xabar sifatida; fayl turidagi: fayl + sarlavha (1024 belgigacha).
        if (! in_array($kind, ['photo', 'document', 'video', 'audio', 'voice'], true) || blank($fileId)) {
            $caption .= "\n\n" . e(Str::limit((string) ($task['text'] ?? ''), 3000));
            Messaging::push($bot, $student->telegram_id, 'text', null, $caption, $markup);

            return;
        }

        Messaging::push($bot, $student->telegram_id, $kind, (string) $fileId, Str::limit($caption, 1000, '…'), $markup);
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
