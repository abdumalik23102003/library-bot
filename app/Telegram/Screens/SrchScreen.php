<?php

namespace App\Telegram\Screens;

use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Qidiruv natijalari. Qidiruv matni params['q'] da (Dispatcher oddiy matndan ochadi). */
class SrchScreen extends MaterialListScreen
{
    protected function key(): string
    {
        return 'srch';
    }

    protected function query(User $user, array $params): Builder
    {
        return Material::query()
            ->visibleTo($user)
            ->search((string) ($params['q'] ?? ''))
            ->latest('id');
    }

    protected function title(array $params, int $total, int $page, int $pages): string
    {
        return __('search.srch.title', [
            'q' => e((string) ($params['q'] ?? '')),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    protected function empty(array $params): string
    {
        return __('search.srch.empty', ['q' => e((string) ($params['q'] ?? ''))]);
    }
}
