<?php

namespace App\Telegram\Screens;

use App\Models\Category;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CategoryFields;
use App\Telegram\Support\CurrentUser;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Ustoz kategoriyalari ro'yxati (standart kategoriyalar faqat ko'rsatiladi).
 */
class CatsScreen extends Screen
{
    private const PER_PAGE = 8;

    public function render(Nutgram $bot, array $params): Page
    {
        $user = CurrentUser::get();

        $total = $user->categories()->count();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($params['page'] ?? 1)), $pages);

        $defaults = Category::query()->defaults()->orderBy('id')->pluck('name')->all();
        $own = $user->categories()->orderBy('name')->orderBy('id')->forPage($page, self::PER_PAGE)->get();

        $lines = [__('cat.list.title'), ''];
        $lines[] = __('cat.list.defaults', ['names' => $defaults === [] ? '—' : e(implode(', ', $defaults))]);
        $lines[] = '';
        $lines[] = $total === 0
            ? __('cat.list.empty')
            : __('cat.list.own', ['count' => $total, 'max' => CategoryFields::MAX_PER_TEACHER]);

        if (! empty($params['error'])) {
            $lines[] = '';
            $lines[] = '⚠️ ' . e($params['error']);
        }

        $rows = [];

        foreach ($own as $category) {
            $rows[] = [[
                CategoryFields::icon($category->default_access) . ' ' . Str::limit($category->name, 40),
                "cats:open:{$category->id}",
            ]];
        }

        if ($pages > 1) {
            $nav = [];

            if ($page > 1) {
                $nav[] = ['◀️', 'cats:page:' . ($page - 1)];
            }

            $nav[] = ["{$page}/{$pages}", "cats:page:{$page}"];

            if ($page < $pages) {
                $nav[] = ['▶️', 'cats:page:' . ($page + 1)];
            }

            $rows[] = $nav;
        }

        $rows[] = [[__('cat.list.new'), 'cats:new']];

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $user = CurrentUser::get();
        $top = Navigator::current($bot);

        if (! $user->isTeacher() || ($top['screen'] ?? '') !== 'cats') {
            Navigator::refresh($bot);

            return;
        }

        match ($action) {
            'page' => Navigator::replace($bot, 'cats', ['page' => max(1, (int) ($args[0] ?? 1))]),
            'open' => $this->open($bot, (int) ($args[0] ?? 0)),
            'new' => $this->startNew($bot),
            default => Navigator::refresh($bot),
        };
    }

    private function open(Nutgram $bot, int $id): void
    {
        $exists = CurrentUser::get()->categories()->whereKey($id)->exists();

        $exists
            ? Navigator::go($bot, 'cat', ['id' => $id])
            : Navigator::refresh($bot);
    }

    private function startNew(Nutgram $bot): void
    {
        $user = CurrentUser::get();

        if (CategoryFields::limitReached($user)) {
            Navigator::replace($bot, 'cats', [
                'error' => __('cat.err.limit', ['max' => CategoryFields::MAX_PER_TEACHER]),
            ]);

            return;
        }

        Navigator::go($bot, 'catnew', ['step' => 'name', 'data' => []]);
        Navigator::awaitInput($bot, 'name');
    }
}
