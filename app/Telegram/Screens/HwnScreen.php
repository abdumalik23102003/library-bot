<?php

namespace App\Telegram\Screens;

use App\Jobs\SendHomework;
use App\Models\Classroom;
use App\Models\Homework;
use App\Models\HomeworkAssignment;
use App\Models\Material;
use App\Models\User;
use App\Services\HomeworkGrader;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use App\Telegram\Support\ShareTargets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/**
 * Yangi uy ishi berish (faqat ustoz): 1) o'quvchilarni tanlash (✅/⬜, sinf, qidiruv) -> 2) vazifa fayli -> 3) javoblar kaliti (ixtiyoriy)
 * -> 4) izoh (ixtiyoriy) -> 5) tasdiqlash va yuborish.
 *
 * params: step (pick|cls|file|key|note|search|confirm|done), page, error, id/n (done uchun).
 * Tanlov Cache da saqlanadi (draft): ids, q, task (material id), key (material id), note.
 * Callback: hwn:t:{id} hwn:p:{sahifa} hwn:all hwn:none hwn:cls hwn:c:{sinfId} hwn:s hwn:sc hwn:next hwn:pick hwn:file hwn:mp:{sahifa}
 *           hwn:f:{materialId} hwn:k:{materialId} hwn:ks hwn:note hwn:ns hwn:go hwn:cancel hwn:open
 */
class HwnScreen extends Screen
{
    public const MAX_STUDENTS = 200;

    public static function forget(User $teacher): void
    {
        Cache::forget(self::key($teacher));
    }

    private static function key(User $teacher): string
    {
        return "hwn:draft:{$teacher->id}";
    }

    /** @return array{ids: array<int, int>, q: string, task: ?int, key: ?int, note: string} */
    private function draft(User $teacher): array
    {
        return array_merge(['ids' => [], 'q' => '', 'task' => null, 'key' => null, 'note' => ''], Cache::get(self::key($teacher)) ?? []);
    }

    private function save(User $teacher, array $draft): void
    {
        Cache::put(self::key($teacher), $draft, now()->addDay());
    }

    // ---- Ko'rinish ----------------------------------------------------------------------------

    public function render(Nutgram $bot, array $params): Page
    {
        $teacher = CurrentUser::get();

        if (! $teacher->isTeacher() || ! $teacher->isActive()) {
            return Page::make(__('hw.err.forbidden'), back: true);
        }

        return match ($params['step'] ?? 'pick') {
            'cls' => $this->classesPage($teacher, (int) ($params['page'] ?? 1)),
            'file' => $this->materialsPage($teacher, 'f', (int) ($params['page'] ?? 1)),
            'key' => $this->materialsPage($teacher, 'k', (int) ($params['page'] ?? 1)),
            'note' => $this->notePage($params),
            'search' => Page::make(__('hw.new.search_ask'), [[[__('bot.cancel'), 'hwn:pick']]]),
            'confirm' => $this->confirmPage($teacher, $params),
            'done' => $this->donePage($params),
            default => $this->pickPage($teacher, (int) ($params['page'] ?? 1)),
        };
    }

    public function mounted(Nutgram $bot, array $params): void
    {
        $step = $params['step'] ?? 'pick';

        if (in_array($step, ['note', 'search'], true)) {
            Navigator::awaitInput($bot, $step === 'note' ? 'note' : 'q');
        }
    }

    private function students(User $teacher, string $q = ''): Builder
    {
        $query = ShareTargets::students($teacher);
        $q = trim($q);

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(fn (Builder $w) => $w->where('full_name', 'ilike', $like)->orWhere('username', 'ilike', $like));
        }

