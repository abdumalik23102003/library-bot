<?php

return [
    'menu' => '📚 Materiallarim',
    'untitled' => 'Nomsiz',

    'list' => [
        'title' => '📚 <b>Materiallarim</b> — :total ta (sahifa :page/:pages)',
        'empty' => "📚 <b>Materiallarim</b>\n\nHali material yuklamagansiz. Pastdagi menyudan yuklashni boshlang.",
    ],

    'card' => [
        'category' => '🗂 Kategoriya: :value',
        'access' => '🔐 Ruxsat: :value',
        'size' => '📦 Hajm: :value',
        'downloads' => '📥 Yuklab olingan: :value marta',
        'updated' => '🕒 Yangilangan: :value',
        'url' => '🔗 :value',
        'desc' => '📝 :value',
        'body' => '💬 :value',
    ],

    'btn' => [
        'send' => '📥 Faylni olish',
        'title' => '✏️ Sarlavha',
        'description' => '📝 Tavsif',
        'category' => '🗂 Kategoriya',
        'access' => '🔐 Ruxsat',
        'replace_file' => '🔄 Faylni almashtirish',
        'replace_url' => "🔗 Havolani o'zgartirish",
        'replace_body' => "📝 Matnni o'zgartirish",
        'delete' => "🗑 O'chirish",
        'delete_yes' => "✅ Ha, o'chirish",
        'delete_no' => "↩️ Yo'q",
    ],

    'ask' => [
        'title' => '✏️ Yangi sarlavhani yozing:',
        'description' => '📝 Yangi tavsifni yozing:',
        'url' => '🔗 Yangi havolani yozing (https:// bilan boshlansin):',
        'body' => '📝 Yangi matnni yozing:',
        'file' => '🔄 Yangi faylni yuboring (hujjat, video, rasm yoki audio). Maksimal hajm: :max MB. Eski fayl shu bilan almashtiriladi.',
    ],

    'pick' => [
        'category' => '🗂 Yangi kategoriyani tanlang:',
        'access' => '🔐 Ruxsat turini tanlang:',
    ],

    'delete_confirm' => "🗑 <b>Materialni o'chirasizmi?</b>\n\nBu amalni qaytarib bo'lmaydi.",

    'flash' => [
        'saved' => '✅ Saqlandi.',
        'file' => '✅ Fayl almashtirildi.',
    ],

    'err' => [
        'not_found' => "ℹ️ Material topilmadi yoki unga ruxsatingiz yo'q.",
        'title' => "Sarlavha 2 dan 100 tagacha belgi bo'lishi kerak.",
        'description' => "Tavsif 3 dan 600 tagacha belgi bo'lishi kerak.",
        'url' => "Havola noto'g'ri. U http:// yoki https:// bilan boshlanishi kerak.",
        'body' => "Matn 1 dan 4000 tagacha belgi bo'lishi kerak.",
        'file_type' => "Bu turdagi faylni qabul qilib bo'lmaydi. Hujjat, video, rasm yoki audio yuboring.",
        'too_big' => 'Fayl hajmi chegaradan oshdi (maksimal :max MB).',
        'send' => "Faylni yuborib bo'lmadi. Uni almashtirib ko'ring.",
    ],

    'notify' => [
        'text' => "🔔 <b>Materialingiz o'zgartirildi</b>\n:role <b>:actor</b>: :what\n🗂 :category · 🔐 :access",
        'what' => [
            'title' => 'sarlavhani yangiladi',
            'description' => 'tavsifni yangiladi',
            'url' => "havolani o'zgartirdi",
            'body' => "matnni o'zgartirdi",
            'category' => "kategoriyani o'zgartirdi",
            'access' => "ruxsat turini o'zgartirdi",
            'file' => 'faylni yangisiga almashtirdi',
            'deleted' => "materialni o'chirdi",
        ],
    ],
];
