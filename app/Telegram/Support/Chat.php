<?php

namespace App\Telegram\Support;

use App\Telegram\Keyboards\MainMenu;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;
use Throwable;

final class Chat
{
    public static function delete(Nutgram $bot, ?int $messageId): void
    {
        if (! $messageId) {
            return;
        }

        try {
            $bot->deleteMessage(chat_id: $bot->chatId(), message_id: $messageId);
        } catch (Throwable) {
            // e'tibor bermaymiz
        }
    }

    /** Pastdagi (reply) klaviaturani yo'qotadi: xabar yuborib, darhol o'chiradi. */
    public static function removeReplyKeyboard(Nutgram $bot): void
    {
        try {
            $sent = $bot->sendMessage(text: '⌨️', reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true));
            self::delete($bot, $sent?->message_id);
        } catch (Throwable) {
            // e'tibor bermaymiz
        }
    }

    /** Joriy foydalanuvchining asosiy menyusini qaytaradi: xizmat xabari yuborib, darhol o'chiradi. */
    public static function restoreMenu(Nutgram $bot): void
    {
        try {
            $sent = $bot->sendMessage(text: '⌨️', reply_markup: MainMenu::keyboard());
            self::delete($bot, $sent?->message_id);
        } catch (Throwable) {
            // e'tibor bermaymiz
        }
    }
}
