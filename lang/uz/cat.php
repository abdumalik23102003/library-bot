<?php

return [
    'list' => [
        'title' => '📂 <b>Kategoriyalar</b>',
        'defaults' => "Standart (hamma ustozda bor): :names",
        'own' => "Mening kategoriyalarim (:count/:max):",
        'empty' => "Sizda hali o'z kategoriyangiz yo'q. Pastdagi tugma orqali yarating.",
        'new' => '➕ Yangi kategoriya',
    ],

    'card' => [
        'description' => 'Tavsif',
        'access' => 'Standart kirish',
        'materials' => 'Materiallar soni',
        'btn_name' => '✏️ Nomi',
        'btn_description' => '✏️ Tavsif',
        'btn_access' => '🔐 Kirish huquqi',
        'btn_delete' => "🗑 O'chirish",
    ],

    'flash' => [
        'created' => '✅ Kategoriya yaratildi.',
        'saved' => '✅ Saqlandi.',
    ],

    'ask' => [
        'name' => "✏️ Kategoriya nomini yozing (2–50 belgi):",
        'description' => "📝 Tavsifni yozing (3–300 belgi). Tavsifni tozalash uchun <code>-</code> yuboring:",
    ],

    'access_pick' => "🔐 <b>Kirish huquqi</b>\n\nBu kategoriyadagi yangi materiallar uchun standart kirish huquqini tanlang:",

    'delete_confirm' => "🗑 <b>Kategoriyani o'chirasizmi?</b>\n\n«:name»\nIchidagi materiallar (:count ta) o'chmaydi, kategoriyasiz qoladi.",
    'btn_delete_yes' => "🗑 Ha, o'chirish",
    'btn_delete_no' => "✖️ Yo'q",

    'new' => [
        'title' => '➕ <b>Yangi kategoriya</b>',
        'step_name' => 'Qadam 1/3',
        'step_description' => 'Qadam 2/3',
        'step_access' => 'Qadam 3/3',
        'name' => 'Nomi',
        'description' => 'Tavsif',
        'skip' => "⏭ O'tkazib yuborish",
        'ask_description' => "📝 Tavsifni yozing (3–300 belgi) yoki o'tkazib yuboring:",
        'ask_access' => "🔐 Bu kategoriya uchun standart kirish huquqini tanlang:",
    ],

    'err' => [
        'name' => "Nom 2 dan 50 tagacha belgi bo'lishi kerak.",
        'exists' => "Bunday nomli kategoriya allaqachon bor.",
        'description' => "Tavsif 3 dan 300 tagacha belgi bo'lishi kerak.",
        'limit' => "Kategoriyalar soni chegaraga yetdi (:max ta). Keraksizini o'chiring.",
    ],

    'not_found' => "ℹ️ Kategoriya topilmadi.",
];
