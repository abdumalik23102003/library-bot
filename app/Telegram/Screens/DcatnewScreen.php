<?php

namespace App\Telegram\Screens;

use App\Enums\Access;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CategoryFields;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\DefaultCategories;
use SergiX44\Nutgram\Nutgram;

/** Yangi standart kategoriya (admin/moderator): nom -> tavsif -> kirish. params: step (name|description|access), data, error. */
class DcatnewScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $step = $params['step'] ?? 'name';
        $data = $params['data'] ?? [];

        $lines = [__('cat.new.title') . ' · ' . __("cat.new.step_{$step}"), ''];

        if (isset($data['name'])) {
            $lines[] = __('cat.new.name') . ': <b>' . e($data['name']) . '</b>';
        }

        if ($step === 'access' && ! empty($data['description'])) {
            $lines[] = __('cat.new.description') . ': ' . e($data['description']);
        }

        if (isset($data['name'])) {
            $lines[] = '';
        }

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        $cancel = [__('bot.cancel'), 'dcatnew:cancel'];

        if ($step === 'description') {
            $lines[] = __('cat.new.ask_description');

            return Page::make(implode("\n", $lines), [[[__('cat.new.skip'), 'dcatnew:skip']], [$cancel]]);
        }

        if ($step === 'access') {
            $lines[] = __('cat.new.ask_access');
            $rows = [];

            foreach (Access::cases() as $case) {
                $rows[] = [[$case->label(), 'dcatnew:access:' . $case->value]];
            }

            $rows[] = [$cancel];

            return Page::make(implode("\n", $lines), $rows);
        }

        $lines[] = __('cat.ask.name');

        return Page::make(implode("\n", $lines), [[$cancel]]);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $top = Navigator::current($bot);

        if (! CurrentUser::get()->isStaff() || ($top['screen'] ?? '') !== 'dcatnew') {
            Navigator::refresh($bot);

            return;
        }

        $step = $top['params']['step'] ?? 'name';
        $data = $top['params']['data'] ?? [];

        match (true) {
            $action === 'cancel' => Navigator::back($bot),
            $action === 'skip' && $step === 'description' => Navigator::replace($bot, 'dcatnew', ['step' => 'access', 'data' => $data]),
            $action === 'access' && $step === 'access' => $this->create($bot, $data, (string) ($args[0] ?? '')),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $data = $params['data'] ?? [];

        if ($field === 'name') {
            [$ok, $value] = DefaultCategories::name($text);

            if (! $ok) {
                Navigator::replace($bot, 'dcatnew', ['step' => 'name', 'data' => $data, 'error' => $value]);
                Navigator::awaitInput($bot, 'name');

                return;
            }

            $data['name'] = $value;
            Navigator::replace($bot, 'dcatnew', ['step' => 'description', 'data' => $data]);
            Navigator::awaitInput($bot, 'description');

            return;
        }

        if ($field === 'description') {
            [$ok, $value] = CategoryFields::description($text);

            if (! $ok) {
                Navigator::replace($bot, 'dcatnew', ['step' => 'description', 'data' => $data, 'error' => $value]);
                Navigator::awaitInput($bot, 'description');

                return;
            }

            $data['description'] = $value;
            Navigator::replace($bot, 'dcatnew', ['step' => 'access', 'data' => $data]);

            return;
        }

        Navigator::refresh($bot);
    }

    private function create(Nutgram $bot, array $data, string $value): void
    {
        $access = Access::tryFrom($value);

        if ($access === null || empty($data['name'])) {
            Navigator::refresh($bot);

            return;
        }

        $category = DefaultCategories::create(CurrentUser::get(), $data['name'], $data['description'] ?? null, $access);

        Navigator::replace($bot, 'dcat', ['id' => $category->id, 'flash' => 'created']);
    }
}
