<?php

namespace App\Telegram\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class Pager
{
    public const PER_PAGE = 8;

    /** @return array{0: Collection, 1: int, 2: int, 3: int}  [elementlar, joriy sahifa, sahifalar soni, jami] */
    public static function slice(Builder $query, int $page, int $perPage = self::PER_PAGE): array
    {
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [$query->forPage($page, $perPage)->get(), $page, $pages, $total];
    }

    /**
     * Sahifalash tugmalari qatori: "{prefix}:{sahifa}{suffix}". Bitta sahifa bo'lsa bo'sh massiv qaytaradi.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function navRow(string $prefix, int $page, int $pages, string $suffix = ''): array
    {
        if ($pages <= 1) {
            return [];
        }

        return [
            ['⬅️', $prefix . ':' . max(1, $page - 1) . $suffix],
            ["{$page}/{$pages}", $prefix . ':' . $page . $suffix],
            ['➡️', $prefix . ':' . min($pages, $page + 1) . $suffix],
        ];
    }
}
