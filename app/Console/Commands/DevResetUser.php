<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Telegram\Navigation\Navigator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DevResetUser extends Command
{
    protected $signature = 'bot:dev-reset {telegram_id : Foydalanuvchining Telegram ID si}';

    protected $description = "[Faqat lokal] Foydalanuvchini bazadan va keshdan butunlay o'chiradi (ro'yxatdan o'tishni qayta sinash uchun).";

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->error('Bu buyruq faqat lokal muhitda ishlaydi.');

            return self::FAILURE;
        }

        $telegramId = (int) $this->argument('telegram_id');

        Navigator::resetFor($telegramId);
        Cache::forget("tg:{$telegramId}:anchor");

        $user = User::withTrashed()->where('telegram_id', $telegramId)->first();

        if ($user === null) {
            $this->warn("Bazada bunday foydalanuvchi yo'q (kesh tozalandi).");

            return self::SUCCESS;
        }

        $user->forceDelete();
        $this->info("O'chirildi: {$telegramId}. Botga /start yozsangiz, yangidan ro'yxatdan o'tadi.");

        return self::SUCCESS;
    }
}
