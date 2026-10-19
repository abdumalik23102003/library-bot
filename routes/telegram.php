<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use SergiX44\Nutgram\Nutgram;

$bot->onCommand('start', function (Nutgram $bot) {
    $isAdmin = $bot->userId() === config('library.admin_telegram_id');

    $bot->sendMessage(
        text: "Salom! Bot ishlayapti ✅\n"
            . "Sizning ID: {$bot->userId()}\n"
            . 'Rol: ' . ($isAdmin ? 'ADMIN' : 'oddiy foydalanuvchi')
    );
});

$bot->onCommand('ping', function (Nutgram $bot) {
    $bot->sendMessage('pong 🏓');
});
