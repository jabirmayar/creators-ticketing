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
            Stat::make(
                __('creators-ticketing::resources.widgets.ticket_stats.total_tickets'), 
                Ticket::count()
            )
                ->description(__('creators-ticketing::resources.widgets.ticket_stats.total_tickets_desc'))
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make(
                __('creators-ticketing::resources.widgets.ticket_stats.open_tickets'), 
                Ticket::whereHas('status', fn (Builder $query) => $query->where('is_closing_status', false))->count()
            )
                ->description(__('creators-ticketing::resources.widgets.ticket_stats.open_tickets_desc'))
                ->descriptionIcon('heroicon-m-fire')
                ->color('warning'),

            Stat::make(
                __('creators-ticketing::resources.widgets.ticket_stats.closed_tickets'), 
                Ticket::whereHas('status', fn (Builder $query) => $query->where('is_closing_status', true))->count()
            )
                ->description(__('creators-ticketing::resources.widgets.ticket_stats.closed_tickets_desc'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}