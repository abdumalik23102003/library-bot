# library-bot — loyiha qoidalari

Telegram fayl kutubxonasi boti: Laravel 13, PHP 8.5, PostgreSQL 18, Nutgram (faqat webhook), Local Bot API, Laravel Sail.
Buyruqlar: `./vendor/bin/sail ...`. Fayllar serverda saqlanmaydi, faqat Telegram `file_id`.

## Qoidalar
- Faqat o'zbek tili (lotin). Barcha matnlar `lang/uz/*.php` da, HTML parse_mode, foydalanuvchi matni `e()` bilan.
- Soft delete: users, materials, categories, classrooms.
- Navigatsiya: har foydalanuvchiga bitta "anchor" xabar; `Navigator::go/replace/back/start/refresh`.
- callback_data: `{ekran}:{amal}:{arg1}:{arg2}`, `b` = orqaga, 64 bayt ichida.
- Yangi ekran: `app/Telegram/Screens/*Screen.php` + `ScreenRegistry::MAP` ga kichik lotin harfli kalit + kerak bo'lsa `MainMenu::ITEMS` va `BY_ROLE`.
- Material ko'rinishi faqat `Material::visibleTo($user)` orqali; ustoz-o'quvchi/protected mantiqi faqat `TeacherLinks` da.
- Sahifalash: `Pager::slice` (count() da ORDER BY bo'lmasligi kerak, Postgres xato beradi).
- Maxfiy kalitlar (`TELEGRAM_TOKEN`, `TELEGRAM_API_HASH`) hech qachon gitga tushmaydi.
- To'liq texnik topshiriq: TZ v2.1.
