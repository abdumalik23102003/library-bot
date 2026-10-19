<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Demo: matn kutish + natijani xuddi shu xabarda ko'rsatish.
 */
class EchoScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        if (! isset($params['text'])) {
            return Page::make(__('bot.echo.prompt'), back: true);
        }

        return Page::make(
            __('bot.echo.result', ['text' => e(Str::limit($params['text'], 500))]),
            [[[__('bot.echo.btn_again'), 'echo:again']]],
            back: true,
        );
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        if ($action === 'again') {
            Navigator::replace($bot, 'echo');
            Navigator::awaitInput($bot, 'text');

            return;
        }

        Navigator::refresh($bot);
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        Navigator::replace($bot, 'echo', ['text' => $text]);
    }
}
