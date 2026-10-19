<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'role' => Role::class,
            'status' => UserStatus::class,
            'approved_at' => 'datetime',
            'registered_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    // ---- Rol va holat ---------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isModerator(): bool
    {
        return $this->role === Role::Moderator;
    }

    public function isTeacher(): bool
    {
        return $this->role === Role::Teacher;
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }

    /** Admin yoki moderator. */
    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function displayName(): string
    {
        if (filled($this->full_name)) {
            return $this->full_name;
        }

        return filled($this->username) ? '@' . $this->username : "ID {$this->telegram_id}";
    }

    // ---- Scope'lar ------------------------------------------------------------------------

    public function scopeOfRole(Builder $query, Role ...$roles): Builder
    {
        return $query->whereIn('role', array_map(fn (Role $role) => $role->value, $roles));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    // ---- Aloqalar -------------------------------------------------------------------------

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'owner_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'owner_id');
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    /** Ustozga yozilgan barcha o'quvchilar (har qanday holatda). */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'teacher_student', 'teacher_id', 'student_id')
            ->withPivot(['status', 'decided_at'])
            ->withTimestamps();
    }

    public function approvedStudents(): BelongsToMany
    {
        return $this->students()->wherePivot('status', RequestStatus::Approved->value);
    }

    /** O'quvchining ustozlari (har qanday holatda). */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'teacher_student', 'student_id', 'teacher_id')
            ->withPivot(['status', 'decided_at'])
            ->withTimestamps();
    }

    public function approvedTeachers(): BelongsToMany
    {
        return $this->teachers()->wherePivot('status', RequestStatus::Approved->value);
    }
}
