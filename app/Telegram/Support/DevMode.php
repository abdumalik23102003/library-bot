<?php

namespace App\Telegram\Support;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Navigation\Navigator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

/**
 * [VAQTINCHALIK, faqat lokal] ENV dagi admin bitta akkaunt bilan ro'yxatdan o'tish va tasdiqlash oqimlarini sinashi uchun.
 */
final class DevMode
{
    /** Faqat lokal muhitda va faqat ENV admin uchun. */
    public static function allowed(int $telegramId): bool
    {
        $adminId = (int) config('library.admin_telegram_id');

        return app()->isLocal() && $adminId > 0 && $adminId === $telegramId;
    }

    public static function active(int $telegramId): bool
    {
        return self::allowed($telegramId) && Cache::has("tg:{$telegramId}:dev");
    }

    /** Ariza kartochkasi oluvchilari: admin/moderatorlar (+ test rejimidagi o'zi). */
    public static function recipients(User $teacher): Collection
    {
        $staff = User::query()->ofRole(Role::Admin, Role::Moderator)->active()->get();

        // ENV dagi admin har doim oluvchi (test rejimida roli vaqtincha o'zgargan bo'lsa ham).
        $adminId = (int) config('library.admin_telegram_id');
        $envAdmin = $adminId > 0 ? User::query()->where('telegram_id', $adminId)->first() : null;

        if ($envAdmin !== null && ! $envAdmin->isBlocked() && ! $staff->contains('id', $envAdmin->id)) {
            $staff->push($envAdmin);
        }

        if (self::active($teacher->telegram_id) && ! $staff->contains('id', $teacher->id)) {
            $staff->push($teacher);
        }

        return $staff;
    }

    /** Foydalanuvchini "yangi" holatga qaytaradi va ro'yxatdan o'tishni boshlaydi. */
    public static function enter(Nutgram $bot, ?Role $presetRole): void
    {
        $user = CurrentUser::get();

        if (! self::allowed($user->telegram_id)) {
            $bot->sendMessage('Bu buyruq faqat lokal muhitda, ENV dagi admin uchun ishlaydi.');

            return;
        }

        Cache::put("tg:{$user->telegram_id}:dev", true, now()->addDays(30));

        $user->forceFill([
            'role' => Role::Student,
            'status' => UserStatus::Pending,
            'full_name' => null,
            'subject' => null,
            'phone' => null,
            'bio' => null,
            'approved_by' => null,
            'approved_at' => null,
            'registered_at' => null,
        ])->save();

        $bot->sendMessage(
            text: "🧪 Test rejimi yoqildi. Siz yangi foydalanuvchisiz.\nAdmin holatiga qaytish: /dev_admin",
            reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
        );

        Navigator::start($bot, 'regrole');
    }

    public static function exit(Nutgram $bot): void
    {
        $user = CurrentUser::get();

        if (! self::allowed($user->telegram_id)) {
            $bot->sendMessage('Bu buyruq faqat lokal muhitda, ENV dagi admin uchun ishlaydi.');

            return;
        }

        Cache::forget("tg:{$user->telegram_id}:dev");

        $user->forceFill([
            'role' => Role::Admin,
            'status' => UserStatus::Active,
            'registered_at' => $user->registered_at ?? now(),
            'full_name' => $user->full_name ?: 'Admin',
        ])->save();

        $bot->sendMessage(text: '🧪 Test rejimi o\'chirildi. Siz yana adminsiz.', reply_markup: MainMenu::keyboard());
        Navigator::start($bot, 'home');
    }
    /** Test rejimida tayyor rol bilan ishga tushadi (masalan, moderator yoki tasdiqlangan ustoz menyusini ko'rish uchun). */
    public static function asRole(Nutgram $bot, Role $role): void
    {
        $user = CurrentUser::get();

        if (! self::allowed($user->telegram_id)) {
            $bot->sendMessage('Bu buyruq faqat lokal muhitda, ENV dagi admin uchun ishlaydi.');

            return;
        }

        Cache::put("tg:{$user->telegram_id}:dev", true, now()->addDays(30));

        $user->forceFill([
            'role' => $role,
            'status' => UserStatus::Active,
            'registered_at' => $user->registered_at ?? now(),
            'full_name' => $user->full_name ?: 'Test ' . $role->label(),
        ]);

        if ($role === Role::Teacher) {
            $user->subject ??= 'Matematika';
            $user->bio ??= 'Test ustoz haqida ma\'lumot';
            $user->phone ??= '+998901234567';
        }

        $user->save();

        $bot->sendMessage(text: "🧪 Test rejimi: rol - {$role->label()}.\nAdmin holatiga qaytish: /dev_admin", reply_markup: MainMenu::keyboard($user));
        Navigator::start($bot, 'home');
    }
}
