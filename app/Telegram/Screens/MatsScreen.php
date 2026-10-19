<?php

namespace App\Telegram\Screens;

use App\Models\Material;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Ustozning o'z materiallari: sahifalangan ro'yxat. Raqam bosilsa "mat" kartochkasi ochiladi.
 */
class MatsScreen extends Screen
{
    private const PER_PAGE = 8;

    private const ACCESS_ICONS = ['public' => '🌐', 'protected' => '🔐', 'private' => '🔒'];

    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();
        $query = Material::query()->where('owner_id', $user->id);
        $total = (clone $query)->count();

        if ($total === 0) {
            return Page::make(__('mats.list.empty'), back: true);
        }

        $pages = (int) ceil($total / self::PER_PAGE);
        $page = min(max(1, (int) ($params['page'] ?? 1)), $pages);
        $items = $query->with('category')->latest('id')->forPage($page, self::PER_PAGE)->get();

        $lines = [__('mats.list.title', ['total' => $total, 'page' => $page, 'pages' => $pages]), ''];
        $buttons = [];

        foreach ($items as $i => $material) {
            $n = ($page - 1) * self::PER_PAGE + $i + 1;

            $lines[] = sprintf(
                '%d. %s %s %s · %s',
                $n,
                Str::before($material->type->label(), ' '),
                e(Str::limit($material->title ?: __('mats.untitled'), 40)),
                self::ACCESS_ICONS[$material->access->value] ?? '',
                e($material->category?->name ?? '—'),
            );

            $buttons[] = [(string) $n, "mats:o:{$material->id}"];
        }

        $rows = array_chunk($buttons, 4);

        if ($pages > 1) {
            $rows[] = [
                ['⬅️', 'mats:p:' . max(1, $page - 1)],
                ["{$page}/{$pages}", "mats:p:{$page}"],
                ['➡️', 'mats:p:' . min($pages, $page + 1)],
            ];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        if ($action === 'p') {
            Navigator::replace($bot, 'mats', ['page' => max(1, (int) ($args[0] ?? 1))]);

            return;
        }

        if ($action === 'o') {
            $owns = Material::query()
                ->where('owner_id', CurrentUser::get()->id)
                ->whereKey((int) ($args[0] ?? 0))
                ->exists();

            $owns ? Navigator::go($bot, 'mat', ['id' => (int) $args[0]]) : Navigator::refresh($bot);

            return;
        }

        Navigator::refresh($bot);
    }
}
