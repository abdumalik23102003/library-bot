<?php

namespace App\Telegram\Support;

use Illuminate\Support\Facades\Http;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Telegram faylini baytlarga yuklab oladi (AI ga berish uchun).
 * Local Bot API getFile da konteyner ichidagi absolyut yo'l qaytaradi. Uni o'qish yo'llari:
 *  1) Laravel konteyneri o'sha papkani o'qiy olsa, to'g'ridan-to'g'ri;
 *  2) "tg-files" fayl serveri (compose.override.yaml, root ruxsati bilan faqat o'qiydi);
 *  3) Bot API serverining HTTP manzili.
 */
final class TelegramFiles
{
    /** AI so'rovi hajmi chegarasi uchun bitta fayl chegarasi. */
    public const MAX_BYTES = 15 * 1024 * 1024;

    private const DIR = '/var/lib/telegram-bot-api/';

    public static ?string $lastError = null;

    public static function fetch(Nutgram $bot, string $fileId): ?string
    {
        self::$lastError = null;

        try {
            $file = $bot->getFile($fileId);
            $path = (string) ($file?->file_path ?? '');

            if ($path === '') {
                self::$lastError = "Telegram fayl yo'lini qaytarmadi";

                return null;
            }

            if (($file->file_size ?? 0) > self::MAX_BYTES) {
                self::$lastError = 'fayl juda katta (15 MB dan oshiq)';

                return null;
            }

            if (str_starts_with($path, '/') && is_file($path) && is_readable($path)) {
                return (string) file_get_contents($path);
            }

            $relative = str_starts_with($path, self::DIR) ? substr($path, strlen(self::DIR)) : ltrim($path, '/');

            $urls = [
                rtrim((string) env('TG_FILES_URL', 'http://tg-files:8080'), '/') . '/' . $relative,
                rtrim((string) config('nutgram.config.api_url'), '/') . '/file/bot' . config('nutgram.token') . '/' . $path,
            ];

            $errors = [];

            foreach ($urls as $url) {
                try {
                    $response = Http::timeout(120)->get($url);

                    if ($response->successful() && strlen($response->body()) <= self::MAX_BYTES) {
                        return $response->body();
                    }

                    $errors[] = 'HTTP ' . $response->status();
                } catch (Throwable $e) {
                    $errors[] = mb_substr($e->getMessage(), 0, 80);
                }
            }

            self::$lastError = "fayl o'qilmadi (" . implode('; ', $errors) . ")";
        } catch (Throwable $e) {
            self::$lastError = mb_substr($e->getMessage(), 0, 150);
        }

        return null;
    }
}
