<?php

namespace App\Telegram\Support;

use App\Models\User;

/**
 * Profil maydonlari: qaysi rol nimani tahrirlay oladi va qiymatni tekshirish.
 */
final class ProfileFields
{
    /** @return array<int, string> */
    public static function editable(User $user): array
    {
        return $user->isTeacher()
            ? ['full_name', 'subject', 'phone', 'bio']
            : ['full_name', 'phone'];
    }

    /** @return array{0: bool, 1: string} [ok, qiymat yoki xato matni] */
    public static function validate(string $field, string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($text);

        return match ($field) {
            'full_name' => preg_match('/^[\p{L}\p{M}][\p{L}\p{M} \'’ʻʼ`.\-]{2,99}$/u', $text)
                ? [true, $text]
                : [false, __('reg.err.full_name')],
            'subject' => $length >= 2 && $length <= 100
                ? [true, $text]
                : [false, __('reg.err.subject')],
            'bio' => $length >= 5 && $length <= 500
                ? [true, $text]
                : [false, __('reg.err.bio')],
            'phone' => ($phone = self::normalizePhone($text)) !== null
                ? [true, $phone]
                : [false, __('reg.err.phone')],
            default => [false, __('reg.err.incomplete')],
        };
    }

    public static function normalizePhone(string $raw): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? '+' . $digits : null;
    }
}
