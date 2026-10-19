<?php

namespace App\Telegram\Middleware;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\DevMode;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

/**
 * Har bir update uchun: foydalanuvchini topadi/yaratadi, ENV admin ni belgilaydi, bloklanganni to'xtatadi.
 */
final class EnsureUser
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $from = $bot->user();

        if ($from === null || $from->is_bot) {
            return;
        }

        $user = User::withTrashed()->firstOrNew(['telegram_id' => $from->id]);

        if ($user->trashed()) {
            $this->deny($bot, __('reg.deleted'), __('reg.deleted'));

            return;
        }

        if (! $user->exists) {
            $user->role = Role::Student;
            $user->status = UserStatus::Pending;
        }

        $user->username = $from->username;

        if ($this->isEnvAdmin($from->id) && ! DevMode::active($from->id)) {
            $user->role = Role::Admin;
            $user->status = UserStatus::Active;
            $user->registered_at ??= now();
            $user->full_name ??= trim($from->first_name . ' ' . ($from->last_name ?? ''));
        }

        $user->last_seen_at = now();
        $user->save();

        if ($user->isBlocked()) {
            $this->deny(
                $bot,
                __('reg.blocked', ['reason' => e($user->blocked_reason ?: '—')]),
                __('reg.blocked_short'),
            );

            return;
        }

        CurrentUser::set($user);

        $next($bot);
    }

    private function isEnvAdmin(int $telegramId): bool
    {
        $adminId = (int) config('library.admin_telegram_id');

        return $adminId > 0 && $adminId === $telegramId;
    }

    private function deny(Nutgram $bot, string $text, string $alert): void
    {
        if ($bot->callbackQuery()) {
            $bot->answerCallbackQuery(text: strip_tags($alert), show_alert: true);

            return;
        }

        $bot->sendMessage(text: $text, parse_mode: ParseMode::HTML);
    }
}
