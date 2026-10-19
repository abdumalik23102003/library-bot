<?php

namespace App\Models;

use App\Enums\Access;
use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'default_access' => Access::class,
            'system_type' => MaterialType::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /** Ustoz uchun ko'rinadigan kategoriyalar: default + o'zinikilar. */
    public function scopeForTeacher(Builder $query, User $teacher): Builder
    {
        return $query->where(function (Builder $q) use ($teacher) {
            $q->where('is_default', true)->orWhere('owner_id', $teacher->id);
        });
    }
}
