<?php

namespace App\Telegram\Screens;

use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Incoming;
use App\Telegram\Support\Settings;
use SergiX44\Nutgram\Nutgram;

/** Sozlamalar (faqat admin): maksimal yuklash hajmi. Callback: set:ask, set:reset, set:cancel. params: ask, flash, error. */
class SetScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        if (! CurrentUser::get()->isAdmin()) {
            return Page::make(__('sys.forbidden'), back: true);
        }

        $lines = [];

        if (! empty($params['flash'])) {
            $lines[] = __("sys.set.{$params['flash']}");
            $lines[] = '';
        }

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        if (($params['ask'] ?? null) === 'max_mb') {
            $lines[] = __('sys.set.ask', ['max' => Settings::MAX_MB_LIMIT]);

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'set:cancel']]]);
        }

        $lines[] = __('sys.set.title', [
            'value' => Incoming::maxMb(),
            'source' => __(Settings::maxMbIsCustom() ? 'sys.set.source_db' : 'sys.set.source_env'),
        ]);

        $rows = [[[__('sys.set.btn_change'), 'set:ask']]];

        if (Settings::maxMbIsCustom()) {
            $rows[] = [[__('sys.set.btn_reset'), 'set:reset']];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();

        if (! $actor->isAdmin() || (Navigator::current($bot)['screen'] ?? '') !== 'set') {
            Navigator::refresh($bot);

            return;
        }

        match ($action) {
            'ask' => $this->ask($bot),
            'reset' => $this->reset($bot, $actor),
            'cancel' => Navigator::replace($bot, 'set'),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $actor = CurrentUser::get();

        if ($field !== 'max_mb' || ! $actor->isAdmin()) {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = Settings::parseMaxMb($text);

        if (! $ok) {
            Navigator::replace($bot, 'set', ['ask' => 'max_mb', 'error' => $value]);
            Navigator::awaitInput($bot, 'max_mb');

            return;
        }

        Settings::setMaxMb($actor, $value);

        Navigator::replace($bot, 'set', ['flash' => 'saved']);
    }

    private function ask(Nutgram $bot): void
    {
        Navigator::replace($bot, 'set', ['ask' => 'max_mb']);
        Navigator::awaitInput($bot, 'max_mb');
    }

    private function reset(Nutgram $bot, $actor): void
    {
        Settings::setMaxMb($actor, null);

        Navigator::replace($bot, 'set', ['flash' => 'reset']);
    }
}
