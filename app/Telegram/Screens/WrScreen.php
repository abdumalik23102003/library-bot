<?php

namespace App\Telegram\Screens;

use App\Jobs\SendMessages;
use App\Models\Message;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\MessageTargets;
use App\Telegram\Support\Messaging;
use App\Telegram\Support\Pager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Xabar yozish: qabul qiluvchilarni tanlash (✅/⬜, raqam, sahifalash, qidiruv) -> matn/fayl -> tasdiqlash -> yuborish.
 *
 * params: step (pick|search|compose|confirm|done), page, error, reply (xabar id) | to (foydalanuvchi id) — bular tanlashsiz to'g'ridan-to'g'ri yozish,
 *         done uchun: sent, failed, queued, label.
 * Tanlov va qoralama Cache da saqlanadi (draft): ids, filter, q, kind, file_id, text. Shunda tugmalar bosilganda eskirgan params xalaqit bermaydi.
 * Callback: wr:t:{id} wr:p:{sahifa} wr:f:{filtr} wr:all wr:none wr:s wr:sc wr:adm wr:next wr:pick wr:edit wr:go wr:cancel wr:again.
 */
class WrScreen extends Screen
{
    public static function forget(User $actor): void
    {
        Cache::forget(self::key($actor));
    }

    private static function key(User $actor): string
    {
        return "wr:draft:{$actor->id}";
    }

    /** @return array{ids: array<int, int>, filter: string, q: string, kind: string, file_id: ?string, text: string} */
    private function draft(User $actor): array
    {
        return array_merge(
            ['ids' => [], 'filter' => 'all', 'q' => '', 'kind' => 'text', 'file_id' => null, 'text' => ''],
            Cache::get(self::key($actor)) ?? [],
        );
    }

    private function save(User $actor, array $draft): void
    {
        Cache::put(self::key($actor), $draft, now()->addDay());
    }

    private function fixedMode(array $params): bool
    {
        return ! empty($params['reply']) || ! empty($params['to']);
    }

    // ---- Ko'rinish ----------------------------------------------------------------------------

    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();

        if (! $actor->isActive() || $actor->registered_at === null) {
            return Page::make(__('msg.err.cannot'), back: true);
        }

        $step = (string) ($params['step'] ?? ($this->fixedMode($params) ? 'compose' : 'pick'));

        if ($step === 'done') {
            return $this->donePage($params);
        }

        if ($this->fixedMode($params) && $this->resolve($actor, $params, $this->draft($actor))['users']->isEmpty()) {
            return Page::make(__('msg.err.cannot'), back: true);
        }

