# library-bot

Telegram fayl kutubxonasi boti: ustozlar material yuklaydi, o'quvchilar qidirib oladi, ustoz materialni o'z o'quvchilariga yoki sinfiga yuboradi.

## Texnologiyalar

Laravel 13, PHP 8.5, PostgreSQL 18, Nutgram (faqat webhook), Local Telegram Bot API (2000 MB gacha fayl), Laravel Sail.
Fayllar serverda saqlanmaydi: faqat Telegram `file_id` saqlanadi. Agar bot tokeni almashsa yoki bot o'chirilsa, eski `file_id` lar yaroqsiz bo'ladi.

## Rollar

- **Admin** (ENV: `ADMIN_TELEGRAM_ID`): hamma narsa, sozlamalar, audit, broadcast.
- **Moderator**: foydalanuvchilar, public materiallar, standart kategoriyalar, ustoz arizalari.
- **Ustoz** (admin/moderator tasdiqlagandan keyin): materiallar, kategoriyalar, o'quvchilar, sinflar, material yuborish.
- **O'quvchi**: qidirish, ustozga qo'shilish, yuklab olish.

## Material ko'rinishi

- `public`: hamma ochadi.
- `protected`: o'quvchi qidiruvda ko'radi (🔒), ochish uchun ustozdan kirish kerak.
- `private`: faqat egasi.
- Ustoz istalgan o'z materialini (private ham) o'quvchiga, sinfga yoki hammaga yubora oladi. Yuborish kirish bermaydi.

## Ishga tushirish

    cp .env.example .env
    # .env da: TELEGRAM_TOKEN, TELEGRAM_API_ID, TELEGRAM_API_HASH, ADMIN_TELEGRAM_ID
    ./vendor/bin/sail up -d
    ./vendor/bin/sail artisan key:generate
    ./vendor/bin/sail artisan migrate --seed

Webhook manzili: `POST /api/webhook`.

## Navbat ishchisi

Katta yuborishlar (10 kishidan ko'p) navbat orqali ishlaydi. Alohida terminalda ishga tushirib qo'ying:

    ./vendor/bin/sail artisan queue:work --tries=1 -v

Kodni o'zgartirgandan keyin: `./vendor/bin/sail artisan queue:restart`.
Xatolar uchun: `storage/logs/laravel.log` va `./vendor/bin/sail artisan queue:failed`.

## Profil

Foydalanuvchi profilini o'zi o'chirishi mumkin (Profil bo'limi). Admin ham o'chira oladi. Keyin `/start` bosib qaytadan ro'yxatdan o'tiladi.

## Testlar

    ./vendor/bin/sail artisan test

## Tuzilma

- `app/Telegram/Screens`: ekranlar (`ScreenRegistry` da ro'yxatdan o'tadi)
- `app/Telegram/Navigation`: navigatsiya yadrosi
- `app/Telegram/Support`: biznes qoidalari (`TeacherLinks`, `UserAdmin`, `Messaging` va boshqalar)
- `lang/uz`: barcha matnlar
