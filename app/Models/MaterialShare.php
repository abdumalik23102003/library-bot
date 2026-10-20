<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ustoz o'quvchiga yuborgan material (yetkazish yozuvi). Kirish qoidasi: Material::visibleTo(). */
class MaterialShare extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
