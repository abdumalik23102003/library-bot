<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\MaterialShare;
use App\Models\User;
use App\Telegram\Support\MaterialSender;
use App\Telegram\Support\TeacherLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Ustozning materialini o'quvchilarga yuboradi (navbat orqali) va oxirida ustozga hisobot beradi.
 * Navbat ishga tushganda hamma narsa qayta tekshiriladi: ustoz faolmi, material uniki va yopiq emasmi,
 * o'quvchi hali ham tasdiqlangan aloqadami. Xato bitta o'quvchida to'xtatmaydi, hisobotga tushadi.
 */
class ShareMaterial implements ShouldQueue
{
    use Queueable;

    /** Qayta urinishlar ichkarida (har o'quvchi uchun), shuning uchun job o'zi bir marta ishlaydi. */
    public int $tries = 1;

    public int $timeout = 900;

    /** Hisobotda ko'rsatiladigan xatoli o'quvchilar soni chegarasi. */
    private const FAILED_LIST_LIMIT = 10;

    /** @param  array<int, int>  $studentIds */
    public function __construct(
        public int $teacherId,
        public int $materialId,
        public array $studentIds,
    ) {
    }

    public function handle(Nutgram $bot): void
    {
        $teacher = User::query()->find($this->teacherId);

        if ($teacher === null) {
            return;
        }

        $material = Material::query()->find($this->materialId);

        if (! $teacher->isTeacher() || ! $teacher->isActive()
            || $material === null
            || $material->owner_id !== $teacher->id) {
            $this->notify($bot, $teacher, __('cls.share.aborted'));

            return;
        }

        $students = User::query()->whereKey($this->studentIds)->get()
            ->filter(fn (User $student) => $student->isStudent()
                && $student->isActive()
                && TeacherLinks::isLinked($teacher, $student))
            ->values();

        $skipped = count($this->studentIds) - $students->count();
        $sent = 0;
        $failed = [];

        foreach ($students as $student) {
            try {
                $this->deliver($bot, $material, $student);
            } catch (Throwable $e) {
                $failed[] = $student->displayName();

                continue;
            }

            MaterialShare::query()->updateOrCreate(
                ['material_id' => $material->id, 'student_id' => $student->id],
                ['sent_by' => $teacher->id],
            );

            $sent++;

            // Telegram chegarasi (~30 xabar/soniya) dan ancha past turamiz.
            usleep(70_000);
        }

        $this->notify($bot, $teacher, $this->report($material, $sent, $failed, $skipped));
    }

    public function failed(Throwable $e): void
    {
        report($e);

        $teacher = User::query()->find($this->teacherId);

        if ($teacher !== null) {
            $this->notify(app(Nutgram::class), $teacher, __('cls.share.crashed'));
        }
    }

    /** Bitta o'quvchiga yuboradi. "Too Many Requests: retry after N" bo'lsa kutib, 3 martagacha qayta uriniladi. */
    private function deliver(Nutgram $bot, Material $material, User $student): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                MaterialSender::send($bot, $material, $student->telegram_id);

                return;
            } catch (Throwable $e) {
                if ($attempt >= 3 || ! preg_match('/retry after (\d+)/i', $e->getMessage(), $m)) {
                    throw $e;
                }

                sleep(min((int) $m[1], 30) + 1);
            }
        }
    }

    /** @param  array<int, string>  $failed */
    private function report(Material $material, int $sent, array $failed, int $skipped): string
    {
        $text = __('cls.share.report', [
            'title' => e($material->title ?: __('mats.untitled')),
            'sent' => $sent,
            'failed' => count($failed),
            'skipped' => $skipped,
        ]);

        if ($failed !== []) {
            $names = array_map('e', array_slice($failed, 0, self::FAILED_LIST_LIMIT));
            $more = count($failed) - count($names);

            $text .= "\n\n" . __('cls.share.failed_list', ['names' => implode(', ', $names)])
                . ($more > 0 ? ' ' . __('cls.share.and_more', ['n' => $more]) : '');
        }

        return $text;
    }

    private function notify(Nutgram $bot, User $teacher, string $text): void
    {
        try {
            $bot->sendMessage(text: $text, chat_id: $teacher->telegram_id, parse_mode: ParseMode::HTML);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
