<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Teacher = 'teacher';
    case Student = 'student';

    public function label(): string
    {
        return __("enums.role.{$this->value}");
    }

    /** Admin yoki moderator. */
    public function isStaff(): bool
    {
        return in_array($this, [self::Admin, self::Moderator], true);
    }
}
