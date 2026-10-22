<?php

namespace App\Telegram\Support;

use App\Models\Homework;
use App\Models\HomeworkAssignment;
use Illuminate\Support\Str;

/** Ustozga boradigan umumiy hisobot (HTML, Telegram uchun bo'laklarga bo'linadi). */
final class HomeworkReport
{
    private const CHUNK = 3600;

    /** @return array<int, string> */
    public static function build(Homework $homework, ?string $summary = null): array
    {
        $all = $homework->assignments()->with('student')->get()->sortBy(fn ($a) => mb_strtolower($a->student?->displayName() ?? ''));

        $checked = $all->where('status', HomeworkAssignment::CHECKED);
        $submitted = $all->filter(fn ($a) => $a->status !== HomeworkAssignment::PENDING);
        $pending = $all->where('status', HomeworkAssignment::PENDING);
        $attention = $all->filter(fn ($a) => in_array($a->status, [HomeworkAssignment::UNREADABLE, HomeworkAssignment::ERROR], true) || ($a->status === HomeworkAssignment::CHECKED && $a->needs_teacher));

        $lines = [__('hw.report.title', ['title' => e($homework->title)]), ''];

        $stats = [
            __('hw.report.assigned', ['n' => $all->count()]),
            __('hw.report.submitted', ['n' => $submitted->count()]),
            __('hw.report.checked', ['n' => $checked->count()]),
        ];

        if ($checked->isNotEmpty()) {
            $stats[] = __('hw.report.avg', ['avg' => round($checked->avg('score'), 1), 'max' => $homework->max_score, 'min' => $checked->min('score'), 'high' => $checked->max('score')]);
        }

        $lines = array_merge($lines, $stats);

        if ($checked->isNotEmpty()) {
            $lines[] = '';
            $lines[] = __('hw.report.grades');

            foreach ($checked->sortByDesc('score') as $a) {
                $lines[] = sprintf(
                    '%s <b>%s</b> — %d/%d%s',
                    $a->needs_teacher ? '👀' : '✅',
                    e($a->student?->displayName() ?? '—'),
                    $a->score,
                    $homework->max_score,
                    filled($a->feedback) ? ' · ' . e(Str::limit($a->feedback, 110)) : '',
                );
            }
        }

        $topics = self::topics($checked);

        if ($topics !== []) {
            $lines[] = '';
            $lines[] = __('hw.report.topics');

            foreach ($topics as [$topic, $count]) {
                $lines[] = '• ' . e($topic) . ' — ' . __('hw.report.n_students', ['n' => $count]);
            }
        }

        if (filled($summary)) {
            $lines[] = '';
            $lines[] = __('hw.report.summary');
            $lines[] = e($summary);
        }

        $lines[] = '';
        $lines[] = __('hw.report.attention');

        if ($attention->isEmpty()) {
            $lines[] = __('hw.report.attention_none');
        }

        foreach ($attention as $a) {
            $lines[] = sprintf('⚠️ <b>%s</b> — %s', e($a->student?->displayName() ?? '—'), e(Str::limit($a->reason ?: __('hw.report.no_reason'), 160)));
        }

        if ($pending->isNotEmpty()) {
            $lines[] = '';
            $lines[] = __('hw.report.pending', ['names' => $pending->map(fn ($a) => e($a->student?->displayName() ?? '—'))->implode(', ')]);
        }

        return self::chunks($lines);
    }

    /** Eng ko'p uchragan xato mavzulari: [[mavzu, soni], ...]. */
    public static function topics($assignments, int $limit = 8): array
    {
        $count = [];
        $label = [];

        foreach ($assignments as $a) {
            foreach (array_unique(array_map(fn ($m) => mb_strtolower(trim((string) $m)), (array) $a->mistakes)) as $key) {
                if ($key === '') {
                    continue;
                }

                $count[$key] = ($count[$key] ?? 0) + 1;
                $label[$key] ??= $key;
            }
        }

        arsort($count);

        return collect($count)->take($limit)->map(fn ($n, $k) => [Str::limit($label[$k], 90), $n])->values()->all();
    }

    /** @return array<int, string> */
    private static function chunks(array $lines): array
    {
        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($line) + 1 > self::CHUNK) {
                $chunks[] = $current;
                $current = '';
            }

            $current .= ($current === '' ? '' : "\n") . $line;
        }

        return $current === '' ? $chunks : array_merge($chunks, [$current]);
    }
}
