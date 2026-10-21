<?php

return [
    'menu' => '🛠 Boshqaruv',
    'system' => 'Tizim',

    'err' => [
        'forbidden' => "⛔ Bu bo'lim faqat admin va moderatorlar uchun.",
        'not_found' => "ℹ️ Foydalanuvchi topilmadi yoki unga ruxsatingiz yo'q.",
    ],

    'panel' => [
        'title' => "🛠 <b>Boshqaruv paneli</b>\n\n👥 <b>Foydalanuvchilar</b>\n🎓 Ustozlar: <b>:teachers</b>\n🎒 O'quvchilar: <b>:students</b>\n🛡 Moderatorlar: <b>:moderators</b>\n🚫 Bloklanganlar: <b>:blocked</b>\n\n📚 <b>Materiallar:</b> :materials (🌐 :public · 🔐 :protected · 🔒 :private)\n📂 Standart kategoriyalar: <b>:categories</b>\n\n⏳ Kutilayotgan ustoz arizalari: <b>:pending</b>",
        'btn_apps' => '📝 Arizalar (:n)',
        'btn_users' => '👥 Foydalanuvchilar',
        'btn_mats' => '📚 Materiallar',
        'btn_mods' => '🛡 Moderatorlar',
    ],

    'apps' => [
        'title' => '📝 <b>Ustoz arizalari</b> — :total ta (sahifa :page/:pages)',
        'empty' => "📝 <b>Ustoz arizalari</b>\n\nKutilayotgan ariza yo'q. ✅",
        'pick_hint' => "👇 Raqamni bosing — ariza ochiladi.",
    ],

    'app' => [
        'approved' => '✅ Ariza tasdiqlandi. Ustozga xabar yuborildi.',
        'rejected' => '❌ Ariza rad etildi. Ustozga xabar yuborildi.',
        'already' => "ℹ️ Bu ariza allaqachon ko'rib chiqilgan.",
        'not_found' => '⚠️ Ariza topilmadi.',
        'btn_approve' => '✅ Tasdiqlash',
        'btn_reject' => '❌ Rad etish',
    ],

    'usrs' => [
        'title' => '👥 <b>Foydalanuvchilar</b> — :total ta (sahifa :page/:pages)',
        'title_q' => '👥 <b>Foydalanuvchilar</b> — «:q» bo\'yicha :total ta (sahifa :page/:pages)',
        'empty' => "👥 <b>Foydalanuvchilar</b>\n\nBu bo'limda hech kim topilmadi.",
        'pick_hint' => '👇 Raqamni bosing — foydalanuvchi kartochkasi ochiladi.',
        'ask_q' => "🔎 Ism, username, telefon yoki Telegram ID bo'yicha qidirish uchun yozing.\n\nBekor qilish uchun pastdagi tugmani bosing.",
        'btn_search' => '🔎 Qidirish',
        'btn_clear' => '✖️ Tozalash',
        'filter' => [
            'all' => 'Hammasi',
            'teacher' => '🎓 Ustoz',
            'student' => "🎒 O'quvchi",
            'mod' => '🛡 Moderator',
            'blocked' => '🚫 Bloklangan',
        ],
    ],

    'usr' => [
        'card' => "👤 <b>:name</b>\nRol: <b>:role</b>\nHolat: <b>:status</b>\nTelegram: :tg",
        'phone' => 'Telefon: :value',
        'subject' => "Yo'nalish: :value",
        'registered' => "Ro'yxatdan o'tgan: :value",
        'reason' => 'Bloklash sababi: :value',
        'stats_teacher' => "📚 Materiallar: <b>:materials</b> · 👥 O'quvchilar: <b>:students</b>",
        'stats_student' => "🎓 Ustozlari: <b>:teachers</b> · 📥 Yuklab olgan: <b>:downloads</b>",
        'ask_reason' => "🚫 Bloklash sababini yozing (foydalanuvchiga ko'rsatiladi).\n\nSababsiz bloklash uchun «-» yozing.",
        'confirm_delete' => "🗑 <b>:name</b> hisobini o'chirishni tasdiqlaysizmi?\n\nU botdan foydalana olmaydi. Uning materiallari saqlanib qoladi.",
        'btn_block' => '🚫 Bloklash',
        'btn_unblock' => '✅ Blokdan chiqarish',
        'btn_mod' => '🛡 Moderator qilish',
        'btn_unmod' => '↩️ Moderatorlikdan olish',
        'btn_mats' => '📚 Materiallari',
        'btn_delete' => "🗑 O'chirish",
        'btn_delete_yes' => "✅ Ha, o'chirish",
        'btn_delete_no' => '↩️ Yo\'q',
    ],

    'flash' => [
        'blocked' => '🚫 Foydalanuvchi bloklandi.',
        'unblocked' => '✅ Foydalanuvchi blokdan chiqarildi.',
        'mod_added' => '🛡 Foydalanuvchi moderator qilindi.',
        'mod_removed' => '↩️ Moderatorlik olib tashlandi.',
        'invalid' => "ℹ️ Amalni bajarib bo'lmadi.",
    ],

    'notify' => [
        'blocked' => "🚫 Hisobingiz bloklandi.\nSabab: :reason",
        'unblocked' => '✅ Hisobingiz blokdan chiqarildi. Botdan yana foydalanishingiz mumkin.',
        'mod_added' => "🛡 Siz moderator etib tayinlandingiz. Pastdagi menyudan «Boshqaruv» bo'limiga kiring.",
        'mod_removed' => "ℹ️ Moderatorlik vazifangiz olib tashlandi. Endi siz o'quvchi sifatida foydalanasiz.",
    ],

    'amats' => [
        'title' => '📚 <b>Materiallar</b> — :total ta (sahifa :page/:pages)',
        'title_q' => '📚 <b>Materiallar</b> — «:q» bo\'yicha :total ta (sahifa :page/:pages)',
        'title_owner' => '📚 <b>:owner materiallari</b> — :total ta (sahifa :page/:pages)',
        'empty' => "📚 <b>Materiallar</b>\n\nHech narsa topilmadi.",
        'pick_hint' => '👇 Raqamni bosing — material kartochkasi ochiladi.',
        'ask_q' => "🔎 Material sarlavhasi yoki tavsifi bo'yicha qidirish uchun yozing.\n\nBekor qilish uchun pastdagi tugmani bosing.",
        'moderator_note' => "ℹ️ Moderator sifatida faqat ochiq (public) materiallarni ko'rasiz.",
    ],
];
