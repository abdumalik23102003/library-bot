<?php

namespace App\Telegram\Screens;

use App\Models\Category;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CategoryFields;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\DefaultCategories;
use App\Telegram\Support\Pager;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/** Standart kategoriyalar ro'yxati (admin/moderator). Callback: dcats:p:{sahifa}, dcats:o:{id}, dcats:new. */
class DcatsScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        if (! CurrentUser::get()->isStaff()) {
            return Page::make(__('dcat.forbidden'), back: true);
        }

        [$items, $page, $pages, $total] = Pager::slice(DefaultCategories::query(), (int) ($params['page'] ?? 1));

        $lines = [__('dcat.list.title', ['total' => $total, 'page' => $page, 'pages' => $pages]), ''];
        $buttons = [];

        /** @var Category $category */
        foreach ($items as $i => $category) {
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;

            $lines[] = sprintf(
                '%d. %s %s%s',
                $n,
                CategoryFields::icon($category->default_access),
                e(Str::limit($category->name, 40)),
                DefaultCategories::canDelete($category) ? '' : ' 🔒',
            );
            $buttons[] = [(string) $n, "dcats:o:{$category->id}"];
        }

        $lines[] = '';
        $lines[] = __('dcat.list.hint');
        $lines[] = __('dcat.list.pick_hint');

        $rows = array_chunk($buttons, 4);
        $rows[] = [[__('dcat.list.new'), 'dcats:new']];

        if ($nav = Pager::navRow('dcats:p', $page, $pages)) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        if (! CurrentUser::get()->isStaff() || (Navigator::current($bot)['screen'] ?? '') !== 'dcats') {
            Navigator::refresh($bot);

            return;
        }

        $id = (int) ($args[0] ?? 0);

        match ($action) {
            'p' => Navigator::replace($bot, 'dcats', ['page' => max(1, $id)]),
            'o' => DefaultCategories::query()->whereKey($id)->exists()
                ? Navigator::go($bot, 'dcat', ['id' => $id])
                : Navigator::refresh($bot),
            'new' => $this->startNew($bot),
            default => Navigator::refresh($bot),
        };
    }

    private function startNew(Nutgram $bot): void
    {
        Navigator::go($bot, 'dcatnew', ['step' => 'name', 'data' => []]);
        Navigator::awaitInput($bot, 'name');
    }
}
