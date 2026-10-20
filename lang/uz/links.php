<?php

return [
    'menu_teachers' => '👨‍🏫 Ustozlar',
    'menu_students' => "👥 O'quvchilarim",

    'err' => [
        'forbidden' => "ℹ️ Bu bo'lim sizning rolingiz uchun emas.",
        'not_found' => "ℹ️ Topilmadi yoki ruxsat yo'q.",
    ],

    'btn' => [
        'accept' => '✅ Qabul qilish',
        'reject' => '❌ Rad etish',
    ],

    // ---- O'quvchi: ustozlar ro'yxati ------------------------------------------------------
    'tchs' => [
        'title' => '👨‍🏫 <b>Ustozlar</b> — :total ta (sahifa :page/:pages)',
        'title_mine' => '👨‍🏫 <b>Mening ustozlarim</b> — :total ta (sahifa :page/:pages)',
        'empty_all' => "👨‍🏫 <b>Ustozlar</b>\n\nHozircha ustozlar yo'q.",
        'empty_mine' => "👨‍🏫 <b>Mening ustozlarim</b>\n\nSiz hali hech bir ustozga qo'shilmagansiz. «Barchasi» bo'limidan ustozni tanlab, so'rov yuboring.",
        'btn_all' => '📋 Barchasi',
        'btn_mine' => '✅ Mening ustozlarim',
    ],

    // ---- O'quvchi: ustoz kartochkasi --------------------------------------------------------
    'tch' => [
        'card' => "👨‍🏫 <b>:name</b>\n📚 Yo'nalish: :subject\n📝 :bio\n🌐 Ochiq materiallar: :public",
        'status' => [
            'none' => "ℹ️ Siz hali bu ustozga qo'shilmagansiz.",
            'pending' => "⏳ Qo'shilish so'rovingiz ko'rib chiqilmoqda.",
            'approved' => "✅ Siz bu ustozning o'quvchisiz.",
            'rejected' => "❌ Qo'shilish so'rovingiz rad etilgan. Qayta yuborishingiz mumkin.",
            'removed' => "🚫 Siz bu ustozning o'quvchilari ro'yxatidan chiqarilgansiz. So'rovni qayta yuborishingiz mumkin.",
        ],
        'protected' => [
            'none' => "🔐 Himoyalangan materiallarga kirishingiz yo'q.",
            'pending' => "⏳ Himoyalangan kirish so'rovingiz ko'rib chiqilmoqda.",
            'approved' => '🔓 Himoyalangan materiallarga kirishingiz ochiq.',
            'rejected' => "🔐 Himoyalangan kirish so'rovingiz rad etilgan. Qayta so'rashingiz mumkin.",
            'removed' => "🔐 Himoyalangan kirish qaytarib olingan. Qayta so'rashingiz mumkin.",
        ],
        'btn_join' => "📨 Qo'shilish so'rovi",
        'btn_cancel_join' => "❌ So'rovni bekor qilish",
        'btn_pr' => "🔐 Himoyalangan kirish so'rash",
        'btn_cancel_pr' => "❌ Kirish so'rovini bekor qilish",
    ],

    'flash' => [
        'join_sent' => "✅ So'rov yuborildi. Ustoz javobini shu bot orqali xabar qilamiz.",
        'join_pending' => "ℹ️ So'rovingiz allaqachon yuborilgan.",
        'join_approved' => "ℹ️ Siz allaqachon bu ustozning o'quvchisiz.",
        'join_cancelled' => "✅ So'rov bekor qilindi.",
        'pr_sent' => "✅ Himoyalangan kirish so'rovi yuborildi.",
        'pr_pending' => "ℹ️ Kirish so'rovingiz allaqachon yuborilgan.",
        'pr_approved' => 'ℹ️ Sizda allaqachon kirish bor.',
        'pr_cancelled' => '✅ Kirish so\'rovi bekor qilindi.',
        'not_linked' => "ℹ️ Avval ustozga qo'shilishingiz kerak.",
        'invalid' => "ℹ️ Amalni bajarib bo'lmadi.",
        'accepted' => "✅ O'quvchi qabul qilindi.",
        'rejected' => "❌ So'rov rad etildi.",
        'already' => "ℹ️ Bu so'rov allaqachon ko'rib chiqilgan yoki bekor qilingan.",
        'prot_granted' => '🔓 Himoyalangan kirish berildi.',
        'prot_rejected' => '❌ Kirish so\'rovi rad etildi.',
        'revoked' => '🔒 Himoyalangan kirish qaytarib olindi.',
        'removed' => "🗑 O'quvchi ro'yxatdan chiqarildi.",
    ],

    // ---- Ustoz: o'quvchilar ---------------------------------------------------------------------
    'stus' => [
        'title' => "👥 <b>O'quvchilarim</b>\n\n⏳ Qo'shilish so'rovlari: :req\n✅ O'quvchilar: :list\n🔐 Himoyalangan kirish so'rovlari: :prot",
        'btn_req' => "⏳ So'rovlar (:n)",
        'btn_list' => "✅ O'quvchilar (:n)",
        'btn_prot' => "🔐 Himoyalangan so'rovlar (:n)",
    ],

    'stul' => [
        'title_req' => "⏳ <b>Qo'shilish so'rovlari</b> — :total ta (sahifa :page/:pages)",
        'title_list' => "✅ <b>O'quvchilarim</b> — :total ta (sahifa :page/:pages)",
        'title_prot' => "🔐 <b>Himoyalangan kirish so'rovlari</b> — :total ta (sahifa :page/:pages)",
        'empty_req' => "⏳ <b>Qo'shilish so'rovlari</b>\n\nYangi so'rovlar yo'q.",
        'empty_list' => "✅ <b>O'quvchilarim</b>\n\nHali o'quvchilaringiz yo'q.",
        'empty_prot' => "🔐 <b>Himoyalangan kirish so'rovlari</b>\n\nYangi so'rovlar yo'q.",
    ],

    'stu' => [
        'tg' => '🔗 Telegram: :value',
        'phone' => '📱 Telefon: :value',
        'link' => [
            'pending' => "⏳ Qo'shilish so'rovi kutilmoqda",
            'approved' => "✅ Sizning o'quvchingiz",
            'rejected' => "❌ So'rov rad etilgan",
            'removed' => "🚫 Ro'yxatdan chiqarilgan",
        ],
        'protected' => [
            'none' => "🔐 Himoyalangan kirish: yo'q",
            'pending' => "⏳ Himoyalangan kirish so'ralgan",
            'approved' => '🔓 Himoyalangan kirish: ochiq',
            'rejected' => '🔐 Himoyalangan kirish: rad etilgan',
            'removed' => '🔐 Himoyalangan kirish: qaytarib olingan',
        ],
        'btn_grant' => '🔓 Himoyalangan kirish berish',
        'btn_revoke' => '🔒 Kirishni qaytarib olish',
        'btn_remove' => "🗑 O'quvchini chiqarish",
        'btn_remove_yes' => '✅ Ha, chiqarish',
        'btn_remove_no' => "↩️ Yo'q",
        'remove_confirm' => "🗑 <b>O'quvchini ro'yxatdan chiqarasizmi?</b>\n\nU sizning sinflaringizdan va himoyalangan materiallardan ham chiqariladi.",
    ],

    // ---- Bildirishnomalar ---------------------------------------------------------------------------
    'notify' => [
        'join_card' => "📨 <b>Yangi o'quvchi so'rovi</b>\n\n👤 <b>:name</b>\n🔗 :tg",
        'protected_card' => "🔐 <b>Himoyalangan materiallarga kirish so'rovi</b>\n\n👤 <b>:name</b>\n🔗 :tg",
        'done_accept' => '✅ Qabul qilindi.',
        'done_reject' => '❌ Rad etildi.',
        'already' => "ℹ️ Bu so'rov allaqachon ko'rib chiqilgan yoki bekor qilingan.",
        'join_accepted' => "✅ <b>:teacher</b> so'rovingizni qabul qildi. Endi siz uning o'quvchisisiz.",
        'join_rejected' => "❌ <b>:teacher</b> qo'shilish so'rovingizni rad etdi.",
        'removed' => "🚫 <b>:teacher</b> sizni o'quvchilar ro'yxatidan chiqardi.",
        'protected_granted' => '🔓 <b>:teacher</b> sizga himoyalangan materiallarga kirish berdi.',
        'protected_rejected' => "❌ <b>:teacher</b> himoyalangan kirish so'rovingizni rad etdi.",
        'protected_revoked' => '🔒 <b>:teacher</b> himoyalangan materiallarga kirishingizni qaytarib oldi.',
    ],
];
