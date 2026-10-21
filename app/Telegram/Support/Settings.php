<?php

namespace App\Telegram\Support;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;

/** Admin sozlamalari (hozircha: maksimal yuklash hajmi). Qiymatni o'qish: Incoming::maxMb(). */
final class Settings
{
    public const MAX_MB_LIMIT = 2000;

    /** @return array{0: bool, 1: int|string} [ok, MB yoki xato matni] */
    public static function parseMaxMb(string $text): array
    {
        $text = trim($text);

        if (! ctype_digit($text) || (int) $text < 1 || (int) $text > self::MAX_MB_LIMIT) {
            return [false, __('sys.set.err', ['max' => self::MAX_MB_LIMIT])];
        }

        return [true, (int) $text];
    }

    /** null = standart (.env) qiymatga qaytarish. */
    public static function setMaxMb(User $actor, ?int $mb): void
    {
        Setting::put('max_upload_mb', $mb === null ? null : (string) $mb);

        AuditLog::record($actor, 'setting.updated', null, ['key' => 'max_upload_mb', 'value' => $mb]);
    }

    public static function maxMbIsCustom(): bool
    {
        return filled(Setting::get('max_upload_mb'));
    }
}
