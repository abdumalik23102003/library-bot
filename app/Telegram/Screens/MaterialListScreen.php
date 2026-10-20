<?php

namespace App\Telegram\Screens;

use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\MaterialDelivery;
use App\Telegram\Support\Pager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Materiallar ro'yxati (qidiruv va yuklab olinganlar uchun umumiy asos):
 * sahifalash, raqam bosilganda materialni yuborish. Callback: {kalit}:p:{sahifa}, {kalit}:o:{materialId}.
 */
abstract class MaterialListScreen extends Screen
{
    /** Ekran kaliti (ScreenRegistry dagi). */
    abstract protected function key(): string;

    /** Foydalanuvchi ko'ra oladigan materiallar so'rovi (tartiblangan). */
    abstract protected function query(User $user, array $params): Builder;

    abstract protected function title(array $params, int $total, int $page, int $pages): string;

    abstract protected function empty(array $params): string;

    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();

        [$items, $page, $pages, $total] = Pager::slice(
            $this->query($user, $params)->with('owner'),
            (int) ($params['page'] ?? 1),
        );

        // O'quvchi uchun: ochib bo'lmaydigan (kirishi yo'q protected) materiallar 🔒 bilan belgilanadi.
        $openable = $user->isStudent()
            ? Material::query()->visibleTo($user)->whereIn('id', $items->pluck('id'))->pluck('id')->all()
            : null;

        $lines = [];

        if (! empty($params['flash'])) {
            $lines[] = __("search.flash.{$params['flash']}");
            $lines[] = '';
        }

        if ($total === 0) {
            $lines[] = $this->empty($params);

            return Page::make(implode("\n", $lines), back: true);
        }

        $lines[] = $this->title($params, $total, $page, $pages);
        $lines[] = '';

        $buttons = [];

        /** @var Material $material */
        foreach ($items as $i => $material) {
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;
            $lock = ($openable !== null && ! in_array($material->id, $openable, true)) ? '🔒 ' : '';

            $lines[] = sprintf(
                '%d. %s%s %s — %s',
                $n,
                $lock,
                Str::before($material->type->label(), ' '),
                e(Str::limit($material->title ?: __('mats.untitled'), 50)),
                e($material->owner?->displayName() ?? __('search.system')),
            );

            $buttons[] = [(string) $n, "{$this->key()}:o:{$material->id}"];
        }

        $lines[] = '';
        $lines[] = __('search.pick_hint');

        if ($openable !== null && count($openable) < $items->count()) {
            $lines[] = __('search.lock_hint');
        }

        $rows = array_chunk($buttons, 4);

        if ($nav = Pager::navRow("{$this->key()}:p", $page, $pages)) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $current = Navigator::current($bot);

        // Eskirgan tugma: boshqa ekranda turgan bo'lsak, joriy ekranni qayta chizamiz.
        if (($current['screen'] ?? null) !== $this->key()) {
            Navigator::refresh($bot);

            return;
        }

        $params = $current['params'] ?? [];
        $keep = isset($params['q']) ? ['q' => $params['q']] : [];

        match ($action) {
            'p' => Navigator::replace($bot, $this->key(), $keep + ['page' => max(1, (int) ($args[0] ?? 1))]),
            'o' => $this->open($bot, $keep, (int) ($params['page'] ?? 1), (int) ($args[0] ?? 0)),
            default => Navigator::refresh($bot),
        };
    }

    private function open(Nutgram $bot, array $keep, int $page, int $materialId): void
    {
        $result = MaterialDelivery::deliver($bot, CurrentUser::get(), $materialId);

        if ($result === 'locked') {
            $ownerId = Material::query()->whereKey($materialId)->value('owner_id');

            if ($ownerId !== null) {
                // Ustoz kartochkasi: qo'shilish va himoyalangan kirish so'rovi shu yerda.
                Navigator::go($bot, 'tch', ['id' => (int) $ownerId, 'flash' => 'locked']);

                return;
            }
        }

        Navigator::replace($bot, $this->key(), $keep + ['page' => $page, 'flash' => $result]);
    }
}