        return match ($step) {
            'search' => $this->searchPage(),
            'compose' => $this->composePage($actor, $params),
            'confirm' => $this->confirmPage($actor, $params),
            default => $this->pickPage($actor, $params),
        };
    }

    /** Matn/fayl yoki qidiruv matnini kutadi. */
    public function mounted(Nutgram $bot, array $params): void
    {
        $step = (string) ($params['step'] ?? ($this->fixedMode($params) ? 'compose' : 'pick'));

        if ($step === 'compose') {
            Navigator::awaitInput($bot, 'material');
        } elseif ($step === 'search') {
            Navigator::awaitInput($bot, 'q');
        }
    }

    private function pickPage(User $actor, array $params): Page
    {
        $d = $this->draft($actor);
        $filters = MessageTargets::filters($actor);
        $filter = in_array($d['filter'], $filters, true) ? $d['filter'] : 'all';

        [$items, $page, $pages, $total] = Pager::slice(MessageTargets::search($actor, $filter, $d['q']), (int) ($params['page'] ?? 1));

        $selected = count($d['ids']);
        $chosen = array_flip($d['ids']);

        $lines = [__('msg.wr.pick_title', ['n' => $selected])];

        if ($d['q'] !== '') {
            $lines[] = __('msg.wr.searching', ['q' => e($d['q'])]);
        }

        $rows = [];

        $filterRow = [];
        foreach ($filters as $f) {
            $filterRow[] = [($f === $filter ? '• ' : '') . __("msg.wr.f.{$f}"), "wr:f:{$f}"];
        }
        $rows[] = $filterRow;

        if ($total === 0) {
            $lines[] = '';
            $lines[] = __($d['q'] !== '' ? 'msg.wr.none_found' : 'msg.wr.empty');
        } else {
            $lines[] = __('msg.wr.page_info', ['total' => $total, 'page' => $page, 'pages' => $pages]);
        }

        $number = ($page - 1) * Pager::PER_PAGE;

        foreach ($items as $user) {
            $number++;
            $mark = isset($chosen[$user->id]) ? '✅' : '⬜';
            $rows[] = [["{$mark} {$number}. " . Str::limit($user->displayName(), 30) . ' ' . $this->icon($user), "wr:t:{$user->id}"]];
        }

        if ($nav = Pager::navRow('wr:p', $page, $pages)) {
            $rows[] = $nav;
        }

        if ($total > 0) {
            $rows[] = [
                [__('msg.wr.btn_all', ['n' => min($total, MessageTargets::MAX)]), 'wr:all'],
                [__('msg.wr.btn_none'), 'wr:none'],
            ];
        }

        $search = [[__('msg.wr.btn_search'), 'wr:s']];
        if ($d['q'] !== '') {
            $search[] = [__('msg.wr.btn_search_clear'), 'wr:sc'];
        }
        $rows[] = $search;

        if (! $actor->isStaff()) {
            $rows[] = [[__('msg.wr.btn_admin'), 'wr:adm']];
        }

        if ($selected > 0) {
            $rows[] = [[__('msg.wr.btn_next', ['n' => $selected]), 'wr:next']];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    private function searchPage(): Page
    {
        return Page::make(__('msg.wr.search_ask'), [[[__('bot.cancel'), 'wr:pick']]]);
    }

    private function composePage(User $actor, array $params): Page
    {
        $d = $this->draft($actor);
        $r = $this->resolve($actor, $params, $d);

        $lines = [__('msg.wr.compose_title', ['to' => $this->label($r['users'])])];

        if ($r['parent'] !== null) {
            $lines[] = '';
            $lines[] = __('msg.wr.quote', ['id' => $r['parent']->id, 'text' => filled($r['parent']->body) ? e(Str::limit($r['parent']->body, 160)) : __('msg.wr.quote_file')]);
        }

        $lines[] = '';

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        $lines[] = __('msg.wr.compose_ask', ['max' => Messaging::MAX_TEXT]);

        $rows = [];

        if (! $this->fixedMode($params)) {
            $rows[] = [[__('msg.wr.btn_people'), 'wr:pick']];
        }

        $rows[] = [[__('bot.cancel'), 'wr:cancel']];

        return Page::make(implode("\n", $lines), $rows);
    }

    private function confirmPage(User $actor, array $params): Page
    {
        $d = $this->draft($actor);
        $r = $this->resolve($actor, $params, $d);
        $n = $r['users']->count();

        $lines = [__('msg.wr.confirm_title', ['to' => $this->label($r['users']), 'n' => $n]), ''];

        if ($d['kind'] !== 'text') {
            $lines[] = __('msg.bc.file_line', ['type' => __("enums.type.{$d['kind']}")]);
        }

        $lines[] = $d['text'] !== '' ? e(Str::limit($d['text'], 1200)) : __('msg.bc.no_text');

        if ($n > MessageTargets::SYNC_LIMIT) {
            $lines[] = '';
            $lines[] = __('msg.wr.queue_note');
        }

        if (! empty($params['error'])) {
            $lines[] = '';
            $lines[] = '⚠️ ' . e($params['error']);
        }

        $rows = [];

        if ($n > 0) {
            $rows[] = [[__('msg.wr.btn_send', ['n' => $n]), 'wr:go']];
        } else {
            $lines[] = '';
            $lines[] = __('msg.wr.no_recipients');
        }

        $second = [[__('msg.wr.btn_edit'), 'wr:edit']];

        if (! $this->fixedMode($params)) {
            $second[] = [__('msg.wr.btn_people'), 'wr:pick'];
        }

        $rows[] = $second;
        $rows[] = [[__('bot.cancel'), 'wr:cancel']];

        return Page::make(implode("\n", $lines), $rows);
    }

    private function donePage(array $params): Page
    {
        $sent = (int) ($params['sent'] ?? 0);
        $failed = (int) ($params['failed'] ?? 0);

        if (! empty($params['queued'])) {
            $text = __('msg.wr.queued', ['n' => (int) $params['queued']]);
        } elseif ($sent > 0) {
            $text = __('msg.sent.ok', ['to' => $params['label'] ?? '']);

            if ($failed > 0) {
                $text .= "\n\n" . __('msg.sent.partial', ['n' => $failed]);
            }
        } else {
            $text = __('msg.sent.fail');
        }

        return Page::make($text, [[[__('msg.wr.btn_again'), 'wr:again']]], back: true);
    }

    // ---- Tugmalar -------------------------------------------------------------------------------

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $top = Navigator::current($bot);

        if (($top['screen'] ?? '') !== 'wr' || ! $actor->isActive()) {
            Navigator::refresh($bot);

            return;
        }

        $params = $top['params'];
        $page = (int) ($params['page'] ?? 1);
        $d = $this->draft($actor);
        $fixed = $this->fixedMode($params);

        switch ($action) {
            case 't':
                $id = (int) ($args[0] ?? 0);

                if (in_array($id, $d['ids'], true)) {
                    $d['ids'] = array_values(array_diff($d['ids'], [$id]));
                } elseif (count($d['ids']) < MessageTargets::MAX && MessageTargets::pool($actor)->whereKey($id)->exists()) {
                    $d['ids'][] = $id;
                }

                $this->save($actor, $d);
                $this->pick($bot, $page);
                break;

            case 'p':
                $this->pick($bot, max(1, (int) ($args[0] ?? 1)));
                break;

            case 'f':
                $f = (string) ($args[0] ?? '');
                $d['filter'] = in_array($f, MessageTargets::filters($actor), true) ? $f : 'all';
                $this->save($actor, $d);
                $this->pick($bot, 1);
                break;

            case 'all':
                $found = MessageTargets::search($actor, $d['filter'], $d['q'])->limit(MessageTargets::MAX)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $d['ids'] = array_slice(array_values(array_unique(array_merge($d['ids'], $found))), 0, MessageTargets::MAX);
                $this->save($actor, $d);
                $this->pick($bot, $page);
                break;

            case 'none':
                $d['ids'] = [];
                $this->save($actor, $d);
                $this->pick($bot, $page);
                break;

            case 's':
                Navigator::replace($bot, 'wr', ['step' => 'search']);
                break;

            case 'sc':
                $d['q'] = '';
                $this->save($actor, $d);
                $this->pick($bot, 1);
                break;

            case 'adm':
                $d['ids'] = MessageTargets::search($actor, 'staff')->limit(MessageTargets::MAX)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $this->save($actor, $d);
                $d['ids'] === [] ? $this->pick($bot, 1) : Navigator::replace($bot, 'wr', ['step' => 'compose']);
                break;

            case 'next':
            case 'edit':
                $ok = $fixed || $d['ids'] !== [];
                Navigator::replace($bot, 'wr', $ok ? array_merge($this->keep($params), ['step' => 'compose']) : ['step' => 'pick']);
                break;

            case 'pick':
                $this->pick($bot, 1);
                break;

            case 'go':
                $this->go($bot, $actor, $params, $d);
                break;

            case 'again':
                self::forget($actor);
                Navigator::replace($bot, 'wr', []);
                break;

            default: // cancel
                self::forget($actor);
                Navigator::back($bot);
        }
    }

    private function pick(Nutgram $bot, int $page): void
    {
        Navigator::replace($bot, 'wr', ['step' => 'pick', 'page' => $page]);
    }

    /** Qaytadan ochilganda saqlanadigan (reply/to) parametrlar. */
    private function keep(array $params): array
    {
        return array_intersect_key($params, ['reply' => 1, 'to' => 1]);
    }

    // ---- Matn va fayl ---------------------------------------------------------------------------

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $actor = CurrentUser::get();
        $d = $this->draft($actor);

        if ($field === 'q') {
            $d['q'] = Str::limit(trim($text), 50, '');
            $this->save($actor, $d);
            $this->pick($bot, 1);

            return;
        }

        if ($field !== 'material') {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = Messaging::validateText($text);

        if (! $ok) {
            Navigator::replace($bot, 'wr', array_merge($this->keep($params), ['step' => 'compose', 'error' => $value]));

            return;
        }

        $this->save($actor, array_merge($d, ['kind' => 'text', 'file_id' => null, 'text' => $value]));
        Navigator::replace($bot, 'wr', array_merge($this->keep($params), ['step' => 'confirm']));
    }

    public function file(Nutgram $bot, string $field, array $file, array $params): void
    {
        $actor = CurrentUser::get();
        $kind = (string) ($file['type'] ?? '');

        if ($field !== 'material') {
            Navigator::refresh($bot);

            return;
        }

        if ($kind === 'text' || ! in_array($kind, Messaging::KINDS, true)) {
            Navigator::replace($bot, 'wr', array_merge($this->keep($params), ['step' => 'compose', 'error' => __('msg.err.file_type')]));

            return;
        }

        $caption = (string) ($file['caption'] ?? '');

        if (mb_strlen($caption) > 1000) {
            Navigator::replace($bot, 'wr', array_merge($this->keep($params), ['step' => 'compose', 'error' => __('msg.err.too_long', ['max' => 1000])]));

            return;
        }

        $this->save($actor, array_merge($this->draft($actor), ['kind' => $kind, 'file_id' => (string) $file['file_id'], 'text' => $caption]));
        Navigator::replace($bot, 'wr', array_merge($this->keep($params), ['step' => 'confirm']));
    }

    // ---- Yuborish -------------------------------------------------------------------------------

    private function go(Nutgram $bot, User $actor, array $params, array $d): void
    {
        $r = $this->resolve($actor, $params, $d);
        $users = $r['users'];
        $back = array_merge($this->keep($params), ['step' => 'confirm']);

        if ($users->isEmpty() || ($d['kind'] === 'text' && $d['text'] === '')) {
            Navigator::replace($bot, 'wr', $back);

            return;
        }

        if (($wait = Messaging::tooMany($actor)) !== null) {
            Navigator::replace($bot, 'wr', array_merge($back, ['error' => __('msg.err.rate', ['min' => max(1, (int) ceil($wait / 60))])]));

            return;
        }

        $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $body = $d['text'] !== '' ? $d['text'] : null;
        $lock = 'wr:go:' . $actor->id . ':' . md5(json_encode([$ids, $d['kind'], $d['file_id'], $d['text'], $params['reply'] ?? 0]));

        // Ikki marta bosilsa, ikkinchisi e'tiborsiz qoladi.
        if (! Cache::add($lock, true, now()->addSeconds(30))) {
            Navigator::refresh($bot);

            return;
        }

        self::forget($actor);

        if ($users->count() > MessageTargets::SYNC_LIMIT) {
            Messaging::hit($actor);
            SendMessages::dispatch($actor->id, $ids, $d['kind'], $d['file_id'], $body);
            Navigator::replace($bot, 'wr', ['step' => 'done', 'queued' => $users->count()]);

            return;
        }

        $result = Messaging::send($bot, $actor, $users, $d['kind'], $d['file_id'], $body, $r['parent']);

        if ($result['sent'] > 0) {
            Messaging::hit($actor);
        }

        Navigator::replace($bot, 'wr', [
            'step' => 'done',
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'label' => $this->label($users),
        ]);
    }

    // ---- Ichki ----------------------------------------------------------------------------------

    /**
     * Qabul qiluvchilar HAR SAFAR bazadan qayta tekshiriladi (rol/bog'lanish o'zgargan bo'lishi mumkin).
     *
     * @return array{users: Collection<int, User>, parent: ?Message}
     */
    private function resolve(User $actor, array $params, array $draft): array
    {
        if (! empty($params['reply'])) {
            $parent = Message::query()->where('receiver_id', $actor->id)->find((int) $params['reply']);
            $to = $parent?->sender;

            return ['users' => ($to !== null && Messaging::canWrite($actor, $to)) ? collect([$to]) : collect(), 'parent' => $parent];
        }

        if (! empty($params['to'])) {
            $to = User::query()->find((int) $params['to']);

            return ['users' => ($to !== null && Messaging::canWrite($actor, $to)) ? collect([$to]) : collect(), 'parent' => null];
        }

        $ids = array_slice(array_values(array_unique($draft['ids'])), 0, MessageTargets::MAX);

        return [
            'users' => $ids === [] ? collect() : MessageTargets::pool($actor)->whereIn('id', $ids)->orderBy('full_name')->orderBy('id')->get(),
            'parent' => null,
        ];
    }

    /** "Ali, Vali, Soli va yana 5 ta" ko'rinishidagi qisqa yorliq (HTML-xavfsiz). */
    private function label(Collection $users): string
    {
        $names = $users->take(3)->map(fn (User $u) => e($u->displayName()))->implode(', ');
        $more = $users->count() - 3;

        return $more > 0 ? __('msg.wr.label_more', ['names' => $names, 'n' => $more]) : $names;
    }

    private function icon(User $user): string
    {
        return match ($user->role->value) {
            'admin' => '👑',
            'moderator' => '🛡',
            'teacher' => '🎓',
            default => '🎒',
        };
    }
}
