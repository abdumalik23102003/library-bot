<?php

namespace App\Telegram\Screens;

use App\Models\Download;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Foydalanuvchi yuklab olgan materiallar (oxirgisi birinchi). Kirishi yopilgan materiallar ko'rinmaydi. */
class DlsScreen extends MaterialListScreen
{
    protected function key(): string
    {
        return 'dls';
    }

    protected function query(User $user, array $params): Builder
    {
        $lastDownload = Download::query()
            ->selectRaw('max(created_at)')
            ->whereColumn('material_id', 'materials.id')
            ->where('user_id', $user->id);

        return Material::query()
            ->visibleTo($user)
            ->whereIn('id', Download::query()->select('material_id')->where('user_id', $user->id))
            ->orderByDesc($lastDownload);
    }

    protected function title(array $params, int $total, int $page, int $pages): string
    {
        return __('search.dls.title', ['total' => $total, 'page' => $page, 'pages' => $pages]);
    }

    protected function empty(array $params): string
    {
        return __('search.dls.empty');
    }
}
