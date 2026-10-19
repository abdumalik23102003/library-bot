<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Navigation\Dispatcher;
use App\Telegram\Navigation\Navigator;
use SergiX44\Nutgram\Nutgram;

// ---- Buyruqlar ----------------------------------------------------------------------------

$bot->onCommand('start', function (Nutgram $bot) {
    // Doimiy (reply) menyu alohida xabar bilan o'rnatiladi, ekranlar esa anchor xabarda.
    $bot->sendMessage(text: __('bot.start.welcome'), reply_markup: MainMenu::keyboard());

    Navigator::start($bot, 'home');
});

$bot->onCommand('ping', function (Nutgram $bot) {
    $bot->sendMessage('pong 🏓');
});

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
