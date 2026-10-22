<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeworkAssignment extends Model
{
    public const PENDING = 'pending';

    public const SUBMITTED = 'submitted';

    public const CHECKED = 'checked';

    public const UNREADABLE = 'unreadable';

    public const ERROR = 'error';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mistakes' => 'array',
            'needs_teacher' => 'boolean',
            'score' => 'integer',
            'submitted_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(HomeworkFile::class, 'assignment_id');
    }

    public function icon(): string
    {
        return match (true) {
            $this->status === self::CHECKED && $this->needs_teacher => '👀',
            $this->status === self::CHECKED => '✅',
            $this->status === self::SUBMITTED => '📥',
            in_array($this->status, [self::UNREADABLE, self::ERROR], true) => '⚠️',
            default => '⏳',
        };
    }
}
