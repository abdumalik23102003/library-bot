<?php

namespace App\Telegram\Support;

use App\Models\User;
use App\Telegram\Navigation\Kb;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/** Ustoz-o'quvchi aloqasi bo'yicha xabarlar (qabul qiluvchi botni bloklagan bo'lsa ham xato chiqmaydi). */
final class LinkNotifier
{
    public static function tg(User $user): string
    {
        return $user->username ? '@' . e($user->username) : 'ID ' . $user->telegram_id;
    }

    public static function joinCard(User $student): string
    {
        return __('links.notify.join_card', ['name' => e($student->displayName()), 'tg' => self::tg($student)]);
    }

    public static function protectedCard(User $student): string
    {
        return __('links.notify.protected_card', ['name' => e($student->displayName()), 'tg' => self::tg($student)]);
    }

    /** Ustozga: yangi qo'shilish so'rovi (tugmalar: req:jacc / req:jrej). */
    public static function joinRequest(Nutgram $bot, User $teacher, User $student): void
    {
        self::send($bot, $teacher, self::joinCard($student), Kb::inline([[
            [__('links.btn.accept'), "req:jacc:{$student->id}"],
            [__('links.btn.reject'), "req:jrej:{$student->id}"],
        ]]));
    }

    /** Ustozga: himoyalangan kirish so'rovi (tugmalar: req:pacc / req:prej). */
    public static function protectedRequest(Nutgram $bot, User $teacher, User $student): void
    {
        self::send($bot, $teacher, self::protectedCard($student), Kb::inline([[
            [__('links.btn.accept'), "req:pacc:{$student->id}"],
            [__('links.btn.reject'), "req:prej:{$student->id}"],
        ]]));
    }

    /** O'quvchiga: qo'shilish so'rovi natijasi. */
    public static function joinDecision(Nutgram $bot, User $teacher, User $student, bool $accepted): void
    {
        self::send($bot, $student, __($accepted ? 'links.notify.join_accepted' : 'links.notify.join_rejected', [
            'teacher' => e($teacher->displayName()),
        ]));
    }

    /** O'quvchiga: ro'yxatdan chiqarilgani. */
    public static function removed(Nutgram $bot, User $teacher, User $student): void
    {
        self::send($bot, $student, __('links.notify.removed', ['teacher' => e($teacher->displayName())]));
    }

    /** O'quvchiga: himoyalangan kirish natijasi. $result: granted | rejected | revoked */
    public static function protectedDecision(Nutgram $bot, User $teacher, User $student, string $result): void
    {
        self::send($bot, $student, __("links.notify.protected_{$result}", ['teacher' => e($teacher->displayName())]));
    }

    private static function send(Nutgram $bot, User $to, string $text, ?InlineKeyboardMarkup $keyboard = null): void
    {
        try {
            $bot->sendMessage(
                text: $text,
                chat_id: $to->telegram_id,
                parse_mode: ParseMode::HTML,
                reply_markup: $keyboard,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
