<?php

return [
    // Admin Telegram ID (ENV orqali, botda admin yaratilmaydi)
    'admin_telegram_id' => (int) env('ADMIN_TELEGRAM_ID'),

    // Yuklanadigan fayl hajmi chegarasi (MB). Local Bot API bilan 2000 MB gacha.
    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 2000),
];
