<?php

return [
    'role' => [
        'text' => "👋 <b>Ro'yxatdan o'tish</b>\n\nSiz kimsiz? Rolingizni tanlang:",
        'teacher' => '👨‍🏫 Ustoz',
        'student' => "🎓 O'quvchi",
    ],

    'profile' => [
        'title_teacher' => '👨‍🏫 <b>Ustoz profili</b>',
        'title_student' => "🎓 <b>O'quvchi profili</b>",
        'fill' => "Pastdagi tugmalar orqali ma'lumotlaringizni kiriting:",
        'complete' => "✅ Hammasi to'ldirildi! Yakunlash tugmasini bosing yoki ma'lumotni tahrirlang.",
    ],

    'field' => [
        'full_name' => '👤 F.I.Sh',
        'subject' => "📚 Yo'nalish",
        'phone' => '📱 Telefon',
        'bio' => "📝 Qo'shimcha ma'lumot",
    ],
    'empty' => '<i>kiritilmagan</i>',

    'add' => [
        'full_name' => '➕ F.I.Sh kiritish',
        'subject' => "➕ Yo'nalishni kiritish",
        'phone' => '➕ Telefon kiritish',
        'bio' => "➕ Qo'shimcha ma'lumot",
    ],
    'edit' => [
        'full_name' => '✏️ F.I.Sh',
        'subject' => "✏️ Yo'nalish",
        'phone' => '✏️ Telefon',
        'bio' => "✏️ Ma'lumot",
    ],

    'ask' => [
        'full_name' => '✏️ F.I.Shingizni yozib yuboring (masalan: Alisher Vahobov):',
        'subject' => "📚 Ustozlik yo'nalishingizni yozing (masalan: Matematika):",
        'phone' => '📱 Telefon raqamingizni yozing (masalan: +998901234567) yoki pastdagi tugma orqali ulashing:',
        'bio' => "📝 O'zingiz haqingizda qisqacha yozing (tajriba, ta'lim va h.k.):",
    ],

    'err' => [
        'full_name' => "F.I.Sh faqat harflardan iborat bo'lishi va kamida 3 ta belgi bo'lishi kerak.",
        'subject' => "Yo'nalish 2 dan 100 tagacha belgi bo'lishi kerak.",
        'bio' => "Ma'lumot 5 dan 500 tagacha belgi bo'lishi kerak.",
        'phone' => "Telefon raqam noto'g'ri. Masalan: +998901234567",
        'incomplete' => "Avval barcha ma'lumotlarni to'ldiring.",
    ],

    'btn_finish' => '🚀 Yakunlash',
    'btn_approve' => '✅ Tasdiqlash',
    'btn_reject' => '❌ Rad etish',

    'phone_hint' => '📱 Raqamingizni yozing yoki pastdagi tugma orqali ulashing.',
    'share_phone' => '📱 Raqamni ulashish',
    'phone_not_yours' => "Iltimos, o'zingizning raqamingizni ulashing.",

    'done_student' => "🎉 Ro'yxatdan o'tdingiz! Pastdagi menyudan foydalanishingiz mumkin.",

    'pending' => [
        'text' => "⏳ <b>Arizangiz yuborildi</b>\n\nAdmin yoki moderator ko'rib chiqadi. Natija haqida shu yerda xabar beramiz.",
        'checked' => '🕒 Tekshirildi: :time',
        'btn_check' => '🔄 Holatni tekshirish',
    ],

    'approved' => "🎉 <b>Arizangiz tasdiqlandi!</b>\n\nEndi siz ustozsiz.",
    'rejected' => "😔 <b>Arizangiz rad etildi.</b>\n\nQayta ariza berish yoki o'quvchi sifatida davom etish uchun /start ni bosing. Savollar bo'lsa, admin bilan bog'laning.",
    'menu_ready' => 'Pastdagi menyudan foydalanishingiz mumkin ⬇️',

    'application' => [
        'card' => "👨‍🏫 <b>Yangi ustoz arizasi</b> #:id\n\n👤 F.I.Sh: <b>:name</b>\n📚 Yo'nalish: :subject\n📱 Telefon: :phone\n📝 Ma'lumot: :bio\n🔗 Telegram: :tg",
        'approved_by' => '✅ Tasdiqladi: :actor',
        'rejected_by' => '❌ Rad etdi: :actor',
        'already' => "ℹ️ Bu ariza allaqachon ko'rib chiqilgan (:status).",
        'not_found' => "ℹ️ Ariza topilmadi yoki allaqachon ko'rib chiqilgan.",
    ],

    'blocked' => "🚫 Hisobingiz bloklangan.\nSabab: :reason",
    'blocked_short' => '🚫 Hisobingiz bloklangan.',
    'deleted' => "🚫 Hisobingiz o'chirilgan. Admin bilan bog'laning.",
];
