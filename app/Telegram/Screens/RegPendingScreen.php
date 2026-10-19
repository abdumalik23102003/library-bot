<?php

namespace App\Telegram\Screens;

use App\Telegram\Keyboards\MainMenu;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Entry;
use SergiX44\Nutgram\Nutgram;

class RegPendingScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $text = __('reg.pending.text');

        if (! empty($params['checked'])) {
            $text .= "\n\n" . __('reg.pending.checked', ['time' => $params['checked']]);
        }

        return Page::make($text, [[[__('reg.pending.btn_check'), 'regpending:check']]]);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        if ($action !== 'check') {
            Navigator::refresh($bot);

            return;
        }

        // Tasdiqlangan bo'lsa (anchor yangilanmay qolgan holat), bosh sahifaga o'tamiz.
        if (Entry::gate(CurrentUser::get()) === null) {
            $bot->sendMessage(text: __('reg.menu_ready'), reply_markup: MainMenu::keyboard());
            Navigator::start($bot, 'home');

            return;
        }

        Navigator::replace($bot, 'regpending', ['checked' => now()->format('H:i:s')]);
    }
}
