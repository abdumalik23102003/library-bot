<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Homework extends Model
{
    protected $table = 'homeworks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'task' => 'array',
            'key' => 'array',
            'closed' => 'boolean',
            'max_score' => 'integer',
            'checked_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** Yopilganiga 1 haftadan oshmagan yoki ochiq uy ishlari (ro'yxatda ko'rinadiganlar). */
    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where(fn ($w) => $w->where('closed', false)->orWhereNull('closed_at')->orWhere('closed_at', '>', now()->subWeek()));
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(HomeworkAssignment::class);
    }

    /** Materialdan fayl nusxasi: {kind, file_id, name, mime, size, text}. */
    public static function sourceFrom(Material $material): array
    {
        return [
            'kind' => $material->type->value,
            'file_id' => $material->telegram_file_id,
            'name' => $material->file_name ?: $material->title,
            'mime' => $material->mime_type,
            'size' => $material->file_size,
            'text' => $material->body ?: $material->url,
        ];
    }
}
