<?php

namespace App\Telegram\Support;

use App\Enums\Role;
use App\Models\User;
use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Navigation\Kb;
use App\Telegram\Navigation\Navigator;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

final class StaffNotifier
{
    public static function applicationCard(User $teacher): string
    {
        return __('reg.application.card', [
            'id' => $teacher->id,
            'name' => e($teacher->full_name),
            'subject' => e($teacher->subject),
            'phone' => e($teacher->phone),
            'bio' => e(Str::limit((string) $teacher->bio, 600)),
            'tg' => $teacher->username ? '@' . e($teacher->username) : 'ID ' . $teacher->telegram_id,
        ]);
    }

    /** Barcha faol admin va moderatorlarga ustoz arizasini yuboradi. */
    public static function teacherApplication(Nutgram $bot, User $teacher): void
    {
        $text = self::applicationCard($teacher);
        $keyboard = Kb::inline([[
            [__('reg.btn_approve'), "tapp:approve:{$teacher->id}"],
            [__('reg.btn_reject'), "tapp:reject:{$teacher->id}"],
        ]]);

        DevMode::recipients($teacher)->each(
            function (User $staff) use ($bot, $text, $keyboard) {
                try {
                    $bot->sendMessage(
                        text: $text,
                        chat_id: $staff->telegram_id,
                        parse_mode: ParseMode::HTML,
                        reply_markup: $keyboard,
                    );
                } catch (Throwable $e) {
                    report($e);
                }
            },
        );
    }

    /** Qarorni ustozning o'ziga yetkazadi. Tasdiqlansa menyu beriladi, rad etilsa menyu berilmaydi. */
    public static function notifyDecision(Nutgram $bot, User $teacher, bool $approved): void
    {
        $chatId = $teacher->telegram_id;
        $text = __($approved ? 'reg.approved' : 'reg.rejected');
        $edited = false;

        if ($anchor = Navigator::anchorOf($chatId)) {
            try {
                $bot->editMessageText(
                    text: $text,
                    chat_id: $chatId,
                    message_id: $anchor,
                    parse_mode: ParseMode::HTML,
                    reply_markup: null,
                );
                $edited = true;
            } catch (Throwable) {
                // yangi xabar yuboramiz
            }
        }

        Navigator::resetFor($chatId);

        if ($edited && ! $approved) {
            return;
        }

        try {
            $bot->sendMessage(
                text: $edited ? __('reg.menu_ready') : $text,
                chat_id: $chatId,
                parse_mode: ParseMode::HTML,
                reply_markup: $approved ? MainMenu::keyboard() : null,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
