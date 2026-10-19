<?php

namespace App\Models;

use App\Enums\Access;
use App\Enums\MaterialType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'access' => Access::class,
            'file_size' => 'integer',
            'downloads_count' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function sizeForHumans(): ?string
    {
        return $this->file_size ? Number::fileSize($this->file_size, precision: 1) : null;
    }

    /**
     * KIRISH QOIDASINING YAGONA MANBAI: $viewer qaysi materiallarni ko'ra oladi.
     *  - admin: hammasini
     *  - ustoz: public + o'zining barcha materiallari
     *  - o'quvchi: public + ustozi protected-kirish bergan ustozlarning protected materiallari
     *  - moderator va boshqalar: faqat public
     * private faqat egasi (va admin) uchun.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->role === Role::Admin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($viewer) {
            $q->where('access', Access::Public->value);

            if ($viewer->role === Role::Teacher) {
                $q->orWhere('owner_id', $viewer->id);
            }

            if ($viewer->role === Role::Student) {
                $q->orWhere(function (Builder $own) use ($viewer) {
                    $own->where('access', Access::Protected->value)
                        ->whereIn('owner_id', ProtectedAccess::query()
                            ->select('teacher_id')
                            ->where('student_id', $viewer->id)
                            ->where('status', RequestStatus::Approved->value));
                });
            }
        });
    }

    /** Sarlavha va tavsif bo'yicha qidiruv (PostgreSQL ILIKE). */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%' . addcslashes(trim($term), '%_\\') . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'ilike', $like)->orWhere('description', 'ilike', $like);
        });
    }
}
