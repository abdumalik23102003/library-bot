<?php

namespace App\Telegram\Support;

use App\Enums\Access;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Standart (hamma ustozda ko'rinadigan) kategoriyalar: nom tekshiruvi, yaratish, yangilash, o'chirish + audit. */
final class DefaultCategories
{
    public static function query(): Builder
    {
        return Category::query()->defaults()->orderBy('id');
    }

    /** @return array{0: bool, 1: ?string} [ok, qiymat yoki xato matni] */
    public static function name(string $text, ?int $ignoreId = null): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($text);

        if ($length < 2 || $length > 50) {
            return [false, __('cat.err.name')];
        }

        $exists = Category::query()
            ->defaults()
            ->whereRaw('lower(name) = ?', [mb_strtolower($text)])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        return $exists ? [false, __('cat.err.exists')] : [true, $text];
    }

    public static function create(User $actor, string $name, ?string $description, Access $access): Category
    {
        $category = Category::create([
            'owner_id' => null,
            'name' => $name,
            'description' => $description,
            'default_access' => $access->value,
            'is_default' => true,
        ]);

        AuditLog::record($actor, 'category.created', $category, ['name' => $name]);

        return $category;
    }

    /** @param  string|Access|null  $value */
    public static function update(User $actor, Category $category, string $field, $value): void
    {
        $category->{$field} = $value;
        $category->save();

        AuditLog::record($actor, 'category.updated', $category, ['field' => $field]);
    }

    /** Tizim kategoriyalari (turiga bog'langan) o'chirilmaydi. */
    public static function canDelete(Category $category): bool
    {
        return $category->is_default && $category->system_type === null;
    }

    /** Ichidagi materiallar o'chmaydi, kategoriyasiz qoladi. */
    public static function delete(User $actor, Category $category): bool
    {
        if (! self::canDelete($category)) {
            return false;
        }

        DB::transaction(function () use ($actor, $category) {
            Material::query()->where('category_id', $category->id)->update(['category_id' => null]);
            AuditLog::record($actor, 'category.deleted', $category, ['name' => $category->name]);
            $category->delete();
        });

        return true;
    }
}
