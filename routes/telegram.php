<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Middleware\EnsureUser;
use App\Telegram\Navigation\Dispatcher;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\DevMode;
use App\Telegram\Support\Entry;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

// ---- Global middleware: foydalanuvchini aniqlash, blok tekshiruvi ------------------------------

$bot->middleware(EnsureUser::class);

// ---- Buyruqlar ----------------------------------------------------------------------------

$bot->onCommand('start', function (Nutgram $bot) {
    $gate = Entry::gate(CurrentUser::get());

    if ($gate !== null) {
        // Ro'yxatdan o'tmagan yoki tasdiqlanmagan: asosiy menyusiz, ro'yxatdan o'tish ekraniga.
        $bot->sendMessage(text: __('bot.start.welcome'), reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true));
        Navigator::start($bot, $gate['screen'], [], $gate['parents']);

        return;
    }

    // Doimiy (reply) menyu alohida xabar bilan o'rnatiladi, ekranlar esa anchor xabarda.
    $bot->sendMessage(text: __('bot.start.welcome'), reply_markup: MainMenu::keyboard());
    Navigator::start($bot, 'home');
});

$bot->onCommand('ping', function (Nutgram $bot) {
    $bot->sendMessage('pong 🏓');
});

// ---- [VAQTINCHALIK] Test rejimi (faqat lokal, faqat ENV admin) ---------------------------------

$bot->onCommand('dev_teacher', fn (Nutgram $bot) => DevMode::enter($bot, null));
$bot->onCommand('dev_student', fn (Nutgram $bot) => DevMode::enter($bot, null));
$bot->onCommand('dev_admin', fn (Nutgram $bot) => DevMode::exit($bot));

// ---- Umumiy yo'naltiruvchilar (eng oxirida bo'lishi kerak) --------------------------------------

$bot->onCallbackQuery(function (Nutgram $bot) {
    Dispatcher::callback($bot);
});

$bot->onMessage(function (Nutgram $bot) {
    Dispatcher::message($bot);
});

// ---- Xatolar -----------------------------------------------------------------------------------

$bot->onException(function (Nutgram $bot, Throwable $e) {
    report($e);

    try {
        $bot->sendMessage(__('bot.errors.generic'));
    } catch (Throwable) {
        // e'tibor bermaymiz
    }
});
