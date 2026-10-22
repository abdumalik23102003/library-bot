<?php

namespace App\Telegram\Screens;

use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\AccountReset;
use App\Telegram\Support\Chat;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\ProfileFields;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

/**
 * Profilni ko'rish va tahrirlash (rolga qarab maydonlar: ProfileFields::editable).
 */
class ProfileScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();
        $fields = ProfileFields::editable($user);
        $ask = $params['ask'] ?? null;

        $lines = [__('profile.title'), ''];
        $lines[] = __('profile.role') . ': <b>' . e($user->role->label()) . '</b>';
        $lines[] = __('profile.status') . ': <b>' . e($user->status->label()) . '</b>';
        $lines[] = __('profile.telegram') . ': ' . ($user->username ? '@' . e($user->username) : 'ID ' . $user->telegram_id);

        foreach ($fields as $field) {
            $value = $user->{$field};
            $lines[] = __("reg.field.{$field}") . ': '
                . (filled($value) ? '<b>' . e(Str::limit((string) $value, 200)) . '</b>' : __('reg.empty'));
        }

        if ($user->registered_at !== null) {
            $lines[] = __('profile.registered') . ': ' . $user->registered_at->format('d.m.Y');
        }

        $lines[] = '';

        if (! empty($params['saved'])) {
            $lines[] = __('profile.saved');
            $lines[] = '';
        }

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        if (! empty($params['confirm']) && ! $user->isStaff()) {
            return Page::make(__('profile.delete_confirm'), [[
                [__('profile.btn_delete_yes'), 'profile:delyes'],
                [__('profile.btn_delete_no'), 'profile:cancel'],
            ]]);
        }

        if ($ask !== null && in_array($ask, $fields, true)) {
            $lines[] = __("reg.ask.{$ask}");

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'profile:cancel']]]);
        }

        $lines[] = __('profile.hint');

        $edit = array_map(fn (string $f) => [__("reg.edit.{$f}"), "profile:ask:{$f}"], $fields);

        $rows = array_chunk($edit, 2);

        if (! $user->isStaff()) {
            $rows[] = [[__('profile.btn_delete'), 'profile:del']];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        match ($action) {
            'ask' => $this->ask($bot, CurrentUser::get(), (string) ($args[0] ?? '')),
            'cancel' => $this->cancel($bot),
            'del' => CurrentUser::get()->isStaff() ? Navigator::refresh($bot) : Navigator::replace($bot, 'profile', ['confirm' => true]),
            'delyes' => $this->deleteProfile($bot, CurrentUser::get()),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $user = CurrentUser::get();

        if (! in_array($field, ProfileFields::editable($user), true)) {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = ProfileFields::validate($field, $text);

        if (! $ok) {
            Navigator::replace($bot, 'profile', array_filter([
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
            Chat::restoreMenu($bot);
        }

        Navigator::replace($bot, 'profile', ['saved' => true]);
    }

    private function ask(Nutgram $bot, User $user, string $field): void
    {
        if (! in_array($field, ProfileFields::editable($user), true)) {
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

        Navigator::replace($bot, 'profile', $params);
        Navigator::awaitInput($bot, $field);
    }

    /** Profilni o'chiradi: bog'langan holat tozalanadi, foydalanuvchi /start bilan qaytadan ro'yxatdan o'tadi. */
    private function deleteProfile(Nutgram $bot, User $user): void
    {
        if ($user->isStaff()) {
            Navigator::refresh($bot);

            return;
        }

        $anchor = $bot->callbackQuery()?->message?->message_id;

        AccountReset::deleteSelf($user);

        Chat::delete($bot, $anchor);

        $bot->sendMessage(
            text: __('profile.deleted'),
            parse_mode: ParseMode::HTML,
            reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
        );
    }

    private function cancel(Nutgram $bot): void
    {
        $params = Navigator::current($bot)['params'] ?? [];

        if (($params['ask'] ?? null) === 'phone') {
            Chat::delete($bot, $params['hint'] ?? null);
            Chat::restoreMenu($bot);
        }

        Navigator::replace($bot, 'profile');
    }
}
