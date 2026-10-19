<?php

namespace App\Enums;

/** teacher_student va protected_accesses jadvallari uchun umumiy holatlar. */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Removed = 'removed';

    public function label(): string
    {
        return __("enums.request.{$this->value}");
    }
}
