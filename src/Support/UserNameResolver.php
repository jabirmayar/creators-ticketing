<?php

namespace daacreators\CreatorsTicketing\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class UserNameResolver
{
    public static function resolve(?Authenticatable $user): string
    {
        if (! $user) {
            return 'System';
        }

        $column = config('creators-ticketing.user_name_column', 'name');

        return (string) data_get($user, $column, 'User');
    }
}
