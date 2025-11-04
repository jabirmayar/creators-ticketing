<?php

namespace daacreators\CreatorsTicketing\Filament\Widgets;

use daacreators\CreatorsTicketing\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TicketStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Tickets', Ticket::count())
                ->description('All tickets in the system')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Open Tickets', Ticket::whereHas('status', fn (Builder $query) => $query->where('is_closing_status', false))->count())
                ->description('Tickets that require attention')
                ->descriptionIcon('heroicon-m-fire')
                ->color('warning'),

            Stat::make('Closed Tickets', Ticket::whereHas('status', fn (Builder $query) => $query->where('is_closing_status', true))->count())
                ->description('Successfully resolved tickets')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}