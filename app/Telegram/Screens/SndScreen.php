<?php

namespace App\Telegram\Screens;

use App\Enums\Access;
use App\Jobs\ShareMaterial;
use App\Models\Classroom;
use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use App\Telegram\Support\ShareTargets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Materialni o'quvchilarga yuborish: kimga (bitta / sinf / hammasi) -> tasdiqlash -> navbatga qo'yish.
 * Callback: snd:start:{materialId}, snd:t:{one|cls|all}, snd:pp:{sahifa}, snd:pick:{id}, snd:to, snd:go.
 * params: id (material), step (target|one|cls|confirm|done), page, mode, arg, n.
 * Yuborish qabul qiluvchilari tasdiqlashda va navbat ishida QAYTA hisoblanadi.
 */
class SndScreen extends Screen
{
    // ---- Ko'rinish --------------------------------------------------------------------------

    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = CurrentUser::get();
        $material = $this->material($teacher, (int) ($params['id'] ?? 0));

        if ($material === null) {
            return Page::make(__('cls.snd.err_material'), back: true);
        }

        $head = __('cls.snd.title', ['title' => e(Str::limit($material->title ?: __('mats.untitled'), 60))]);

        return match ($params['step'] ?? 'target') {
            'one' => $this->studentsPage($teacher, $head, (int) ($params['page'] ?? 1)),
            'cls' => $this->classesPage($teacher, $head, (int) ($params['page'] ?? 1)),
            'confirm' => $this->confirmPage($teacher, $material, $head, (string) ($params['mode'] ?? ''), (int) ($params['arg'] ?? 0)),
            'done' => Page::make($head . "\n\n" . __('cls.snd.queued', ['n' => (int) ($params['n'] ?? 0)]), back: true),
            default => $this->targetPage($teacher, $material, $head),
        };
    }

    // ---- Tugmalar ---------------------------------------------------------------------------

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $teacher = CurrentUser::get();

        if ($action === 'start') {
            $material = $this->material($teacher, (int) ($args[0] ?? 0));

            $material === null
                ? Navigator::refresh($bot)
                : Navigator::go($bot, 'snd', ['id' => $material->id, 'step' => 'target']);

            return;
        }

        $top = Navigator::current($bot);

        if (($top['screen'] ?? '') !== 'snd') {
            Navigator::refresh($bot);

            return;
        }

        $params = $top['params'];
        $id = (int) ($params['id'] ?? 0);
        $step = (string) ($params['step'] ?? 'target');

        if ($this->material($teacher, $id) === null) {
            Navigator::refresh($bot);

            return;
        }

        match (true) {
            $action === 'to' => Navigator::replace($bot, 'snd', ['id' => $id, 'step' => 'target']),
            $action === 't' && in_array($args[0] ?? '', ['one', 'cls'], true) => Navigator::replace($bot, 'snd', ['id' => $id, 'step' => $args[0], 'page' => 1]),
            $action === 't' && ($args[0] ?? '') === 'all' => Navigator::replace($bot, 'snd', ['id' => $id, 'step' => 'confirm', 'mode' => 'all', 'arg' => 0]),
            $action === 'pp' && in_array($step, ['one', 'cls'], true) => Navigator::replace($bot, 'snd', ['id' => $id, 'step' => $step, 'page' => max(1, (int) ($args[0] ?? 1))]),
            $action === 'pick' && in_array($step, ['one', 'cls'], true) => Navigator::replace($bot, 'snd', ['id' => $id, 'step' => 'confirm', 'mode' => $step, 'arg' => (int) ($args[0] ?? 0)]),
            $action === 'go' && $step === 'confirm' => $this->go($bot, $teacher, $id, (string) ($params['mode'] ?? ''), (int) ($params['arg'] ?? 0)),
            default => Navigator::refresh($bot),
        };
    }

    // ---- Ichki ------------------------------------------------------------------------------

    /** Ustozning o'z materiali (public, protected yoki private). */
    private function material(User $teacher, int $id): ?Material
    {
        if (! $teacher->isTeacher() || ! $teacher->isActive()) {
            return null;
        }

        return Material::query()
            ->where('owner_id', $teacher->id)
            ->find($id);
    }

    private function targetPage(User $teacher, Material $material, string $head): Page
    {
        $count = ShareTargets::students($teacher)->count();

        if ($count === 0) {
            return Page::make($head . "\n\n" . __('cls.snd.no_students'), back: true);
        }

        $lines = [$head, '', __('cls.snd.pick_target', ['n' => $count])];

        if ($material->access !== Access::Public) {
            $lines[] = '';
            $lines[] = __("cls.snd.note_{$material->access->value}");
        }

        return Page::make(implode("\n", $lines), [
            [[__('cls.snd.btn_one'), 'snd:t:one']],
            [[__('cls.snd.btn_cls'), 'snd:t:cls']],
            [[__('cls.snd.btn_all'), 'snd:t:all']],
        ], back: true);
    }

    private function studentsPage(User $teacher, string $head, int $page): Page
    {
        $query = ShareTargets::students($teacher)->orderBy('full_name')->orderBy('id');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        $rows = [];

        foreach ($items as $student) {
            $rows[] = [[Str::limit($student->displayName(), 40), "snd:pick:{$student->id}"]];
        }

        if ($nav = Pager::navRow('snd:pp', $page, $pages)) {
            $rows[] = $nav;
        }

        $rows[] = [[__('cls.snd.btn_targets'), 'snd:to']];

        return Page::make($head . "\n\n" . __('cls.snd.pick_student', ['total' => $total, 'page' => $page, 'pages' => $pages]), $rows);
    }

    private function classesPage(User $teacher, string $head, int $page): Page
    {
        $query = Classroom::query()
            ->where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('name')
            ->orderBy('id');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        if ($total === 0) {
            return Page::make($head . "\n\n" . __('cls.snd.no_classes'), [[[__('cls.snd.btn_targets'), 'snd:to']]]);
        }

        $rows = [];

        foreach ($items as $classroom) {
            $rows[] = [[
                Str::limit($classroom->name, 30) . ' · ' . __('cls.list.members', ['n' => $classroom->students_count]),
                "snd:pick:{$classroom->id}",
            ]];
        }

        if ($nav = Pager::navRow('snd:pp', $page, $pages)) {
            $rows[] = $nav;
        }

        $rows[] = [[__('cls.snd.btn_targets'), 'snd:to']];

        return Page::make($head . "\n\n" . __('cls.snd.pick_class', ['total' => $total, 'page' => $page, 'pages' => $pages]), $rows);
    }

    private function confirmPage(User $teacher, Material $material, string $head, string $mode, int $arg): Page
    {
        $ids = ShareTargets::resolve($teacher, $mode, $arg);

        if ($ids === []) {
            return Page::make($head . "\n\n" . __('cls.snd.empty_target'), [[[__('cls.snd.btn_targets'), 'snd:to']]]);
        }

        $lines = [$head, '', __('cls.snd.confirm', ['target' => $this->targetLabel($teacher, $mode, $arg), 'n' => count($ids)])];

        if ($material->access !== Access::Public) {
            $lines[] = '';
            $lines[] = __("cls.snd.note_{$material->access->value}");
        }

        return Page::make(implode("\n", $lines), [[
            [__('cls.snd.btn_go'), 'snd:go'],
            [__('cls.snd.btn_back'), 'snd:to'],
        ]]);
    }

    private function targetLabel(User $teacher, string $mode, int $arg): string
    {
        if ($mode === 'one') {
            $student = ShareTargets::students($teacher)->whereKey($arg)->first();

            return e($student?->displayName() ?? '—');
        }

        if ($mode === 'cls') {
            $classroom = Classroom::query()->where('teacher_id', $teacher->id)->find($arg);

            return __('cls.snd.target_cls', ['name' => e($classroom?->name ?? '—')]);
        }

        return __('cls.snd.target_all');
    }

    /** Qabul qiluvchilarni qayta hisoblab, navbatga qo'yadi. Ikki marta bosilsa, ikkinchisi e'tiborsiz qoladi. */
    private function go(Nutgram $bot, User $teacher, int $materialId, string $mode, int $arg): void
    {
        $ids = ShareTargets::resolve($teacher, $mode, $arg);

        if ($ids === []) {
            Navigator::refresh($bot);

            return;
        }

        $key = sprintf('share:%d:%d:%s', $teacher->id, $materialId, md5($mode . ':' . $arg));

        if (Cache::add($key, true, now()->addSeconds(20))) {
            ShareMaterial::dispatch($teacher->id, $materialId, $ids);
        }

        Navigator::replace($bot, 'snd', ['id' => $materialId, 'step' => 'done', 'n' => count($ids)]);
    }
}
