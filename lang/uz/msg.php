<?php

return [
    'menu' => '✉️ Murojaat',
    'staff_label' => 'administratsiya',

    'btn' => [
        'reply' => '↩️ Javob berish',
        'block' => '🚫 Bloklash',
        'write' => '✉️ Xabar yozish',
        'write_actor' => '✉️ Shu kishiga xabar yozish',
    ],

    'card' => [
        'title' => "✉️ <b>Xabar #:id</b>\n👤 :name · :role",
        'reply_to' => '↩️ #:id xabarga javob',
    ],

    'compose' => [
        'title' => "✉️ <b>Xabar yozish</b>\nKimga: <b>:to</b>",
        'quote' => "↩️ <b>#:id</b> xabarga javob:\n«:text»",
        'quote_file' => '📎 fayl',
        'ask' => "Xabaringizni yozing yoki fayl yuboring (rasm, hujjat, video, audio, ovozli xabar).\nMatn :max belgigacha.",
    ],

    'sent' => [
        'ok' => '✅ Xabar yuborildi: :to.',
        'partial' => "⚠️ :n ta qabul qiluvchiga yetkazib bo'lmadi.",
        'fail' => "⚠️ Xabarni yetkazib bo'lmadi. Qabul qiluvchi botni to'xtatgan bo'lishi mumkin.",
    ],

    'err' => [
        'cannot' => "ℹ️ Bu xabarni yozib bo'lmaydi yoki qabul qiluvchi topilmadi.",
        'cannot_block' => "ℹ️ Bu foydalanuvchini bloklab bo'lmaydi.",
        'empty' => "Xabar bo'sh bo'lmasligi kerak.",
        'too_long' => 'Xabar juda uzun (:max belgidan oshmasin).',
        'rate' => "Siz juda ko'p xabar yubordingiz. Taxminan :min daqiqadan keyin qayta urinib ko'ring.",
        'file_type' => "Bu fayl turini yuborib bo'lmaydi. Rasm, hujjat, video, audio yoki ovozli xabar yuboring.",
    ],

    'bc' => [
        'btn_panel' => '📢 Broadcast',
        'forbidden' => "⛔ Bu bo'lim faqat admin uchun.",
        'header' => "📢 <b>Administratsiyadan xabar</b>\n\n",
        'audience' => "📢 <b>Broadcast</b>\n\nKimga yuboramiz?\n\n👥 Hammaga: <b>:all</b>\n🎓 Ustozlarga: <b>:teacher</b>\n🎒 O'quvchilarga: <b>:student</b>\n🛡 Moderatorlarga: <b>:moderator</b>\n\nFaqat faol foydalanuvchilarga yuboriladi.",
        'aud' => [
            'all' => '👥 Hammaga',
            'teacher' => '🎓 Ustozlarga',
            'student' => "🎒 O'quvchilarga",
            'moderator' => '🛡 Moderatorlarga',
        ],
        'compose' => "📢 <b>Broadcast</b> · :aud (:n ta)\n\nXabar matnini yozing yoki fayl yuboring (rasm, hujjat, video, audio, ovozli xabar). Fayl bilan birga sarlavha ham yozishingiz mumkin.\nMatn :max belgigacha.",
        'confirm' => "📢 <b>Yuborishni tasdiqlang</b>\n\n🎯 Kimga: <b>:aud</b>\n👥 Soni: <b>:n ta</b>",
        'file_line' => '📎 Fayl: :type',
        'no_text' => '(matnsiz)',
        'empty' => "⚠️ Bu guruhda yuboriladigan foydalanuvchi yo'q.",
        'btn_go' => '✅ Yuborish (:n)',
        'btn_edit' => '✏️ Qayta yozish',
        'queued' => "⏳ Navbatga qo'yildi (:n ta foydalanuvchi).\nYuborish tugagach hisobot keladi — kutib o'tirishingiz shart emas.",
        'report' => "📢 <b>Broadcast tugadi</b>\n\n🎯 :aud\n✅ Yuborildi: :sent\n⚠️ Yuborilmadi: :failed (botni to'xtatganlar)",
        'crashed' => "⚠️ Broadcast yuborishda xatolik yuz berdi. Loglarni tekshiring.",
    ],
];
