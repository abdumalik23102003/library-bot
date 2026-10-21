<?php

namespace App\Jobs;

use App\Models\User;
use App\Telegram\Support\MessageTargets;
use App\Telegram\Support\Messaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Ko'p qabul qiluvchiga xabar (navbat orqali). Ruxsat navbat ishlaganda QAYTA tekshiriladi,
 * bitta qabul qiluvchidagi xato to'xtatmaydi, oxirida yuboruvchiga hisobot boradi.
 */
class SendMessages implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    /** @param  array<int, int>  $recipientIds */
    public function __construct(
        public int $senderId,
        public array $recipientIds,
        public string $kind,
        public ?string $fileId,
        public ?string $text,
    ) {
    }

    public function handle(Nutgram $bot): void
    {
        $from = User::query()->find($this->senderId);

        if ($from === null || ! $from->isActive()) {
            return;
        }

        $users = MessageTargets::pool($from)->whereIn('id', $this->recipientIds)->orderBy('id')->get();
        $sent = 0;
        $failed = count($this->recipientIds) - $users->count();

        foreach ($users as $user) {
            $result = Messaging::send($bot, $from, [$user], $this->kind, $this->fileId, $this->text);
            $sent += $result['sent'];
            $failed += $result['failed'];

            // Telegram chegarasidan ancha past turamiz.
            usleep(70_000);
        }

        $this->notify($bot, $from, __('msg.wr.report', ['sent' => $sent, 'failed' => $failed]));
    }

    public function failed(Throwable $e): void
    {
        report($e);

        $from = User::query()->find($this->senderId);

        if ($from !== null) {
            $this->notify(app(Nutgram::class), $from, __('msg.wr.crashed'));
        }
    }

    private function notify(Nutgram $bot, User $to, string $text): void
    {
        try {
            $bot->sendMessage(text: $text, chat_id: $to->telegram_id, parse_mode: ParseMode::HTML);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
