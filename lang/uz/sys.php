<?php

return [
    'btn_audit' => '📜 Audit',
    'btn_settings' => '⚙️ Sozlamalar',
    'forbidden' => "⛔ Bu bo'lim faqat admin uchun.",

    'audit' => [
        'title' => '📜 <b>Audit jurnali</b> — :total ta (sahifa :page/:pages)',
        'empty' => "📜 <b>Audit jurnali</b>\n\nHali yozuv yo'q.",
        'system' => 'tizim',
        'actions' => [
            'teacher_approved' => '✅ ustoz arizasini tasdiqladi',
            'teacher_rejected' => '❌ ustoz arizasini rad etdi',
            'material_updated' => '✏️ materialni yangiladi',
            'material_deleted' => "🗑 materialni o'chirdi",
            'user_blocked' => '🚫 foydalanuvchini blokladi',
            'user_unblocked' => '✅ blokdan chiqardi',
            'user_moderator_added' => '🛡 moderator qildi',
            'user_moderator_removed' => '↩️ moderatorlikdan oldi',
            'user_deleted' => "🗑 foydalanuvchini o'chirdi",
            'category_created' => '➕ standart kategoriya yaratdi',
            'category_updated' => '✏️ standart kategoriyani yangiladi',
            'category_deleted' => "🗑 standart kategoriyani o'chirdi",
            'setting_updated' => "⚙️ sozlamani o'zgartirdi",
        ],
    ],

    'set' => [
        'title' => "⚙️ <b>Sozlamalar</b>\n\n📦 Maksimal yuklash hajmi: <b>:value MB</b> (:source)\n\nLocal Bot API bilan 2000 MB gacha yuklash mumkin.",
        'source_db' => 'admin sozlamasi',
        'source_env' => 'standart (.env)',
        'ask' => "✏️ Maksimal yuklash hajmini MB da yozing (1 dan :max gacha). Masalan: <code>500</code>",
        'err' => "Faqat 1 dan :max gacha butun son yozing.",
        'btn_change' => "✏️ O'zgartirish",
        'btn_reset' => '↩️ Standartga qaytarish',
        'saved' => '✅ Saqlandi.',
        'reset' => '✅ Standart qiymatga qaytarildi.',
    ],
];
