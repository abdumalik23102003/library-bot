<?php

namespace App\Telegram\Screens;

use App\Enums\UserStatus;
use App\Models\User;
use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\Chat;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\StaffNotifier;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

/**
 * Profil kartochkasi: to'ldirilgan maydonlarning tugmasi yo'qoladi, hammasi tayyor bo'lsa "Yakunlash" chiqadi.
 */
class RegProfileScreen extends Screen
{
    /** @return array<int, string> */
    private function fields(User $user): array
    {
        return $user->isTeacher() ? ['full_name', 'subject', 'phone', 'bio'] : ['full_name'];
    }

    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();
        $fields = $this->fields($user);
        $ask = $params['ask'] ?? null;

        $lines = [__($user->isTeacher() ? 'reg.profile.title_teacher' : 'reg.profile.title_student'), ''];

        foreach ($fields as $field) {
            $value = $user->{$field};
            $lines[] = __("reg.field.{$field}") . ': '
                . (filled($value) ? '<b>' . e(Str::limit((string) $value, 200)) . '</b>' : __('reg.empty'));
        }

        $lines[] = '';

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        if ($ask !== null) {
            $lines[] = __("reg.ask.{$ask}");

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'regprofile:cancel']]]);
        }

        $missing = array_values(array_filter($fields, fn (string $f) => blank($user->{$f})));

        if ($missing === []) {
            $lines[] = __('reg.profile.complete');
            $rows = [[[__('reg.btn_finish'), 'regprofile:finish']]];

            $edit = array_map(fn (string $f) => [__("reg.edit.{$f}"), "regprofile:ask:{$f}"], $fields);
            foreach (array_chunk($edit, 2) as $chunk) {
                $rows[] = $chunk;
            }
        } else {
            $lines[] = __('reg.profile.fill');
            $rows = array_map(fn (string $f) => [[__("reg.add.{$f}"), "regprofile:ask:{$f}"]], $missing);
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $user = CurrentUser::get();

        match ($action) {
            'ask' => $this->ask($bot, $user, (string) ($args[0] ?? '')),
            'cancel' => $this->cancel($bot),
            'finish' => $this->finish($bot, $user),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $user = CurrentUser::get();

        if (! in_array($field, $this->fields($user), true)) {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = $this->validate($field, $text);

        if (! $ok) {
            Navigator::replace($bot, 'regprofile', array_filter([
                'ask' => $field,
                'error' => $value,
                'hint' => $params['hint'] ?? null,
            ]));
            Navigator::awaitInput($bot, $field);

            return;
        }

        $user->{$field} = $value;
        $user->save();

        if ($field === 'phone') {
            Chat::delete($bot, $params['hint'] ?? null);
            Chat::removeReplyKeyboard($bot);
        }

        Navigator::replace($bot, 'regprofile');
    }

    private function ask(Nutgram $bot, User $user, string $field): void
    {
        if (! in_array($field, $this->fields($user), true)) {
            Navigator::refresh($bot);

            return;
        }

        $params = ['ask' => $field];

        if ($field === 'phone') {
            $sent = $bot->sendMessage(
                text: __('reg.phone_hint'),
                reply_markup: ReplyKeyboardMarkup::make(resize_keyboard: true, one_time_keyboard: true)->addRow(
                    KeyboardButton::make(__('reg.share_phone'), request_contact: true),
                ),
            );
            $params['hint'] = $sent?->message_id;
        }

        Navigator::replace($bot, 'regprofile', $params);
        Navigator::awaitInput($bot, $field);
    }

    private function cancel(Nutgram $bot): void
    {
        $params = Navigator::current($bot)['params'] ?? [];

        if (($params['ask'] ?? null) === 'phone') {
            Chat::delete($bot, $params['hint'] ?? null);
            Chat::removeReplyKeyboard($bot);
        }

        Navigator::replace($bot, 'regprofile');
    }

    private function finish(Nutgram $bot, User $user): void
    {
        $missing = array_filter($this->fields($user), fn (string $f) => blank($user->{$f}));

        if ($missing !== []) {
            Navigator::replace($bot, 'regprofile', ['error' => __('reg.err.incomplete')]);

            return;
        }

        $user->registered_at = now();

        if ($user->isTeacher()) {
            // Admin yoki moderator tasdiqlaguncha status "pending" bo'lib qoladi.
            $user->save();
            StaffNotifier::teacherApplication($bot, $user);
            Navigator::replace($bot, 'regpending');

            return;
        }

        $user->status = UserStatus::Active;
        $user->save();

        $bot->sendMessage(text: __('reg.done_student'), reply_markup: MainMenu::keyboard());
        Navigator::start($bot, 'home');
    }

    /** @return array{0: bool, 1: string} [ok, qiymat yoki xato matni] */
    private function validate(string $field, string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($text);

        return match ($field) {
            'full_name' => preg_match('/^[\p{L}\p{M}][\p{L}\p{M} \'’ʻʼ`.\-]{2,99}$/u', $text)
                ? [true, $text]
                : [false, __('reg.err.full_name')],
            'subject' => $length >= 2 && $length <= 100
                ? [true, $text]
                : [false, __('reg.err.subject')],
            'bio' => $length >= 5 && $length <= 500
                ? [true, $text]
                : [false, __('reg.err.bio')],
            'phone' => ($phone = $this->normalizePhone($text)) !== null
                ? [true, $phone]
                : [false, __('reg.err.phone')],
            default => [false, __('reg.err.incomplete')],
        };
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? '+' . $digits : null;
    }
}