        return $query->orderBy('full_name')->orderBy('id');
    }

    private function pickPage(User $teacher, int $page): Page
    {
        $d = $this->draft($teacher);

        [$items, $page, $pages, $total] = Pager::slice($this->students($teacher, $d['q']), $page);

        $chosen = array_flip($d['ids']);
        $lines = [__('hw.new.pick_title', ['n' => count($d['ids'])])];

        if ($d['q'] !== '') {
            $lines[] = __('hw.new.searching', ['q' => e($d['q'])]);
        }

        if ($total === 0) {
            $lines[] = '';
            $lines[] = __($d['q'] !== '' ? 'hw.new.none_found' : 'hw.new.no_students');
        } else {
            $lines[] = __('hw.new.page_info', ['total' => $total, 'page' => $page, 'pages' => $pages]);
        }

        $rows = [];
        $number = ($page - 1) * Pager::PER_PAGE;

        foreach ($items as $student) {
            $number++;
            $mark = isset($chosen[$student->id]) ? '✅' : '⬜';
            $rows[] = [["{$mark} {$number}. " . Str::limit($student->displayName(), 34), "hwn:t:{$student->id}"]];
        }

        if ($nav = Pager::navRow('hwn:p', $page, $pages)) {
            $rows[] = $nav;
        }

        if ($total > 0) {
            $rows[] = [[__('hw.new.btn_all', ['n' => min($total, self::MAX_STUDENTS)]), 'hwn:all'], [__('hw.new.btn_none'), 'hwn:none']];
        }

        $rows[] = [[__('hw.new.btn_cls'), 'hwn:cls'], [__('hw.new.btn_search'), 'hwn:s']];

        if ($d['q'] !== '') {
            $rows[] = [[__('hw.new.btn_search_clear'), 'hwn:sc']];
        }

        if ($d['ids'] !== []) {
            $rows[] = [[__('hw.new.btn_next', ['n' => count($d['ids'])]), 'hwn:next']];
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    private function classesPage(User $teacher, int $page): Page
    {
        $query = Classroom::query()->where('teacher_id', $teacher->id)->withCount('students')->orderBy('name')->orderBy('id');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        $back = [[__('hw.new.btn_people'), 'hwn:pick']];

        if ($total === 0) {
            return Page::make(__('hw.new.no_classes'), [$back]);
        }

        $rows = [];

        foreach ($items as $classroom) {
            $rows[] = [['🏫 ' . Str::limit($classroom->name, 30) . ' · ' . $classroom->students_count, "hwn:c:{$classroom->id}"]];
        }

        if ($nav = Pager::navRow('hwn:cp', $page, $pages)) {
            $rows[] = $nav;
        }

        $rows[] = $back;

        return Page::make(__('hw.new.cls_title'), $rows);
    }

    /** Vazifa fayli ($mode = f) yoki javoblar kaliti ($mode = k) uchun ustozning materiallari. */
    private function materialsPage(User $teacher, string $mode, int $page): Page
    {
        $query = Material::query()->where('owner_id', $teacher->id)->latest('id');

        [$items, $page, $pages, $total] = Pager::slice($query, $page);

        $isKey = $mode === 'k';
        $nav = [[__('hw.new.btn_people'), 'hwn:pick']];

        if ($total === 0) {
            return Page::make(__('hw.new.no_materials'), [$nav]);
        }

        $rows = [];

        foreach ($items as $material) {
            $source = Homework::sourceFrom($material);
            $rows[] = [[
                Str::before($material->type->label(), ' ') . ' ' . Str::limit($material->title ?: __('mats.untitled'), 32) . (HomeworkGrader::readable($source) ? '' : ' ⚠️'),
                "hwn:{$mode}:{$material->id}",
            ]];
        }

        if ($pager = Pager::navRow('hwn:mp', $page, $pages)) {
            $rows[] = $pager;
        }

        if ($isKey) {
            $rows[] = [[__('hw.new.btn_skip_key'), 'hwn:ks']];
        }

        $rows[] = $nav;

        return Page::make(__($isKey ? 'hw.new.key_title' : 'hw.new.file_title', ['total' => $total, 'page' => $page, 'pages' => $pages]), $rows);
    }

    private function notePage(array $params): Page
    {
        $lines = [__('hw.new.note_ask')];

        if (! empty($params['error'])) {
            $lines[] = '';
            $lines[] = '⚠️ ' . e($params['error']);
        }

        return Page::make(implode("\n", $lines), [[[__('hw.new.btn_skip_note'), 'hwn:ns']], [[__('bot.cancel'), 'hwn:cancel']]]);
    }

    private function confirmPage(User $teacher, array $params): Page
    {
        $d = $this->draft($teacher);
        $students = $this->selected($teacher, $d);
        $task = $d['task'] ? Material::query()->where('owner_id', $teacher->id)->find($d['task']) : null;
        $key = $d['key'] ? Material::query()->where('owner_id', $teacher->id)->find($d['key']) : null;

        $names = $students->take(5)->map(fn (User $u) => e($u->displayName()))->implode(', ');
        $more = $students->count() - 5;

        $lines = [
            __('hw.new.confirm_title'),
            '',
            __('hw.new.c_students', ['n' => $students->count(), 'names' => $names . ($more > 0 ? ' ' . __('hw.new.and_more', ['n' => $more]) : '')]),
            __('hw.new.c_file', ['name' => $task ? e(Str::limit($task->title ?: $task->file_name ?: __('mats.untitled'), 60)) : '—']),
            __('hw.new.c_key', ['name' => $key ? e(Str::limit($key->title ?: $key->file_name ?: __('mats.untitled'), 60)) : __('hw.new.none')]),
            __('hw.new.c_note', ['text' => $d['note'] !== '' ? e(Str::limit($d['note'], 200)) : __('hw.new.none')]),
        ];

        if ($task !== null && ! HomeworkGrader::readable(Homework::sourceFrom($task)) && $d['note'] === '') {
            $lines[] = '';
            $lines[] = __('hw.new.warn_task');
        }

        if ($key !== null && ! HomeworkGrader::readable(Homework::sourceFrom($key))) {
            $lines[] = '';
            $lines[] = __('hw.new.warn_key');
        }

        if (! empty($params['error'])) {
            $lines[] = '';
            $lines[] = '⚠️ ' . e($params['error']);
        }

        $rows = [];

        if ($students->isNotEmpty() && $task !== null) {
            $rows[] = [[__('hw.new.btn_send', ['n' => $students->count()]), 'hwn:go']];
        }

        $rows[] = [[__('hw.new.btn_people'), 'hwn:pick'], [__('hw.new.btn_file'), 'hwn:file']];
        $rows[] = [[__('hw.new.btn_key'), 'hwn:ks'], [__('hw.new.btn_note'), 'hwn:note']];
        $rows[] = [[__('bot.cancel'), 'hwn:cancel']];

        return Page::make(implode("\n", $lines), $rows);
    }

    private function donePage(array $params): Page
    {
        return Page::make(__('hw.new.done', ['n' => (int) ($params['n'] ?? 0)]), [[[__('hw.btn.open_card'), 'hwn:open']]], back: true);
    }

    // ---- Tugmalar -------------------------------------------------------------------------------

    public function action(Nutgram $bot, string $action, array $args): void
    {
        $teacher = CurrentUser::get();
        $top = Navigator::current($bot);

        if (($top['screen'] ?? '') !== 'hwn' || ! $teacher->isTeacher() || ! $teacher->isActive()) {
            Navigator::refresh($bot);

            return;
        }

        $params = $top['params'];
        $page = (int) ($params['page'] ?? 1);
        $d = $this->draft($teacher);
        $to = fn (string $step, array $extra = []) => Navigator::replace($bot, 'hwn', ['step' => $step] + $extra);

        switch ($action) {
            case 't':
                $id = (int) ($args[0] ?? 0);

                if (in_array($id, $d['ids'], true)) {
                    $d['ids'] = array_values(array_diff($d['ids'], [$id]));
                } elseif (count($d['ids']) < self::MAX_STUDENTS && ShareTargets::students($teacher)->whereKey($id)->exists()) {
                    $d['ids'][] = $id;
                }

                $this->save($teacher, $d);
                $to('pick', ['page' => $page]);
                break;

            case 'p':
                $to('pick', ['page' => max(1, (int) ($args[0] ?? 1))]);
                break;

            case 'all':
                $found = $this->students($teacher, $d['q'])->limit(self::MAX_STUDENTS)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $d['ids'] = array_slice(array_values(array_unique(array_merge($d['ids'], $found))), 0, self::MAX_STUDENTS);
                $this->save($teacher, $d);
                $to('pick', ['page' => $page]);
                break;

            case 'none':
                $d['ids'] = [];
                $this->save($teacher, $d);
                $to('pick', ['page' => $page]);
                break;

            case 'cls':
                $to('cls', ['page' => 1]);
                break;

            case 'cp':
                $to('cls', ['page' => max(1, (int) ($args[0] ?? 1))]);
                break;

            case 'c':
                $d['ids'] = array_slice(array_values(array_unique(array_merge($d['ids'], ShareTargets::resolve($teacher, 'cls', (int) ($args[0] ?? 0))))), 0, self::MAX_STUDENTS);
                $this->save($teacher, $d);
                $to('pick', ['page' => 1]);
                break;

            case 's':
                $to('search');
                break;

            case 'sc':
                $d['q'] = '';
                $this->save($teacher, $d);
                $to('pick', ['page' => 1]);
                break;

            case 'next':
                $d['ids'] === [] ? $to('pick') : $to($d['task'] ? 'confirm' : 'file', ['page' => 1]);
                break;

            case 'pick':
                $to('pick', ['page' => 1]);
                break;

            case 'file':
                $to('file', ['page' => 1]);
                break;

            case 'mp':
                $to(in_array($params['step'] ?? '', ['file', 'key'], true) ? $params['step'] : 'file', ['page' => max(1, (int) ($args[0] ?? 1))]);
                break;

            case 'f':
                if (Material::query()->where('owner_id', $teacher->id)->whereKey((int) ($args[0] ?? 0))->exists()) {
                    $d['task'] = (int) $args[0];
                    $this->save($teacher, $d);
                    $to('key', ['page' => 1]);
                } else {
                    Navigator::refresh($bot);
                }
                break;

            case 'k':
                if (Material::query()->where('owner_id', $teacher->id)->whereKey((int) ($args[0] ?? 0))->exists()) {
                    $d['key'] = (int) $args[0];
                    $this->save($teacher, $d);
                    $to('note');
                } else {
                    Navigator::refresh($bot);
                }
                break;

            case 'ks':
                // Kalitsiz davom etish (yoki tasdiqlash sahifasidan kalitni qayta tanlash).
                if (($params['step'] ?? '') === 'confirm') {
                    $to('key', ['page' => 1]);
                } else {
                    $d['key'] = null;
                    $this->save($teacher, $d);
                    $to('note');
                }
                break;

            case 'note':
                $to('note');
                break;

            case 'ns':
                $to('confirm');
                break;

            case 'go':
                $this->go($bot, $teacher, $d);
                break;

            case 'open':
                $id = (int) ($params['id'] ?? 0);
                Homework::query()->where('teacher_id', $teacher->id)->whereKey($id)->exists() ? Navigator::replace($bot, 'hwk', ['id' => $id]) : Navigator::refresh($bot);
                break;

            default: // cancel
                self::forget($teacher);
                Navigator::back($bot);
        }
    }

    public function input(Nutgram $bot, string $field, string $text, array $params): void
    {
        $teacher = CurrentUser::get();
        $d = $this->draft($teacher);
        $text = trim($text);

        if ($field === 'q') {
            $d['q'] = Str::limit($text, 50, '');
            $this->save($teacher, $d);
            Navigator::replace($bot, 'hwn', ['step' => 'pick', 'page' => 1]);

            return;
        }

        if ($field !== 'note') {
            Navigator::refresh($bot);

            return;
        }

        if (mb_strlen($text) > 1500) {
            Navigator::replace($bot, 'hwn', ['step' => 'note', 'error' => __('hw.new.note_long', ['max' => 1500])]);

            return;
        }

        $d['note'] = $text;
        $this->save($teacher, $d);
        Navigator::replace($bot, 'hwn', ['step' => 'confirm']);
    }

    // ---- Yaratish -------------------------------------------------------------------------------

    /** Uy ishini yaratadi va o'quvchilarga yuborishni navbatga qo'yadi. */
    private function go(Nutgram $bot, User $teacher, array $d): void
    {
        $students = $this->selected($teacher, $d);
        $task = $d['task'] ? Material::query()->where('owner_id', $teacher->id)->find($d['task']) : null;
        $key = $d['key'] ? Material::query()->where('owner_id', $teacher->id)->find($d['key']) : null;

        if ($students->isEmpty() || $task === null) {
            Navigator::replace($bot, 'hwn', ['step' => 'confirm', 'error' => __('hw.new.err_incomplete')]);

            return;
        }

        $lock = 'hwn:go:' . $teacher->id . ':' . md5(json_encode([$students->pluck('id'), $task->id, $key?->id, $d['note']]));

        // Ikki marta bosilsa, ikkinchisi e'tiborsiz qoladi.
        if (! Cache::add($lock, true, now()->addSeconds(30))) {
            Navigator::refresh($bot);

            return;
        }

        $homework = Homework::query()->create([
            'teacher_id' => $teacher->id,
            'material_id' => $task->id,
            'title' => Str::limit($task->title ?: $task->file_name ?: __('hw.untitled'), 150, ''),
            'note' => $d['note'] !== '' ? $d['note'] : null,
            'task' => Homework::sourceFrom($task),
            'key' => $key ? Homework::sourceFrom($key) : null,
        ]);

        foreach ($students as $student) {
            HomeworkAssignment::query()->create(['homework_id' => $homework->id, 'student_id' => $student->id]);
        }

        SendHomework::dispatch($homework->id);
        self::forget($teacher);

        Navigator::replace($bot, 'hwn', ['step' => 'done', 'id' => $homework->id, 'n' => $students->count()]);
    }

    /** Tanlangan o'quvchilar HAR SAFAR qayta tekshiriladi (hali ham tasdiqlangan va faolmi). */
    private function selected(User $teacher, array $d)
    {
        $ids = array_slice(array_values(array_unique($d['ids'])), 0, self::MAX_STUDENTS);

        return $ids === [] ? collect() : ShareTargets::students($teacher)->whereIn('id', $ids)->orderBy('full_name')->orderBy('id')->get();
    }
}
