<?php

namespace daacreators\CreatorsTicketing\Traits;

use Filament\Facades\Filament;

trait HasNavigationVisibility
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessNavigation();
    }

    public static function canAccessNavigation(): bool
    {
        $user = Filament::auth()->user();
        
        if (!$user) {
            return false;
        }

        $field = config('creators-ticketing.navigation_visibility.field');
        $allowed = config('creators-ticketing.navigation_visibility.allowed', []);

        return in_array($user->{$field}, $allowed);
    }

    public static function canAccess(): bool
    {
        return static::canAccessNavigation();
    }
}