<?php

namespace App\Telegram\Navigation;

use App\Models\User;
use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Support\Chat;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Entry;
use App\Telegram\Support\Incoming;
use SergiX44\Nutgram\Nutgram;

/**
 * Barcha callback va xabarlarni ekranlarga yo'naltiradi. Xabar turlari (matn, kontakt, fayl) shu yerda ajratiladi.
 */
final class Dispatcher
{
    public static function callback(Nutgram $bot): void
    {
        $data = (string) $bot->callbackQuery()?->data;

        $bot->answerCallbackQuery();

        $user = CurrentUser::get();

        if ($data === 'b') {
            Navigator::current($bot) === null ? self::restart($bot, $user) : Navigator::back($bot);

            return;
        }

        $parts = explode(':', $data);
        $key = (string) array_shift($parts);

        if (! Entry::allows($user, $key)) {
            self::restart($bot, $user);

            return;
        }

        $screen = ScreenRegistry::make($key);

        if ($screen === null) {
            Navigator::refresh($bot);

            return;
        }

        $screen->action($bot, (string) array_shift($parts), $parts);
    }

    public static function message(Nutgram $bot): void
    {
        $user = CurrentUser::get();
        $message = $bot->message();

        if ($message?->contact !== null) {
            self::contact($bot, $user);

            return;
        }

        if ($file = Incoming::file($message)) {
            self::file($bot, $user, $file);

            return;
        }

        $text = trim((string) $message?->text);

        if ($text === '') {
            $bot->sendMessage(__('bot.unknown'));

            return;
        }

        $gate = Entry::gate($user);

        // 1) Doimiy (reply) menyu tugmalari
        if ($gate === null && ($target = MainMenu::resolve($text, $user))) {
            Navigator::start($bot, $target['screen'], [], $target['parents']);

            return;
        }

        // 2) Ekran matn kutayotgan bo'lsa
        $pending = Navigator::pendingInput($bot);

        if ($pending && Entry::allows($user, $pending['screen'])) {
            self::deliverInput($bot, $pending, $text);

            return;
        }

        if ($pending) {
            Navigator::clearInput($bot);
        }

        // 3) Ro'yxatdan o'tmaganlarni mavjud ekranga qaytaramiz
        if ($gate !== null) {
            Navigator::current($bot) !== null
                ? Navigator::refresh($bot)
                : Navigator::start($bot, $gate['screen'], [], $gate['parents']);

            return;
        }

        $bot->sendMessage(__('bot.unknown'));
    }

    /** Fayl faqat ekran "material" kutayotganda qabul qilinadi. */
    private static function file(Nutgram $bot, User $user, array $file): void
    {
        $pending = Navigator::pendingInput($bot);

        if (! $pending || $pending['field'] !== 'material' || ! Entry::allows($user, $pending['screen'])) {
            $bot->sendMessage(__('mat.file_hint'));

            return;
        }

        $screen = ScreenRegistry::make($pending['screen']);

        if ($screen === null) {
            Navigator::clearInput($bot);

            return;
        }

        $screen->file($bot, $pending['field'], $file, $pending['params']);

        Chat::delete($bot, $bot->messageId());
    }

    /** Telefon kontakti (faqat "phone" maydoni kutilayotganda qabul qilinadi). */
    private static function contact(Nutgram $bot, User $user): void
    {
        $contact = $bot->message()?->contact;
        $pending = Navigator::pendingInput($bot);

        if ($contact === null || ! $pending || $pending['field'] !== 'phone' || ! Entry::allows($user, $pending['screen'])) {
            $bot->sendMessage(__('bot.unknown'));

            return;
        }

        if ($contact->user_id !== null && $contact->user_id !== $bot->userId()) {
            $bot->sendMessage(__('reg.phone_not_yours'));

            return;
        }

        self::deliverInput($bot, $pending, (string) $contact->phone_number);
    }

    /** Kiritilgan qiymatni ekranga beradi va foydalanuvchi xabarini o'chiradi. */
    private static function deliverInput(Nutgram $bot, array $pending, string $text): void
    {
        $screen = ScreenRegistry::make($pending['screen']);

        if ($screen === null) {
            Navigator::clearInput($bot);

            return;
        }

        $screen->input($bot, $pending['field'], $text, $pending['params']);

        Chat::delete($bot, $bot->messageId());
    }

    private static function restart(Nutgram $bot, User $user): void
    {
        $gate = Entry::gate($user);

        $gate
            ? Navigator::start($bot, $gate['screen'], [], $gate['parents'])
            : Navigator::start($bot, 'home');
    }
}
