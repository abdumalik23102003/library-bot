<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string
    {
        return __("enums.status.{$this->value}");
    }
}
