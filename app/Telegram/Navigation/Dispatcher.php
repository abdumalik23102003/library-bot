<?php

namespace App\Telegram\Navigation;

use App\Telegram\Keyboards\MainMenu;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/**
 * Barcha callback va matn xabarlarini ekranlarga yo'naltiradi.
 */
final class Dispatcher
{
    public static function callback(Nutgram $bot): void
    {
        $data = (string) $bot->callbackQuery()?->data;

        // Tugma "yuklanmoqda" holatida qolib ketmasin.
        $bot->answerCallbackQuery();

        if ($data === 'b') {
            Navigator::back($bot);

            return;
        }

        $parts = explode(':', $data);
        $screen = ScreenRegistry::make((string) array_shift($parts));

        if ($screen === null) {
            Navigator::refresh($bot);

            return;
        }

        $action = (string) array_shift($parts);
        $screen->action($bot, $action, $parts);
    }

    public static function message(Nutgram $bot): void
    {
        $text = trim((string) $bot->message()?->text);

        if ($text === '') {
            $bot->sendMessage(__('bot.unknown'));

            return;
        }

        // 1) Doimiy (reply) menyu tugmalari
        if ($target = MainMenu::resolve($text)) {
            Navigator::start($bot, $target['screen'], [], $target['parents']);

            return;
        }

        // 2) Ekran matn kutayotgan bo'lsa
        if ($pending = Navigator::pendingInput($bot)) {
            $screen = ScreenRegistry::make($pending['screen']);

            if ($screen !== null) {
                $screen->input($bot, $pending['field'], $text, $pending['params']);

                // Foydalanuvchi yozgan xabarni o'chirib, chatni toza saqlaymiz.
                try {
                    $bot->deleteMessage(chat_id: $bot->chatId(), message_id: $bot->messageId());
                } catch (Throwable) {
                    // e'tibor bermaymiz
                }

                return;
            }
        }

        // 3) Boshqa hamma narsa
        $bot->sendMessage(__('bot.unknown'));
    }
}
