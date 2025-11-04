<?php

namespace daacreators\CreatorsTicketing;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use daacreators\CreatorsTicketing\Filament\Resources\Forms\FormResource;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\DepartmentResource;
use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\TicketStatusResource;

class TicketingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'creators-ticketing';
    }

    public function register(Panel $panel): void
    {
        $allResources = [
            FormResource::class,
            DepartmentResource::class,
            TicketResource::class,
            TicketStatusResource::class,
        ];

        foreach ($allResources as $resource) {
            $resource::navigationGroup(config('creators-ticketing.navigation_group'));
        }

        $panel->resources($allResources);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static();
    }
}