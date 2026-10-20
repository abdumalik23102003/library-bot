<?php

return [
    'menu' => '🏫 Sinflar',
    'btn_share' => "📤 O'quvchilarga yuborish",

    'err' => [
        'forbidden' => "Bu bo'lim faqat tasdiqlangan ustozlar uchun.",
        'not_found' => 'ℹ️ Sinf topilmadi.',
        'name' => "Nom 2 dan 50 tagacha belgi bo'lishi kerak.",
        'exists' => 'Bunday nomli sinfingiz allaqachon bor.',
        'description' => "Tavsif 3 dan 300 tagacha belgi bo'lishi kerak.",
        'limit' => "Sinflar soni chegaraga yetdi (:max ta). Keraksizini o'chiring.",
    ],

    'list' => [
        'title' => "🏫 <b>Sinflarim</b>\n:total ta · :page/:pages-sahifa",
        'empty' => "🏫 <b>Sinflar</b>\n\nSizda hali sinf yo'q. Sinf yarating, o'quvchilaringizni unga qo'shing — keyin materialni butun sinfga bir bosishda yuborasiz.",
        'new' => '➕ Yangi sinf',
        'members' => ":n ta o'quvchi",
        'pick_hint' => 'Sinfni ochish uchun raqamini bosing:',
    ],

    'ask' => [
        'name' => '✏️ Sinf nomini yozing (2–50 belgi):',
        'description' => '📝 Tavsifni yozing (3–300 belgi). Tavsifni tozalash uchun <code>-</code> yuboring:',
    ],

    'card' => [
        'members' => "👥 O'quvchilar: :n",
        'description' => '📝 :value',
    ],

    'btn' => [
        'members' => "👥 A'zolar (:n)",
        'name' => '✏️ Nomi',
        'description' => '✏️ Tavsif',
        'delete' => "🗑 O'chirish",
        'delete_yes' => "🗑 Ha, o'chirish",
        'delete_no' => "✖️ Yo'q",
        'done' => '✅ Tayyor',
    ],

    'flash' => [
        'created' => "✅ Sinf yaratildi. Endi «A'zolar» orqali o'quvchilarni qo'shing.",
        'saved' => '✅ Saqlandi.',
    ],

    'delete_confirm' => "🗑 <b>Sinfni o'chirasizmi?</b>\n\n«:name»\nO'quvchilar o'chmaydi, faqat sinf yo'qoladi.",

    'members' => [
        'title' => "👥 <b>:name</b> — a'zolar\n:total ta o'quvchi · :page/:pages-sahifa\n\nQo'shish yoki olib tashlash uchun o'quvchini bosing (✅ = sinfda):",
        'empty' => "Sizda tasdiqlangan o'quvchi yo'q. Avval o'quvchilarning qo'shilish so'rovlarini qabul qiling.",
    ],

    'snd' => [
        'title' => "📤 <b>Yuborish</b>\n📌 :title",
        'pick_target' => "Kimga yuboramiz? (tasdiqlangan o'quvchilaringiz: :n ta)",
        'no_students' => "Sizda tasdiqlangan o'quvchi yo'q. Avval o'quvchilarning qo'shilish so'rovlarini qabul qiling.",
        'no_classes' => "Sizda hali sinf yo'q. «🏫 Sinflar» bo'limida sinf yarating.",
        'btn_one' => "👤 Bitta o'quvchiga",
        'btn_cls' => '🏫 Sinfga',
        'btn_all' => "👥 Barcha o'quvchilarga",
        'btn_targets' => '🔙 Qabul qiluvchini tanlash',
        'btn_go' => '✅ Yuborish',
        'btn_back' => '✖️ Bekor qilish',
        'pick_student' => "O'quvchini tanlang (:total ta · :page/:pages-sahifa):",
        'pick_class' => 'Sinfni tanlang (:total ta · :page/:pages-sahifa):',
        'target_cls' => '«:name» sinfi',
        'target_all' => "barcha o'quvchilaringiz",
        'confirm' => "🎯 Kimga: :target\n👥 Soni: :n ta\n\nYuboramizmi?",
        'empty_target' => "Bu qabul qiluvchida yuboriladigan o'quvchi yo'q.",
        'queued' => "⏳ Navbatga qo'yildi (:n ta o'quvchi).\nYuborish tugagach hisobot keladi — kutib o'tirishingiz shart emas.",
        'note_protected' => "🔐 Material himoyalangan: u faqat chatga yuboriladi, o'quvchi ochishi uchun kirish shart emas. Kirish ham berilmaydi.",
        'note_private' => "🔒 Material shaxsiy (private): u faqat chatga yuboriladi. O'quvchiga kirish berilmaydi va qidiruvda ko'rinmaydi.",
        'err_material' => 'ℹ️ Material topilmadi.',
    ],

    'share' => [
        'report' => "📤 <b>Yuborish tugadi</b>\n\n📌 :title\n✅ Yuborildi: :sent\n⚠️ Yuborilmadi: :failed\n⏭ O'tkazib yuborildi: :skipped",
        'failed_list' => "Yuborilmadi (ehtimol, botni bloklagan): :names",
        'and_more' => 'va yana :n ta',
        'aborted' => "⚠️ Yuborish bekor qilindi: material yoki ruxsat o'zgargan.",
        'crashed' => "⚠️ Yuborishda xatolik yuz berdi. Iltimos, keyinroq qaytadan urinib ko'ring.",
    ],
];
