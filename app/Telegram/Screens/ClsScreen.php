<?php

namespace App\Telegram\Screens;

use App\Models\Classroom;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\ClassroomFields;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use SergiX44\Nutgram\Nutgram;

/**
 * Ustozning sinflari ro'yxati va yangi sinf yaratish.
 * Callback: cls:p:{sahifa}, cls:o:{id}, cls:new, cls:cancel. params: page, ask (name), error.
 */
class ClsScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = CurrentUser::get();

        if (! $teacher->isTeacher() || ! $teacher->isActive()) {
            return Page::make(__('cls.err.forbidden'), back: true);
        }

        // Yangi sinf nomini kutish
        if (($params['ask'] ?? null) === 'name') {
            $lines = [];

            if (! empty($params['error'])) {
                $lines[] = '⚠️ ' . e($params['error']);
                $lines[] = '';
            }

            $lines[] = __('cls.ask.name');

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), 'cls:cancel']]]);
        }

        $query = Classroom::query()
            ->where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('name')
            ->orderBy('id');

        [$items, $page, $pages, $total] = Pager::slice($query, (int) ($params['page'] ?? 1));

        $lines = [];

        if (! empty($params['error'])) {
            $lines[] = '⚠️ ' . e($params['error']);
            $lines[] = '';
        }

        if ($total === 0) {
            $lines[] = __('cls.list.empty');

            return Page::make(implode("\n", $lines), [[[__('cls.list.new'), 'cls:new']]], back: true);
        }

        $lines[] = __('cls.list.title', ['total' => $total, 'page' => $page, 'pages' => $pages]);
        $lines[] = '';

        $buttons = [];

        foreach ($items as $i => $classroom) {
            $n = ($page - 1) * Pager::PER_PAGE + $i + 1;

            $lines[] = sprintf('%d. %s — %s', $n, e($classroom->name), __('cls.list.members', ['n' => $classroom->students_count]));
            $buttons[] = [(string) $n, "cls:o:{$classroom->id}"];
        }

        $lines[] = '';
        $lines[] = __('cls.list.pick_hint');

        $rows = array_chunk($buttons, 4);

        if ($nav = Pager::navRow('cls:p', $page, $pages)) {
            $rows[] = $nav;
        }

        $rows[] = [[__('cls.list.new'), 'cls:new']];

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    /** Ekran chizilgach, sinf nomini kutishni yoqadi. */
    public function mounted(Nutgram $bot, array $params): void
    {
        if (($params['ask'] ?? null) === 'name') {
            Navigator::awaitInput($bot, 'name');
        }
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $teacher = CurrentUser::get();
        $top = Navigator::current($bot);

        // Eskirgan tugma yoki ustoz bo'lmagan: joriy ekranni qayta chizamiz.
        if (! $teacher->isTeacher() || ! $teacher->isActive() || ($top['screen'] ?? '') !== 'cls') {
            Navigator::refresh($bot);

            return;
        }

        $asking = ($top['params']['ask'] ?? null) === 'name';

        match (true) {
            $action === 'p' && ! $asking => Navigator::replace($bot, 'cls', ['page' => max(1, (int) ($args[0] ?? 1))]),
            $action === 'o' && ! $asking => $this->open($bot, (int) ($args[0] ?? 0)),
            $action === 'new' && ! $asking => $this->startNew($bot),
            $action === 'cancel' && $asking => Navigator::back($bot),
            default => Navigator::refresh($bot),
        };
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $teacher = CurrentUser::get();

        if ($field !== 'name' || ! $teacher->isTeacher() || ! $teacher->isActive()) {
            Navigator::refresh($bot);

            return;
        }

        if (ClassroomFields::limitReached($teacher)) {
            Navigator::back($bot);

            return;
        }

        [$ok, $value] = ClassroomFields::name($teacher, $text);

        if (! $ok) {
            Navigator::replace($bot, 'cls', ['ask' => 'name', 'error' => $value]);

            return;
        }

        $classroom = Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => $value]);

        // Nom kutish holati almashtiriladi, ro'yxat stekda qoladi: "Orqaga" ro'yxatga qaytaradi.
        Navigator::replace($bot, 'cl', ['id' => $classroom->id, 'flash' => 'created']);
    }

    private function open(Nutgram $bot, int $id): void
    {
        $owned = Classroom::query()->where('teacher_id', CurrentUser::get()->id)->whereKey($id)->exists();

        $owned ? Navigator::go($bot, 'cl', ['id' => $id]) : Navigator::refresh($bot);
    }

    private function startNew(Nutgram $bot): void
    {
        if (ClassroomFields::limitReached(CurrentUser::get())) {
            Navigator::replace($bot, 'cls', [
                'error' => __('cls.err.limit', ['max' => ClassroomFields::MAX_PER_TEACHER]),
            ]);

            return;
        }

        Navigator::go($bot, 'cls', ['ask' => 'name']);
    }
}
