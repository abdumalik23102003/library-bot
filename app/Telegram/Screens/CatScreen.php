<?php

namespace App\Telegram\Screens;

use App\Enums\Access;
use App\Models\Category;
use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CategoryFields;
use App\Telegram\Support\CurrentUser;
use Illuminate\Support\Facades\DB;
use SergiX44\Nutgram\Nutgram;

/**
 * Bitta kategoriya kartochkasi: tahrirlash, kirish huquqi, o'chirish.
 * params: id, mode (access|delete), ask (name|description), flash (created|saved), error
 */
class CatScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $cat = $this->find(CurrentUser::get(), (int) ($params['id'] ?? 0));

        if ($cat === null) {
            return Page::make(__('cat.not_found'), back: true);
        }

        $count = $cat->materials()->count();

        $lines = ['📂 <b>' . e($cat->name) . '</b>', ''];
        $lines[] = __('cat.card.description') . ': ' . (filled($cat->description) ? e($cat->description) : __('reg.empty'));
        $lines[] = __('cat.card.access') . ': <b>' . e($cat->default_access->label()) . '</b>';
        $lines[] = __('cat.card.materials') . ': <b>' . $count . '</b>';
        $lines[] = '';

        if (! empty($params['flash'])) {
            $lines[] = __("cat.flash.{$params['flash']}");
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

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'cat:cancel']]]);
        }

        if ($mode === 'access') {
            $lines[] = __('cat.access_pick');
            $rows = [];

            foreach (Access::cases() as $case) {
                $rows[] = [[
                    ($cat->default_access === $case ? '✅ ' : '') . $case->label(),
                    'cat:setaccess:' . $case->value,
                ]];
            }

            $rows[] = [[__('bot.cancel'), 'cat:cancel']];

            return Page::make(implode("\n", $lines), $rows);
        }

        if ($mode === 'delete') {
            $lines[] = __('cat.delete_confirm', ['name' => e($cat->name), 'count' => $count]);

            return Page::make(implode("\n", $lines), [[
                [__('cat.btn_delete_yes'), 'cat:delete'],
                [__('cat.btn_delete_no'), 'cat:cancel'],
            ]]);
        }

        return Page::make(implode("\n", $lines), [
            [[__('cat.card.btn_name'), 'cat:ask:name'], [__('cat.card.btn_description'), 'cat:ask:description']],
            [[__('cat.card.btn_access'), 'cat:access']],
            [[__('cat.card.btn_delete'), 'cat:confirm']],
        ], back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $user = CurrentUser::get();
        $top = Navigator::current($bot);

        if (! $user->isTeacher() || ($top['screen'] ?? '') !== 'cat') {
            Navigator::refresh($bot);

            return;
        }

        $id = (int) ($top['params']['id'] ?? 0);
        $cat = $this->find($user, $id);

        if ($cat === null) {
            Navigator::back($bot);

            return;
        }

        match ($action) {
            'ask' => $this->ask($bot, $id, (string) ($args[0] ?? '')),
            'cancel' => Navigator::replace($bot, 'cat', ['id' => $id]),
            'access' => Navigator::replace($bot, 'cat', ['id' => $id, 'mode' => 'access']),
            'setaccess' => $this->setAccess($bot, $cat, (string) ($args[0] ?? '')),
            'confirm' => Navigator::replace($bot, 'cat', ['id' => $id, 'mode' => 'delete']),
            'delete' => $this->delete($bot, $cat),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $user = CurrentUser::get();
        $id = (int) ($params['id'] ?? 0);
        $cat = $this->find($user, $id);

        if ($cat === null || ! in_array($field, ['name', 'description'], true)) {
            Navigator::refresh($bot);

            return;
        }

        [$ok, $value] = $field === 'name'
            ? CategoryFields::name($user, $text, $cat->id)
            : CategoryFields::description($text);

        if (! $ok) {
            Navigator::replace($bot, 'cat', ['id' => $id, 'ask' => $field, 'error' => $value]);
            Navigator::awaitInput($bot, $field);

            return;
        }

        $cat->{$field} = $value;
        $cat->save();

        Navigator::replace($bot, 'cat', ['id' => $id, 'flash' => 'saved']);
    }

    private function ask(Nutgram $bot, int $id, string $field): void
    {
        if (! in_array($field, ['name', 'description'], true)) {
            Navigator::refresh($bot);

            return;
        }

        Navigator::replace($bot, 'cat', ['id' => $id, 'ask' => $field]);
        Navigator::awaitInput($bot, $field);
    }

    private function setAccess(Nutgram $bot, Category $cat, string $value): void
    {
        $access = Access::tryFrom($value);

        if ($access === null) {
            Navigator::refresh($bot);

            return;
        }

        $cat->default_access = $access;
        $cat->save();

        Navigator::replace($bot, 'cat', ['id' => $cat->id, 'flash' => 'saved']);
    }

    private function delete(Nutgram $bot, Category $cat): void
    {
        DB::transaction(function () use ($cat) {
            Material::query()->where('category_id', $cat->id)->update(['category_id' => null]);
            $cat->delete();
        });

        Navigator::back($bot);
    }

    private function find(User $user, int $id): ?Category
    {
        return Category::query()->where('owner_id', $user->id)->find($id);
    }
}
