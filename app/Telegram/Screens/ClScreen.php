<?php

namespace App\Telegram\Screens;

use App\Models\Classroom;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\ClassroomFields;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use App\Telegram\Support\ShareTargets;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Bitta sinf: kartochka, a'zolarni boshqarish, nom/tavsifni tahrirlash, o'chirish.
 * Callback: cl:o:{id}, cl:m:{id}, cl:mp:{sahifa}:{id}, cl:t:{id}:{o'quvchiId}, cl:ask:{id}:{maydon}, cl:del:{id}, cl:delyes:{id}, cl:cancel:{id}.
 * params: id, view (members), page, ask (name|description), confirm, flash, error.
 */
class ClScreen extends Screen
{
    private const FIELDS = ['name', 'description'];

    // ---- Ko'rinish --------------------------------------------------------------------------

    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = CurrentUser::get();
        $classroom = $this->find($teacher, (int) ($params['id'] ?? 0));

        if ($classroom === null) {
            return Page::make(__('cls.err.not_found'), back: true);
        }

        $id = $classroom->id;
        $card = $this->card($classroom);

        if (! empty($params['flash'])) {
            $card = __("cls.flash.{$params['flash']}") . "\n\n" . $card;
        }

        // Nom yoki tavsif kutish
        if (($ask = $params['ask'] ?? null) !== null) {
            $lines = [$card, ''];

            if (! empty($params['error'])) {
                $lines[] = '⚠️ ' . e($params['error']);
            }

            $lines[] = __("cls.ask.{$ask}");

            return Page::make(implode("\n", $lines), [[[__('bot.cancel'), "cl:cancel:{$id}"]]]);
        }

        // A'zolarni boshqarish
        if (($params['view'] ?? null) === 'members') {
            return $this->membersPage($teacher, $classroom, (int) ($params['page'] ?? 1));
        }

        // O'chirishni tasdiqlash
        if (! empty($params['confirm'])) {
            return Page::make(__('cls.delete_confirm', ['name' => e($classroom->name)]), [[
                [__('cls.btn.delete_yes'), "cl:delyes:{$id}"],
                [__('cls.btn.delete_no'), "cl:cancel:{$id}"],
            ]]);
        }

        return Page::make($card, [
            [[__('cls.btn.members', ['n' => $classroom->students_count]), "cl:m:{$id}"]],
            [
                [__('cls.btn.name'), "cl:ask:{$id}:name"],
                [__('cls.btn.description'), "cl:ask:{$id}:description"],
            ],
            [[__('cls.btn.delete'), "cl:del:{$id}"]],
        ], back: true);
    }

    /** Ekran chizilgach, kerak bo'lsa matn kutishni yoqadi. */
    public function mounted(Nutgram $bot, array $params): void
    {
        $ask = $params['ask'] ?? null;

        if (in_array($ask, self::FIELDS, true)) {
            Navigator::awaitInput($bot, $ask);
        }
    }

    // ---- Tugmalar ---------------------------------------------------------------------------

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $teacher = CurrentUser::get();
        $id = (int) ($action === 'mp' ? ($args[1] ?? 0) : ($args[0] ?? 0));
        $top = Navigator::current($bot);

        // Eskirgan tugma (boshqa ekran yoki boshqa sinf) yoki sinf endi yo'q.
        if (($top['screen'] ?? '') !== 'cl'
            || (int) ($top['params']['id'] ?? 0) !== $id
            || $this->find($teacher, $id) === null) {
            Navigator::refresh($bot);

            return;
        }

        $classroom = $this->find($teacher, $id);

        switch ($action) {
            case 'm':
                $this->show($bot, $id, ['view' => 'members', 'page' => 1]);
                break;

            case 'mp':
                $this->show($bot, $id, ['view' => 'members', 'page' => max(1, (int) ($args[0] ?? 1))]);
                break;

            case 't':
                $studentId = (int) ($args[1] ?? 0);

                if (ShareTargets::students($teacher)->whereKey($studentId)->exists()) {
                    $classroom->students()->toggle([$studentId]);
                }

                $this->show($bot, $id, ['view' => 'members', 'page' => (int) ($top['params']['page'] ?? 1)]);
                break;

            case 'ask':
                $field = (string) ($args[1] ?? '');
                $this->show($bot, $id, in_array($field, self::FIELDS, true) ? ['ask' => $field] : []);
                break;

            case 'del':
                $this->show($bot, $id, ['confirm' => true]);
                break;

            case 'delyes':
                $classroom->delete();
                // Ro'yxatga qaytamiz (u bazadan qayta o'qiladi)
                Navigator::back($bot);
                break;

            default: // o, cancel va boshqalar
                $this->show($bot, $id);
        }
    }

    // ---- Matn kiritish ----------------------------------------------------------------------

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $teacher = CurrentUser::get();
        $id = (int) ($params['id'] ?? 0);
        $classroom = $this->find($teacher, $id);

        if ($classroom === null || ! in_array($field, self::FIELDS, true)) {
            $this->show($bot, $id);

            return;
        }

        [$ok, $value] = $field === 'name'
            ? ClassroomFields::name($teacher, $text, $id)
            : ClassroomFields::description($text);

        if (! $ok) {
            $this->show($bot, $id, ['ask' => $field, 'error' => $value]);

            return;
        }

        Classroom::query()->whereKey($id)->update([$field => $value, 'updated_at' => now()]);

        $this->show($bot, $id, ['flash' => 'saved']);
    }

    // ---- Ichki ------------------------------------------------------------------------------

    private function find(User $teacher, int $id): ?Classroom
    {
        if (! $teacher->isTeacher() || ! $teacher->isActive()) {
            return null;
        }

        return Classroom::query()->where('teacher_id', $teacher->id)->withCount('students')->find($id);
    }

    private function membersPage(User $teacher, Classroom $classroom, int $page): Page
    {
        $id = $classroom->id;

        $query = ShareTargets::students($teacher)->orderBy('full_name')->orderBy('id');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        $done = [[__('cls.btn.done'), "cl:o:{$id}"]];

        if ($total === 0) {
            return Page::make(__('cls.members.empty'), [$done]);
        }

        $memberIds = $classroom->students()->pluck('users.id')->all();

        $rows = [];

        foreach ($items as $student) {
            $mark = in_array($student->id, $memberIds, true) ? '✅ ' : '⬜ ';
            $rows[] = [[$mark . Str::limit($student->displayName(), 40), "cl:t:{$id}:{$student->id}"]];
        }

        if ($nav = Pager::navRow('cl:mp', $page, $pages, ":{$id}")) {
            $rows[] = $nav;
        }

        $rows[] = $done;

        return Page::make(__('cls.members.title', [
            'name' => e($classroom->name),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]), $rows);
    }

    private function card(Classroom $classroom): string
    {
        $lines = [
            '🏫 <b>' . e($classroom->name) . '</b>',
            __('cls.card.members', ['n' => $classroom->students_count]),
        ];

        if (filled($classroom->description)) {
            $lines[] = "\n" . __('cls.card.description', ['value' => e(Str::limit($classroom->description, 300))]);
        }

        return implode("\n", $lines);
    }

    /** Shu ekranni (id bo'yicha) ko'rsatadi: stekda "cl" tepada bo'lsa almashtiradi, aks holda o'tadi. */
    private function show(Nutgram $bot, int $id, array $extra = []): void
    {
        $params = ['id' => $id] + $extra;

        (Navigator::current($bot)['screen'] ?? null) === 'cl'
            ? Navigator::replace($bot, 'cl', $params)
            : Navigator::go($bot, 'cl', $params);
    }
}
