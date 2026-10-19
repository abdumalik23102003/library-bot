<?php

use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

// Telegram webhook: POST /api/webhook (api guruhida CSRF yo'q)
Route::post('/webhook', function (Nutgram $bot) {
    $bot->run();

    return response()->noContent();
});
