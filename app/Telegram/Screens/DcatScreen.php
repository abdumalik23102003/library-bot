<?php

namespace App\Telegram\Screens;

use App\Enums\Access;
use App\Models\Category;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CategoryFields;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\DefaultCategories;
use SergiX44\Nutgram\Nutgram;

/**
 * Standart kategoriya kartochkasi (admin/moderator): nom, tavsif, kirish, o'chirish.
 * Callback: dcat:{ask:name|description, cancel, access, setaccess:{v}, confirm, delete}. params: id, ask, mode, flash, error.
 */
class DcatScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $cat = $this->find((int) ($params['id'] ?? 0));

        if (! CurrentUser::get()->isStaff() || $cat === null) {
            return Page::make(__('dcat.not_found'), back: true);
        }

        $count = $cat->materials()->count();
        $lines = ['📂 <b>' . e($cat->name) . '</b>', ''];
        $lines[] = __('cat.card.description') . ': ' . (filled($cat->description) ? e($cat->description) : __('reg.empty'));
        $lines[] = __('cat.card.access') . ': <b>' . e($cat->default_access->label()) . '</b>';
        $lines[] = __('cat.card.materials') . ': <b>' . $count . '</b>';

        if (! DefaultCategories::canDelete($cat)) {
            $lines[] = '';
            $lines[] = __('dcat.card.system');
        }

        $lines[] = '';

        if (! empty($params['flash'])) {
            $lines[] = $params['flash'] === 'system' ? __('dcat.flash.system') : __("cat.flash.{$params['flash']}");
            $lines[] = '';
        }

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        $ask = $params['ask'] ?? null;
        $mode = $params['mode'] ?? null;

        if (in_array($ask, ['name', 'description'], true)) {
            $lines[] = __("cat.ask.{$ask}");

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'dcat:cancel']]]);
        }

        if ($mode === 'access') {
            $lines[] = __('cat.access_pick');
            $rows = [];

            foreach (Access::cases() as $case) {
                $rows[] = [[($cat->default_access === $case ? '✅ ' : '') . $case->label(), 'dcat:setaccess:' . $case->value]];
            }

            $rows[] = [[__('bot.cancel'), 'dcat:cancel']];

            return Page::make(implode("\n", $lines), $rows);
        }

        if ($mode === 'delete') {
            $lines[] = __('cat.delete_confirm', ['name' => e($cat->name), 'count' => $count]);

            return Page::make(implode("\n", $lines), [[
                [__('cat.btn_delete_yes'), 'dcat:delete'],
                [__('cat.btn_delete_no'), 'dcat:cancel'],
            ]]);
        }

        $rows = [
            [[__('cat.card.btn_name'), 'dcat:ask:name'], [__('cat.card.btn_description'), 'dcat:ask:description']],
            [[__('cat.card.btn_access'), 'dcat:access']],
        ];

        if (DefaultCategories::canDelete($cat)) {
            $rows[] = [[__('cat.card.btn_delete'), 'dcat:confirm']];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $actor = CurrentUser::get();
        $top = Navigator::current($bot);

        if (! $actor->isStaff() || ($top['screen'] ?? '') !== 'dcat') {
            Navigator::refresh($bot);

            return;
        }

        $id = (int) ($top['params']['id'] ?? 0);
        $cat = $this->find($id);

        if ($cat === null) {
            Navigator::back($bot);

            return;
        }

        match ($action) {
            'ask' => $this->ask($bot, $id, (string) ($args[0] ?? '')),
            'cancel' => Navigator::replace($bot, 'dcat', ['id' => $id]),
            'access' => Navigator::replace($bot, 'dcat', ['id' => $id, 'mode' => 'access']),
            'setaccess' => $this->setAccess($bot, $cat, (string) ($args[0] ?? '')),
            'confirm' => DefaultCategories::canDelete($cat)
                ? Navigator::replace($bot, 'dcat', ['id' => $id, 'mode' => 'delete'])
                : Navigator::replace($bot, 'dcat', ['id' => $id, 'flash' => 'system']),
            'delete' => DefaultCategories::delete($actor, $cat)
                ? Navigator::back($bot)
                : Navigator::replace($bot, 'dcat', ['id' => $id, 'flash' => 'system']),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $cat = $this->find($id);

        if ($cat === null || ! in_array($field, ['name', 'description'], true)) {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = $field === 'name'
            ? DefaultCategories::name($text, $cat->id)
            : CategoryFields::description($text);

        if (! $ok) {
            Navigator::replace($bot, 'dcat', ['id' => $id, 'ask' => $field, 'error' => $value]);
            Navigator::awaitInput($bot, $field);

            return;
        }

        DefaultCategories::update(CurrentUser::get(), $cat, $field, $value);

        Navigator::replace($bot, 'dcat', ['id' => $id, 'flash' => 'saved']);
    }

    private function ask(Nutgram $bot, int $id, string $field): void
    {
        if (! in_array($field, ['name', 'description'], true)) {
            Navigator::refresh($bot);

            return;
        }

        Navigator::replace($bot, 'dcat', ['id' => $id, 'ask' => $field]);
        Navigator::awaitInput($bot, $field);
    }

    private function setAccess(Nutgram $bot, Category $cat, string $value): void
    {
        $access = Access::tryFrom($value);

        if ($access === null) {
            Navigator::refresh($bot);

            return;
        }

        DefaultCategories::update(CurrentUser::get(), $cat, 'default_access', $access);

        Navigator::replace($bot, 'dcat', ['id' => $cat->id, 'flash' => 'saved']);
    }

    private function find(int $id): ?Category
    {
        return Category::query()->defaults()->find($id);
    }
}
