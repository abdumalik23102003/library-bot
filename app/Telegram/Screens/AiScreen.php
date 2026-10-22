<?php

namespace App\Telegram\Screens;

use App\Services\AiChat;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;
use Throwable;

/** "🤖 AI yordamchi": barcha rollar uchun oddiy AI suhbat. Foydalanuvchi matn yozadi, AI javob beradi. */
class AiScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();

        if (! $user->isActive()) {
            return Page::make(__('ai.err_inactive'), back: true);
        }

        $lines = [__('ai.title')];
        $last = Cache::get("ai:last:{$user->id}");

        if (is_array($last)) {
            $lines[] = '';
            $lines[] = __('ai.you', ['text' => e(Str::limit((string) $last['q'], 200))]);
            $lines[] = '';
            $lines[] = '🤖 ' . e(Str::limit((string) $last['a'], 3300));
        } else {
            $lines[] = '';
            $lines[] = __('ai.hint');
        }

        if (! empty($params['flash'])) {
            $lines[] = '';
            $lines[] = __("ai.flash.{$params['flash']}");
        }

        $lines[] = '';
        $lines[] = __('ai.ask');

        return Page::make(implode("\n", $lines), [[[__('ai.btn_new'), 'ai:new']]], back: true);
    }

    public function mounted(Nutgram $bot, array $params): void
    {
        if (CurrentUser::get()->isActive()) {
            Navigator::awaitInput($bot, 'msg');
        }
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $user = CurrentUser::get();

        if ((Navigator::current($bot)['screen'] ?? '') !== 'ai' || ! $user->isActive()) {
            Navigator::refresh($bot);

            return;
        }

        if ($action === 'new') {
            AiChat::forget($user);
            Navigator::replace($bot, 'ai', ['flash' => 'cleared']);

            return;
        }

        Navigator::refresh($bot);
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $user = CurrentUser::get();
        $text = trim($text);
        $to = fn (string $flash = '') => Navigator::replace($bot, 'ai', $flash !== '' ? ['flash' => $flash] : []);

        if ($field !== 'msg' || $text === '' || ! $user->isActive()) {
            Navigator::refresh($bot);

            return;
        }

        if (mb_strlen($text) > 2000) {
            $to('long');

            return;
        }

        $ai = app(AiChat::class);

        if (! $ai->enabled()) {
            $to('no_key');

            return;
        }

        $limit = "ai:rate:{$user->id}";

        if (RateLimiter::tooManyAttempts($limit, 10)) {
            $to('busy');

            return;
        }

        RateLimiter::hit($limit, 60);

        try {
            $answer = $ai->reply($user, $text);
        } catch (Throwable $e) {
            report($e);
            $to('error');

            return;
        }

        Cache::put("ai:last:{$user->id}", ['q' => $text, 'a' => $answer], now()->addHours(6));
        $to();
    }
}
