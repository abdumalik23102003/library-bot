<?php

return [
    'menu' => '✉️ Xabarlar',
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

    'hub' => [
        'title' => "✉️ <b>Xabarlar</b>\n\nKimgadir yozing, kelgan xabarlarni ko'ring yoki javob bering.\nO'qilmagan xabarlar: <b>:n</b>",
        'btn_write' => '✍️ Xabar yozish',
        'btn_inbox' => '📥 Kelganlar (:n)',
    ],

    'wr' => [
        'pick_title' => "✍️ <b>Kimga yozamiz?</b>\nTanlandi: <b>:n</b> ta\nBir nechtasini belgilash mumkin (✅ / ⬜).",
        'searching' => '🔎 Qidiruv: «:q»',
        'page_info' => "\nJami: :total · :page/:pages-sahifa",
        'empty' => "Hozircha yoza oladigan odam yo'q.",
        'none_found' => 'Hech kim topilmadi.',
        'f' => [
            'all' => '👥 Hammasi',
            'staff' => '🛡 Administratsiya',
            'teacher' => '🎓 Ustozlar',
            'student' => "🎒 O'quvchilar",
        ],
        'btn_all' => '☑️ Hammasini belgilash (:n)',
        'btn_none' => '🧹 Tozalash',
        'btn_search' => '🔎 Qidirish',
        'btn_search_clear' => '✖️ Qidiruvni olib tashlash',
        'btn_admin' => '🛡 Administratsiyaga yozish',
        'btn_next' => '➡️ Davom etish (:n)',
        'btn_people' => '👥 Qabul qiluvchilar',
        'btn_edit' => '✏️ Qayta yozish',
        'btn_send' => '✅ Yuborish (:n)',
        'btn_again' => '✉️ Yana yozish',
        'search_ask' => "🔎 Ism yoki username bo'yicha qidiruv matnini yozing.",
        'compose_title' => "✉️ <b>Xabar yozish</b>\nKimga: <b>:to</b>",
        'compose_ask' => "Xabaringizni yozing yoki fayl yuboring (rasm, hujjat, video, audio, ovozli xabar).\nMatn :max belgigacha.",
        'quote' => "↩️ <b>#:id</b> xabarga javob:\n«:text»",
        'quote_file' => '📎 fayl',
        'confirm_title' => "✉️ <b>Yuborishni tasdiqlang</b>\n\n🎯 Kimga: <b>:to</b>\n👥 Soni: <b>:n ta</b>",
        'queue_note' => "⏳ Qabul qiluvchilar ko'p: xabar navbat orqali yuboriladi, oxirida hisobot keladi.",
        'no_recipients' => '⚠️ Qabul qiluvchi qolmadi. Qabul qiluvchilarni qayta tanlang.',
        'label_more' => ':names va yana :n ta',
        'queued' => "⏳ Navbatga qo'yildi (:n ta qabul qiluvchi).\nYuborish tugagach hisobot keladi.",
        'report' => "✉️ <b>Xabar yuborish tugadi</b>\n\n✅ Yuborildi: :sent\n⚠️ Yuborilmadi: :failed",
        'crashed' => "⚠️ Xabarlarni yuborishda xatolik yuz berdi.",
    ],

    'inb' => [
        'title' => "📥 <b>Kelgan xabarlar</b>\nJami: :total · :page/:pages-sahifa\n🔵 o'qilmagan · ⚪ o'qilgan\n\nXabarni ochish uchun bosing.",
        'empty' => "📥 Hozircha xabar yo'q.",
        'empty_unread' => "📥 O'qilmagan xabar yo'q.",
        'f_all' => 'Hammasi',
        'f_unread' => "O'qilmagan (:n)",
        'btn_read_all' => "✅ Hammasini o'qilgan qilish",
        'deleted' => "(o'chirilgan foydalanuvchi)",
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
        'forbidden' => "⛔ Bu bo'lim faqat admin va moderatorlar uchun.",
        'header' => "📢 <b>Administratsiyadan xabar</b>\n\n",
        'audience' => "📢 <b>Broadcast</b>\n\nKimga yuboramiz?\n",
        'count_line' => ':label: <b>:n</b>',
        'audience_note' => 'Faqat faol foydalanuvchilarga yuboriladi.',
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
        'sent_now' => "✅ Yuborildi (:n ta foydalanuvchi). Hisobot alohida xabar bilan keladi.",
        'reason' => 'ℹ️ Xato sababi: :reason',
    ],
];
