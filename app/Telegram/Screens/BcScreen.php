<?php

namespace App\Telegram\Screens;

use App\Jobs\SendBroadcast;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\Broadcasts;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Messaging;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Broadcast (faqat admin): auditoriya -> matn/fayl -> tasdiqlash -> navbatga qo'yish.
 * Callback: bc:a:{auditoriya}, bc:edit, bc:go, bc:cancel. params: step (audience|compose|confirm|done), audience, kind, file_id, text, error, n.
 */
class BcScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();

        if (! $actor->isAdmin()) {
            return Page::make(__('msg.bc.forbidden'), back: true);
        }

        return match ($params['step'] ?? 'audience') {
            'compose' => $this->composePage($actor, $params),
            'confirm' => $this->confirmPage($actor, $params),
            'done' => Page::make(__('msg.bc.queued', ['n' => (int) ($params['n'] ?? 0)]), back: true),
            default => $this->audiencePage($actor),
        };
    }

    public function mounted(Nutgram $bot, array $params): void
    {
        if (($params['step'] ?? '') === 'compose' && CurrentUser::get()->isAdmin()) {
            Navigator::awaitInput($bot, 'material');
        }
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $top = Navigator::current($bot);

        if (! $actor->isAdmin() || ($top['screen'] ?? '') !== 'bc') {
            Navigator::refresh($bot);

            return;
        }

        $params = $top['params'];
        $step = (string) ($params['step'] ?? 'audience');
        $audience = (string) ($params['audience'] ?? '');

        match (true) {
            $action === 'a' && $step === 'audience' && Broadcasts::valid((string) ($args[0] ?? '')) => Navigator::replace($bot, 'bc', ['step' => 'compose', 'audience' => $args[0]]),
            $action === 'edit' && $step === 'confirm' => Navigator::replace($bot, 'bc', ['step' => 'compose', 'audience' => $audience]),
            $action === 'cancel' && $step !== 'audience' => Navigator::replace($bot, 'bc', []),
            $action === 'go' && $step === 'confirm' => $this->go($bot, $actor, $params),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $audience = (string) ($params['audience'] ?? '');

        if ($field !== 'material' || ! CurrentUser::get()->isAdmin() || ! Broadcasts::valid($audience)) {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = Messaging::validateText($text);

        if (! $ok) {
            Navigator::replace($bot, 'bc', ['step' => 'compose', 'audience' => $audience, 'error' => $value]);

            return;
        }

        Navigator::replace($bot, 'bc', ['step' => 'confirm', 'audience' => $audience, 'kind' => 'text', 'text' => $value]);
    }

    public function file(Nutgram $bot, string $field, array $file, array $params): void
    {
        $audience = (string) ($params['audience'] ?? '');
        $kind = (string) ($file['type'] ?? '');

        if ($field !== 'material' || ! CurrentUser::get()->isAdmin() || ! Broadcasts::valid($audience)) {
            Navigator::refresh($bot);

            return;
        }

        if ($kind === 'text' || ! in_array($kind, Messaging::KINDS, true)) {
            Navigator::replace($bot, 'bc', ['step' => 'compose', 'audience' => $audience, 'error' => __('msg.err.file_type')]);

            return;
        }

        Navigator::replace($bot, 'bc', [
            'step' => 'confirm',
            'audience' => $audience,
            'kind' => $kind,
            'file_id' => (string) $file['file_id'],
            'text' => (string) ($file['caption'] ?? ''),
        ]);
    }

    // ---- Ko'rinishlar ---------------------------------------------------------------------------

    private function audiencePage(User $actor): Page
    {
        $counts = [];
        $rows = [];

        foreach (Broadcasts::AUDIENCES as $audience) {
            $counts[$audience] = Broadcasts::count($actor, $audience);
            $rows[] = [[Broadcasts::label($audience), "bc:a:{$audience}"]];
        }

        return Page::make(__('msg.bc.audience', $counts), $rows, back: true);
    }

    private function composePage(User $actor, array $params): Page
    {
        $audience = (string) ($params['audience'] ?? '');

        $lines = [__('msg.bc.compose', [
            'aud' => Broadcasts::label($audience),
            'n' => Broadcasts::count($actor, $audience),
            'max' => Messaging::MAX_TEXT,
        ])];

        if (! empty($params['error'])) {
            $lines[] = '';
            $lines[] = '⚠️ ' . e($params['error']);
        }

        return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'bc:cancel']]]);
    }

    private function confirmPage(User $actor, array $params): Page
    {
        $audience = (string) ($params['audience'] ?? '');
        $kind = (string) ($params['kind'] ?? 'text');
        $text = (string) ($params['text'] ?? '');
        $n = Broadcasts::count($actor, $audience);

        $lines = [__('msg.bc.confirm', ['aud' => Broadcasts::label($audience), 'n' => $n]), ''];

        if ($kind !== 'text') {
            $lines[] = __('msg.bc.file_line', ['type' => __("enums.type.{$kind}")]);
        }

        $lines[] = $text !== '' ? e(Str::limit($text, 1200)) : __('msg.bc.no_text');

        $rows = [];

        if ($n > 0) {
            $rows[] = [[__('msg.bc.btn_go', ['n' => $n]), 'bc:go']];
        } else {
            $lines[] = '';
            $lines[] = __('msg.bc.empty');
        }

        $rows[] = [[__('msg.bc.btn_edit'), 'bc:edit'], [__('bot.cancel'), 'bc:cancel']];

        return Page::make(implode("\n", $lines), $rows);
    }

    /** Qabul qiluvchilar soni qayta hisoblanadi; ikki marta bosilsa, ikkinchisi e'tiborsiz qoladi. */
    private function go(Nutgram $bot, User $actor, array $params): void
    {
        $audience = (string) ($params['audience'] ?? '');
        $n = Broadcasts::valid($audience) ? Broadcasts::count($actor, $audience) : 0;

        if ($n === 0) {
            Navigator::refresh($bot);

            return;
        }

        $kind = (string) ($params['kind'] ?? 'text');
        $fileId = isset($params['file_id']) ? (string) $params['file_id'] : null;
        $text = (string) ($params['text'] ?? '');

        $key = 'broadcast:' . $actor->id . ':' . md5(json_encode([$audience, $kind, $fileId, $text]));

        if (Cache::add($key, true, now()->addSeconds(30))) {
            SendBroadcast::dispatch($actor->id, $audience, $kind, $fileId, $text);
        }

        Navigator::replace($bot, 'bc', ['step' => 'done', 'n' => $n]);
    }
}
