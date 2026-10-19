<?php

namespace App\Telegram\Support;

use App\Models\User;

/** Joriy update egasi (middleware o'rnatadi). */
final class CurrentUser
{
    public static function set(User $user): void
    {
        app()->instance('tg.user', $user);
    }

    public static function get(): User
    {
        return app('tg.user');
    }
}
