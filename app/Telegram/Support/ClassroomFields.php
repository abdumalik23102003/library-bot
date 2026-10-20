<?php

namespace App\Telegram\Support;

use App\Models\Classroom;
use App\Models\User;

/** Sinf maydonlarini tekshirish. */
final class ClassroomFields
{
    public const MAX_PER_TEACHER = 30;

    public static function limitReached(User $teacher): bool
    {
        return Classroom::query()->where('teacher_id', $teacher->id)->count() >= self::MAX_PER_TEACHER;
    }

    /** @return array{0: bool, 1: string}  [to'g'rimi, qiymat yoki xato matni] */
    public static function name(User $teacher, string $text, ?int $ignoreId = null): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($name);

        if ($length < 2 || $length > 50) {
            return [false, __('cls.err.name')];
        }

        $exists = Classroom::query()
            ->where('teacher_id', $teacher->id)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        return $exists ? [false, __('cls.err.exists')] : [true, $name];
    }

    /** "-" yuborilsa tavsif tozalanadi (null). @return array{0: bool, 1: string|null} */
    public static function description(string $text): array
    {
        $text = trim($text);

        if ($text === '-') {
            return [true, null];
        }

        $length = mb_strlen($text);

        return $length >= 3 && $length <= 300 ? [true, $text] : [false, __('cls.err.description')];
    }
}
