<?php

namespace App\Telegram\Support;

use App\Enums\Access;
use App\Models\Category;
use App\Models\User;

/**
 * Kategoriya maydonlarini tekshirish va umumiy qoidalar.
 */
final class CategoryFields
{
    public const MAX_PER_TEACHER = 30;

    public static function icon(Access $access): string
    {
        return match ($access) {
            Access::Public => '🌐',
            Access::Protected => '🔐',
            Access::Private => '🔒',
        };
    }

    /** @return array{0: bool, 1: ?string} [ok, qiymat yoki xato matni] */
    public static function name(User $owner, string $text, ?int $ignoreId = null): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($text);

        if ($length < 2 || $length > 50) {
            return [false, __('cat.err.name')];
        }

        $exists = Category::query()
            ->forTeacher($owner)
            ->whereRaw('lower(name) = ?', [mb_strtolower($text)])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        return $exists ? [false, __('cat.err.exists')] : [true, $text];
    }

    /** "-" yuborilsa, tavsif tozalanadi (null). @return array{0: bool, 1: ?string} */
    public static function description(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '-') {
            return [true, null];
        }

        $length = mb_strlen($text);

        return $length >= 3 && $length <= 300 ? [true, $text] : [false, __('cat.err.description')];
    }

    public static function limitReached(User $owner): bool
    {
        return $owner->categories()->count() >= self::MAX_PER_TEACHER;
    }
}
