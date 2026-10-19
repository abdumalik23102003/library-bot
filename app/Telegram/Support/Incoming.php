<?php

namespace App\Telegram\Support;

use App\Enums\MaterialType;
use App\Models\Setting;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

/**
 * Kelgan xabardan fayl ma'lumotlarini ajratib oladi.
 */
final class Incoming
{
    /** Maksimal yuklash hajmi (MB): admin sozlamasi, bo'lmasa config. */
    public static function maxMb(): int
    {
        $value = (int) (Setting::get('max_upload_mb') ?: config('library.max_upload_mb', 2000));

        return $value > 0 ? $value : 2000;
    }

    /** @return array{type: string, file_id: string, uid: ?string, name: ?string, mime: ?string, size: ?int, caption: ?string}|null */
    public static function file(?Message $message): ?array
    {
        if ($message === null) {
            return null;
        }

        $caption = filled($message->caption) ? mb_substr(trim((string) $message->caption), 0, 600) : null;

        if ($f = $message->document) {
            return self::pack(MaterialType::Document, $f->file_id, $f->file_unique_id, $f->file_name ?? null, $f->mime_type ?? null, $f->file_size ?? null, $caption);
        }

        if ($f = $message->video) {
            return self::pack(MaterialType::Video, $f->file_id, $f->file_unique_id, $f->file_name ?? null, $f->mime_type ?? null, $f->file_size ?? null, $caption);
        }

        if ($f = $message->audio) {
            $name = $f->file_name ?? null;

            return self::pack(MaterialType::Audio, $f->file_id, $f->file_unique_id, filled($name) ? $name : ($f->title ?? null), $f->mime_type ?? null, $f->file_size ?? null, $caption);
        }

        if ($f = $message->voice) {
            return self::pack(MaterialType::Voice, $f->file_id, $f->file_unique_id, null, $f->mime_type ?? null, $f->file_size ?? null, $caption);
        }

        if (is_array($message->photo) && $message->photo !== []) {
            $f = end($message->photo);

            return self::pack(MaterialType::Photo, $f->file_id, $f->file_unique_id, null, 'image/jpeg', $f->file_size ?? null, $caption);
        }

        return null;
    }

    private static function pack(MaterialType $type, string $id, ?string $uid, ?string $name, ?string $mime, ?int $size, ?string $caption): array
    {
        return [
            'type' => $type->value,
            'file_id' => $id,
            'uid' => $uid,
            'name' => $name,
            'mime' => $mime,
            'size' => $size,
            'caption' => $caption,
        ];
    }
}
