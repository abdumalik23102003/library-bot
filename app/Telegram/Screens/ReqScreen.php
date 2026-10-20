<?php

namespace App\Telegram\Screens;

use App\Models\User;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\LinkNotifier;
use App\Telegram\Support\TeacherLinks;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Ustozga yuborilgan so'rov kartochkalarining tugmalari: req:jacc|jrej|pacc|prej:{o'quvchiId}.
 * Navigatsiyaga tegmaydi: kartochkaning o'zi qaror bilan yangilanadi.
 */
class ReqScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        return Page::make(__('links.err.not_found'));
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $isJoin = in_array($action, ['jacc', 'jrej'], true);
        $isProtected = in_array($action, ['pacc', 'prej'], true);

        if (! $isJoin && ! $isProtected) {
            return;
        }

        $teacher = CurrentUser::get();
        $student = User::find((int) ($args[0] ?? 0));

        if (! $teacher->isTeacher() || ! $teacher->isActive() || $student === null) {
            $this->finalize($bot, __('links.err.not_found'));

            return;
        }

        $accept = in_array($action, ['jacc', 'pacc'], true);
        $card = $isJoin ? LinkNotifier::joinCard($student) : LinkNotifier::protectedCard($student);

        $done = $isJoin
            ? TeacherLinks::decideJoin($teacher, $student, $accept)
            : TeacherLinks::decideProtected($teacher, $student, $accept);

        if (! $done) {
            $this->finalize($bot, $card . "\n\n" . __('links.notify.already'));

            return;
        }

        $this->finalize($bot, $card . "\n\n" . __($accept ? 'links.notify.done_accept' : 'links.notify.done_reject'));

        $isJoin
            ? LinkNotifier::joinDecision($bot, $teacher, $student, $accept)
            : LinkNotifier::protectedDecision($bot, $teacher, $student, $accept ? 'granted' : 'rejected');
    }

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
