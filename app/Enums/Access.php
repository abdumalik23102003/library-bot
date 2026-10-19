<?php

namespace App\Enums;

enum Access: string
{
    case Public = 'public';
    case Protected = 'protected';
    case Private = 'private';

    public function label(): string
    {
        return __("enums.access.{$this->value}");
    }
}
