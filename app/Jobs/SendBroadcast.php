<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\User;
use App\Telegram\Support\Broadcasts;
use App\Telegram\Support\Messaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Admin broadcast: tanlangan auditoriyaga xabarni navbat orqali yuboradi va oxirida adminga hisobot beradi.
 * Qabul qiluvchilar navbat ishlaganda QAYTA hisoblanadi. Bitta foydalanuvchidagi xato to'xtatmaydi.
 */
class SendBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public int $adminId,
        public string $audience,
        public string $kind,
        public ?string $fileId,
        public string $text,
    ) {
    }

    public function handle(Nutgram $bot): void
    {
        $admin = User::query()->find($this->adminId);

        if ($admin === null || ! $admin->isStaff() || ! $admin->isActive() || ! Broadcasts::valid($this->audience, $admin)) {
            return;
        }

        $caption = __('msg.bc.header') . e($this->text);
        $sent = 0;
        $failed = 0;

        Broadcasts::recipients($admin, $this->audience)->chunkById(100, function ($users) use ($bot, $caption, &$sent, &$failed) {
            foreach ($users as $user) {
                try {
                    Messaging::attempt(fn () => Messaging::push($bot, $user->telegram_id, $this->kind, $this->fileId, $caption));
                    $sent++;
                } catch (Throwable) {
                    $failed++;
                }

                // Telegram chegarasi (~30 xabar/soniya) dan ancha past turamiz.
                usleep(70_000);
            }
        });

        AuditLog::record($admin, 'broadcast.sent', null, [
            'name' => Broadcasts::label($this->audience),
            'sent' => $sent,
            'failed' => $failed,
        ]);

        $this->notify($bot, $admin, __('msg.bc.report', [
            'aud' => Broadcasts::label($this->audience),
            'sent' => $sent,
            'failed' => $failed,
        ]));
    }

    public function failed(Throwable $e): void
    {
        report($e);

        $admin = User::query()->find($this->adminId);

        if ($admin !== null) {
            $this->notify(app(Nutgram::class), $admin, __('msg.bc.crashed'));
        }
    }

    private function notify(Nutgram $bot, User $admin, string $text): void
    {
        try {
            $bot->sendMessage(text: $text, chat_id: $admin->telegram_id, parse_mode: ParseMode::HTML);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
