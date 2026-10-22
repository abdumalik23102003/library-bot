<?php

namespace App\Console\Commands;

use App\Telegram\Support\TelegramFiles;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

class HwFetchTest extends Command
{
    protected $signature = 'hw:fetch-test {file_id : Telegram file_id (bazadagi materials.telegram_file_id)}';

    protected $description = "Telegram faylini yuklab olish AI tekshiruv uchun ishlayaptimi - tekshiradi";

    public function handle(Nutgram $bot): int
    {
        $bytes = TelegramFiles::fetch($bot, (string) $this->argument('file_id'));

        if ($bytes === null) {
            $this->error('XATO: ' . TelegramFiles::$lastError);

            return self::FAILURE;
        }

        $this->info('OK, hajmi: ' . strlen($bytes) . ' bayt');

        return self::SUCCESS;
    }
}
