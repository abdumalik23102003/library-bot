<?php

namespace App\Telegram\Screens;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\DevMode;
use App\Telegram\Support\StaffNotifier;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Admin/moderatorga yuborilgan ariza kartochkasi tugmalari (tapp:approve:{id} / tapp:reject:{id}).
 */
class TeacherApplicationScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = User::find((int) ($params['id'] ?? 0));

        return Page::make($teacher ? StaffNotifier::applicationCard($teacher) : __('reg.application.not_found'));
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();

        if (! ($actor->isStaff() || DevMode::active($actor->telegram_id)) || ! in_array($action, ['approve', 'reject'], true)) {
            return;
        }

        $teacher = User::find((int) ($args[0] ?? 0));

        if ($teacher === null || ! $teacher->isTeacher()) {
            $this->finalize($bot, __('reg.application.not_found'));

            return;
        }

        $card = StaffNotifier::applicationCard($teacher);

        if ($teacher->status !== UserStatus::Pending || $teacher->registered_at === null) {
            $this->finalize($bot, $card . "\n\n" . __('reg.application.already', ['status' => $teacher->status->label()]));

            return;
        }

        $approved = $action === 'approve';

        if ($approved) {
            $teacher->status = UserStatus::Active;
            $teacher->approved_by = $actor->id;
            $teacher->approved_at = now();
        } else {
            // Rad etilgan: menyusiz qoladi, /start da rol tanlash qayta chiqadi (ma'lumotlari saqlanadi).
            $teacher->role = Role::Student;
            $teacher->status = UserStatus::Pending;
            $teacher->registered_at = null;
        }

        $teacher->save();

        AuditLog::record($actor, $approved ? 'teacher.approved' : 'teacher.rejected', $teacher);

        $this->finalize($bot, $card . "\n\n" . __(
            $approved ? 'reg.application.approved_by' : 'reg.application.rejected_by',
            ['actor' => e($actor->displayName())],
        ));

        StaffNotifier::notifyDecision($bot, $teacher, $approved);
    }

    /** Kartochkani qaror bilan yangilaydi va tugmalarni olib tashlaydi. */
    private function finalize(Nutgram $bot, string $text): void
    {
        $messageId = $bot->callbackQuery()?->message?->message_id;

        if (! $messageId) {
            return;
        }

        try {
            $bot->editMessageText(
                text: $text,
                chat_id: $bot->chatId(),
                message_id: $messageId,
                parse_mode: ParseMode::HTML,
                reply_markup: null,
            );
        } catch (Throwable) {
            // e'tibor bermaymiz
        }
    }
}
