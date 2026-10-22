<?php

namespace App\Telegram\Support;

use App\Enums\Access;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Download;
use App\Models\Material;
use App\Models\TeacherStudent;
use App\Models\User;
use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Navigation\Navigator;
use Illuminate\Database\Eloquent\Builder;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;
use Throwable;

/**
 * Admin/moderator amallari: kim kimni boshqara oladi, bloklash, moderator tayinlash, ariza qarori, statistika.
 * Holat o'zgarishlari bu yerda (testlanadi), bildirishnomalar alohida notify() da.
 */
final class UserAdmin
{
    /** Admin hamma (o'zidan tashqari), moderator faqat ustoz va o'quvchilarni boshqaradi. */
    public static function can(User $actor, User $target): bool
    {
        if (! $actor->isStaff() || $actor->id === $target->id || $target->isAdmin()) {
            return false;
        }

        return $actor->isAdmin() || ! $target->role->isStaff();
    }

    /** Ro'yxatda ko'rinadigan foydalanuvchilar. */
    public static function visible(User $actor): Builder
    {
        $query = User::query()->whereKeyNot($actor->id)->whereNotNull('registered_at');

        return $actor->isAdmin() ? $query : $query->ofRole(Role::Teacher, Role::Student);
    }

    /** Kutilayotgan ustoz arizalari. */
    public static function pendingApplications(): Builder
    {
        return User::query()
            ->ofRole(Role::Teacher)
            ->where('status', UserStatus::Pending->value)
            ->whereNotNull('registered_at');
    }

    /** Ism, username, telefon yoki Telegram ID bo'yicha qidiruv. */
    public static function search(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $like = '%' . addcslashes(ltrim($term, '@'), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like, $term) {
            $q->where('full_name', 'ilike', $like)
                ->orWhere('username', 'ilike', $like)
                ->orWhere('phone', 'like', $like);

            if (ctype_digit($term) && strlen($term) <= 15) {
                $q->orWhere('telegram_id', (int) $term);
            }
        });
    }

    // ---- Holat o'zgarishlari -----------------------------------------------------------------

    public static function block(User $actor, User $target, string $reason = ''): bool
    {
        if (! self::can($actor, $target) || $target->isBlocked()) {
            return false;
        }

        $target->status = UserStatus::Blocked;
        $target->blocked_reason = $reason !== '' ? $reason : null;
        $target->save();

        AuditLog::record($actor, 'user.blocked', $target, ['reason' => $reason]);

        return true;
    }

    public static function unblock(User $actor, User $target): bool
    {
        if (! self::can($actor, $target) || ! $target->isBlocked()) {
            return false;
        }

        // Ariza tasdiqlanmagan ustoz yoki ro'yxatdan o'tmagan foydalanuvchi "kutilmoqda" holatiga qaytadi.
        $pending = $target->registered_at === null || ($target->isTeacher() && $target->approved_at === null);

        $target->status = $pending ? UserStatus::Pending : UserStatus::Active;
        $target->blocked_reason = null;
        $target->save();

        AuditLog::record($actor, 'user.unblocked', $target);

        return true;
    }

    /** Faqat admin: faol ustoz yoki o'quvchini moderator qiladi. */
    public static function makeModerator(User $actor, User $target): bool
    {
        if (! $actor->isAdmin() || ! self::can($actor, $target)
            || $target->role->isStaff() || ! $target->isActive() || $target->registered_at === null) {
            return false;
        }

        $target->role = Role::Moderator;
        $target->save();

        AuditLog::record($actor, 'user.moderator_added', $target);

        return true;
    }

    public static function removeModerator(User $actor, User $target): bool
    {
        if (! $actor->isAdmin() || ! self::can($actor, $target) || ! $target->isModerator()) {
            return false;
        }

        $target->role = Role::Student;
        $target->save();

        AuditLog::record($actor, 'user.moderator_removed', $target);

        return true;
    }

    public static function delete(User $actor, User $target): bool
    {
        if (! self::can($actor, $target)) {
            return false;
        }

        AuditLog::record($actor, 'user.deleted', $target);
        AccountReset::wipe($target);
        $target->delete();

        return true;
    }

    /** Ustoz arizasi qarori. false = ariza allaqachon ko'rib chiqilgan yoki ustoz emas. */
    public static function decideApplication(User $actor, User $teacher, bool $approve): bool
    {
        if (! $actor->isStaff() || ! $teacher->isTeacher()
            || $teacher->status !== UserStatus::Pending || $teacher->registered_at === null) {
            return false;
        }

        if ($approve) {
            $teacher->status = UserStatus::Active;
            $teacher->approved_by = $actor->id;
            $teacher->approved_at = now();
        } else {
            // Rad etilgan: o'quvchi rolida, ro'yxatdan o'tmagan holatga qaytadi (ma'lumotlari saqlanadi).
            $teacher->role = Role::Student;
            $teacher->status = UserStatus::Pending;
            $teacher->registered_at = null;
        }

        $teacher->save();

        AuditLog::record($actor, $approve ? 'teacher.approved' : 'teacher.rejected', $teacher);

        return true;
    }

    // ---- Statistika ----------------------------------------------------------------------------

    /** @return array<string, int> */
    public static function stats(): array
    {
        $users = fn (Role $role) => User::query()->ofRole($role)->active()->count();

        return [
            'teachers' => $users(Role::Teacher),
            'students' => $users(Role::Student),
            'moderators' => $users(Role::Moderator),
            'blocked' => User::query()->where('status', UserStatus::Blocked->value)->count(),
            'pending' => self::pendingApplications()->count(),
            'materials' => Material::query()->count(),
            'public' => Material::query()->where('access', Access::Public->value)->count(),
            'protected' => Material::query()->where('access', Access::Protected->value)->count(),
            'private' => Material::query()->where('access', Access::Private->value)->count(),
            'categories' => Category::query()->defaults()->count(),
        ];
    }

    /** @return array<string, int> */
    public static function userStats(User $user): array
    {
        return [
            'materials' => Material::query()->where('owner_id', $user->id)->count(),
            'students' => TeacherStudent::query()->where('teacher_id', $user->id)->where('status', 'approved')->count(),
            'teachers' => TeacherStudent::query()->where('student_id', $user->id)->where('status', 'approved')->count(),
            'downloads' => Download::query()->where('user_id', $user->id)->count(),
        ];
    }

    // ---- Bildirishnoma -------------------------------------------------------------------------

    /** Foydalanuvchiga holat o'zgargani haqida xabar yuboradi va uning navigatsiya holatini tozalaydi. */
    public static function notify(Nutgram $bot, User $target, string $kind, string $reason = ''): void
    {
        Navigator::resetFor($target->telegram_id);

        $withMenu = in_array($kind, ['unblocked', 'mod_added', 'mod_removed'], true);

        try {
            $bot->sendMessage(
                text: __("adm.notify.{$kind}", ['reason' => e($reason !== '' ? $reason : '—')]),
                chat_id: $target->telegram_id,
                parse_mode: ParseMode::HTML,
                reply_markup: $withMenu
                    ? MainMenu::keyboard($target)
                    : ($kind === 'deleted' ? ReplyKeyboardRemove::make(remove_keyboard: true) : null),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
