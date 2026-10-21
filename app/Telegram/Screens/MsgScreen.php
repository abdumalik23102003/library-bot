<?php

namespace App\Telegram\Screens;

use App\Models\Message;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Messaging;
use App\Telegram\Support\UserAdmin;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Xabar yozish va javob berish.
 * Callback: msg:to:{userId} (bildirishnomadan), msg:reply:{xabarId} (kartochkadan), msg:blk:{xabarId}, msg:cancel.
 * params: to | reply (kimga), error, sent/failed/label (natija).
 * Kartochkadagi tugmalar yangi xabar ochadi (Navigator::start), shunda asl xabar matni yo'qolmaydi.
 */
class MsgScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        if (array_key_exists('sent', $params)) {
            return $this->sentPage($params);
        }

        $target = $this->target(CurrentUser::get(), $params);

        if ($target === null) {
            return Page::make(__('msg.err.cannot'), back: true);
        }

        $lines = [__('msg.compose.title', ['to' => $target['label']])];

        if ($target['parent'] !== null) {
            $lines[] = '';
            $lines[] = __('msg.compose.quote', ['id' => $target['parent']->id, 'text' => $this->excerpt($target['parent'])]);
        }

        $lines[] = '';

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        $lines[] = __('msg.compose.ask', ['max' => Messaging::MAX_TEXT]);

        return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'msg:cancel']]]);
    }

    /** Matn yoki fayl kutish ("material" maydoni fayllarni ham qabul qiladi). */
    public function mounted(Nutgram $bot, array $params): void
    {
        if (! array_key_exists('sent', $params) && $this->target(CurrentUser::get(), $params) !== null) {
            Navigator::awaitInput($bot, 'material');
        }
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $id = (int) ($args[0] ?? 0);

        match ($action) {
            'reply' => Navigator::start($bot, 'msg', ['reply' => $id], ['home']),
            'to' => Navigator::start($bot, 'msg', ['to' => $id], ['home']),
            'blk' => $this->block($bot, $actor, $id),
            'cancel' => (Navigator::current($bot)['screen'] ?? '') === 'msg' ? Navigator::back($bot) : Navigator::refresh($bot),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $actor = CurrentUser::get();
        $target = $this->target($actor, $params);

        if ($field !== 'material' || $target === null) {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = Messaging::validateText($text);

        if (! $ok) {
            Navigator::replace($bot, 'msg', array_merge($params, ['error' => $value]));

            return;
        }

        $this->deliver($bot, $actor, $target, $params, 'text', null, $value);
    }

    public function file(Nutgram $bot, string $field, array $file, array $params): void
    {
        $actor = CurrentUser::get();
        $target = $this->target($actor, $params);

        if ($field !== 'material' || $target === null) {
            Navigator::refresh($bot);

            return;
        }

        $kind = (string) ($file['type'] ?? '');

        if ($kind === 'text' || ! in_array($kind, Messaging::KINDS, true)) {
            Navigator::replace($bot, 'msg', array_merge($params, ['error' => __('msg.err.file_type')]));

            return;
        }

        $this->deliver($bot, $actor, $target, $params, $kind, (string) $file['file_id'], $file['caption'] ?? null);
    }

    // ---- Ichki ------------------------------------------------------------------------------

    /**
     * Xabar kimga borishini aniqlaydi va ruxsatni tekshiradi.
     *
     * @return array{recipients: iterable<int, User>, parent: ?Message, label: string}|null
     */
    private function target(User $actor, array $params): ?array
    {
        // Javob: faqat o'zingizga kelgan xabarga, asl yuboruvchiga.
        if (! empty($params['reply'])) {
            $parent = Message::query()->where('receiver_id', $actor->id)->find((int) $params['reply']);
            $to = $parent?->sender;

            if ($to === null || ! Messaging::canWrite($actor, $to)) {
                return null;
            }

            return ['recipients' => [$to], 'parent' => $parent, 'label' => e($to->displayName())];
        }

        // Aniq foydalanuvchiga (xodim -> foydalanuvchi, yoki bildirishnomadagi "shu kishiga").
        if (! empty($params['to'])) {
            $to = User::query()->find((int) $params['to']);

            if ($to === null || ! Messaging::canWrite($actor, $to)) {
                return null;
            }

            return ['recipients' => [$to], 'parent' => null, 'label' => e($to->displayName())];
        }

        // Murojaat: xodim bo'lmagan foydalanuvchi -> barcha xodimlarga.
        if ($actor->isStaff() || ! $actor->isActive()) {
            return null;
        }

        $staff = Messaging::staff();

        return $staff->isEmpty() ? null : ['recipients' => $staff, 'parent' => null, 'label' => __('msg.staff_label')];
    }

    /** @param  array{recipients: iterable<int, User>, parent: ?Message, label: string}  $target */
    private function deliver(Nutgram $bot, User $actor, array $target, array $params, string $kind, ?string $fileId, ?string $body): void
    {
        if (($wait = Messaging::tooMany($actor)) !== null) {
            Navigator::replace($bot, 'msg', array_merge($params, [
                'error' => __('msg.err.rate', ['min' => max(1, (int) ceil($wait / 60))]),
            ]));

            return;
        }

        $result = Messaging::send($bot, $actor, $target['recipients'], $kind, $fileId, $body, $target['parent']);

        if ($result['sent'] > 0) {
            Messaging::hit($actor);
        }

        Navigator::replace($bot, 'msg', [
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'label' => $target['label'],
        ]);
    }

    /** Kartochkadagi [Bloklash]: foydalanuvchi kartochkasini ochadi (sabab so'raladi). */
    private function block(Nutgram $bot, User $actor, int $messageId): void
    {
        $sender = Message::query()->where('receiver_id', $actor->id)->find($messageId)?->sender;

        if ($sender === null || ! UserAdmin::can($actor, $sender)) {
            $bot->sendMessage(__('msg.err.cannot_block'));

            return;
        }

        Navigator::start($bot, 'usr', ['id' => $sender->id], ['home']);
    }

    private function sentPage(array $params): Page
    {
        $sent = (int) ($params['sent'] ?? 0);
        $failed = (int) ($params['failed'] ?? 0);

        $text = $sent > 0
            ? __('msg.sent.ok', ['to' => $params['label'] ?? ''])
            : __('msg.sent.fail');

        if ($sent > 0 && $failed > 0) {
            $text .= "\n\n" . __('msg.sent.partial', ['n' => $failed]);
        }

        return Page::make($text, back: true);
    }

    private function excerpt(Message $message): string
    {
        return filled($message->body) ? e(Str::limit($message->body, 160)) : __('msg.compose.quote_file');
    }
}
