<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkFile extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(HomeworkAssignment::class, 'assignment_id');
    }
}
