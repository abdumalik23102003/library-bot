<?php

namespace App\Telegram\Screens;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Material;
use App\Models\User;
use App\Telegram\Navigation\Navigator;
use App\Telegram\Navigation\Page;
use App\Telegram\Navigation\Screen;
use App\Telegram\Support\CurrentUser;
use App\Telegram\Support\Pager;
use Illuminate\Support\Str;
use SergiX44\Nutgram\Nutgram;

/** Audit jurnali (faqat admin): oxirgi amallar, sahifalangan. Callback: aud:p:{sahifa}. */
class AudScreen extends Screen
{
    public function render(Nutgram $bot, array $params): Page
    {
        if (! CurrentUser::get()->isAdmin()) {
            return Page::make(__('sys.forbidden'), back: true);
        }

        [$items, $page, $pages, $total] = Pager::slice(
            AuditLog::query()->with('actor')->latest('id'),
            (int) ($params['page'] ?? 1),
        );

        if ($total === 0) {
            return Page::make(__('sys.audit.empty'), back: true);
        }

        $lines = [__('sys.audit.title', ['total' => $total, 'page' => $page, 'pages' => $pages]), ''];

        /** @var AuditLog $log */
        foreach ($items as $log) {
            $key = 'sys.audit.actions.' . str_replace('.', '_', $log->action);
            $action = __($key);
            $subject = $this->subject($log);

            $lines[] = sprintf(
                '%s · <b>%s</b> %s%s',
                $log->created_at?->format('d.m H:i') ?? '—',
                e($log->actor?->displayName() ?? __('sys.audit.system')),
                $action === $key ? e($log->action) : $action,
                $subject !== null ? ' — ' . e(Str::limit($subject, 40)) : '',
            );
        }

        $rows = [];

        if ($nav = Pager::navRow('aud:p', $page, $pages)) {
            $rows[] = $nav;
        }

        return Page::make(implode("\n", $lines), $rows, back: true);
    }

    public function action(Nutgram $bot, string $action, array $args): void
    {
        if ($action === 'p' && CurrentUser::get()->isAdmin() && (Navigator::current($bot)['screen'] ?? '') === 'aud') {
            Navigator::replace($bot, 'aud', ['page' => max(1, (int) ($args[0] ?? 1))]);

            return;
        }

        Navigator::refresh($bot);
    }

    /** Amal ob'ekti nomi (o'chirilgan bo'lsa ham topiladi). */
    private function subject(AuditLog $log): ?string
    {
        $meta = $log->meta ?? [];

        if (! empty($meta['name'])) {
            return (string) $meta['name'];
        }

        if ($log->subject_id === null) {
            return $meta['key'] ?? null;
        }

        return match ($log->subject_type) {
            User::class => User::withTrashed()->find($log->subject_id)?->displayName(),
            Material::class => Material::withTrashed()->find($log->subject_id)?->title,
            Category::class => Category::withTrashed()->find($log->subject_id)?->name,
            default => null,
        };
    }
}
