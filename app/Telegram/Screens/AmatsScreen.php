<?php

namespace App\Telegram\Screens;

use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Admin/moderator uchun barcha materiallar (moderator faqat public). Raqam -> "mat" kartochkasi (CRUD).
 * Callback: amats:p:{sahifa}, amats:o:{id}, amats:s (qidirish), amats:x (tozalash), amats:cancel. params: owner, q, page.
 */
class AmatsScreen extends Screen
{
    private const ACCESS_ICONS = ['public' => '🌐', 'protected' => '🔐', 'private' => '🔒'];

    public function render(Nutgram $bot, array $params): Page
    {
        $actor = CurrentUser::get();

        if (! $actor->isStaff()) {
            return Page::make(__('adm.err.forbidden'), back: true);
        }

        if (($params['ask'] ?? null) === 'q') {
            return Page::make(__('adm.amats.ask_q'), [[[__('bot.cancel'), 'amats:cancel']]]);
        }

        $q = trim((string) ($params['q'] ?? ''));
        $owner = ! empty($params['owner']) ? User::find((int) $params['owner']) : null;

        $query = $this->base($actor, $params)->with(['owner', 'category'])->latest('id');

        [$items, $page, $pages, $total] = Pager::slice($query, (int) ($params['page'] ?? 1));

        $tools = [[__('adm.usrs.btn_search'), 'amats:s']];

        if ($q !== '') {
            $tools[] = [__('adm.usrs.btn_clear'), 'amats:x'];
        }

        if ($total === 0) {
            return Page::make(__('adm.amats.empty'), [$tools], back: true);
        }

        $vars = ['total' => $total, 'page' => $page, 'pages' => $pages];
        $lines = [match (true) {
            $q !== '' => __('adm.amats.title_q', $vars + ['q' => e($q)]),
            $owner !== null => __('adm.amats.title_owner', $vars + ['owner' => e($owner->displayName())]),
            default => __('adm.amats.title', $vars),
        }];

        if ($actor->isModerator()) {
            $lines[] = __('adm.amats.moderator_note');
        }

        $lines[] = '';
        $buttons = [];

        /** @var Material $material */
        foreach ($items as $i => $material) {
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;

            $lines[] = sprintf(
                '%d. %s %s %s — %s',
                $n,
                Str::before($material->type->label(), ' '),
                e(Str::limit($material->title ?: __('mats.untitled'), 40)),
                self::ACCESS_ICONS[$material->access->value] ?? '',
                e($material->owner?->displayName() ?? __('adm.system')),
            );
            $buttons[] = [(string) $n, "amats:o:{$material->id}"];
        }

        $lines[] = '';
        $lines[] = __('adm.amats.pick_hint');

        $rows = [$tools, ...array_chunk($buttons, 4)];

        if ($nav = Pager::navRow('amats:p', $page, $pages)) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $current = Navigator::current($bot);

        if (! $actor->isStaff() || ($current['screen'] ?? '') !== 'amats') {
            Navigator::refresh($bot);

            return;
        }

        $params = $current['params'] ?? [];
        $keep = array_filter([
            'owner' => $params['owner'] ?? null,
            'q' => filled($params['q'] ?? null) ? $params['q'] : null,
        ]);
        $id = (int) ($args[0] ?? 0);

        match ($action) {
            'p' => Navigator::replace($bot, 'amats', $keep + ['page' => max(1, $id)]),
            'o' => $this->base($actor, $params)->whereKey($id)->exists()
                ? Navigator::go($bot, 'mat', ['id' => $id])
                : Navigator::refresh($bot),
            's' => $this->ask($bot, $keep),
            'x' => Navigator::replace($bot, 'amats', array_diff_key($keep, ['q' => 1])),
            'cancel' => Navigator::replace($bot, 'amats', $keep),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        if ($field !== 'q') {
            Navigator::refresh($bot);

            return;
        }

        $q = mb_substr(trim($text), 0, 60);
        $keep = array_filter(['owner' => $params['owner'] ?? null]);

        Navigator::replace($bot, 'amats', $keep + ($q !== '' && $q !== '-' ? ['q' => $q] : []));
    }

    private function ask(Nutgram $bot, array $keep): void
    {
        Navigator::replace($bot, 'amats', array_diff_key($keep, ['q' => 1]) + ['ask' => 'q']);
        Navigator::awaitInput($bot, 'q');
    }

    /** Ko'rinadigan materiallar: admin hammasi, moderator faqat public; ixtiyoriy ega va qidiruv. */
    private function base(User $actor, array $params): Builder
    {
        $query = Material::query();

        if ($actor->isModerator()) {
            $query->where('access', 'public');
        }

        if (! empty($params['owner'])) {
            $query->where('owner_id', (int) $params['owner']);
        }

        if (filled($params['q'] ?? null)) {
            $query->search((string) $params['q']);
        }

        return $query;
    }
}
