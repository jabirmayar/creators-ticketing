<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages;

use Filament\Facades\Filament;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Filament\Widgets\TicketStatsWidget;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use Illuminate\Database\Eloquent\Model;

class ListTickets extends ListRecords
{
    use HasTicketPermissions;

    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        $permissions = $this->getUserPermissions();
        $canCreate = $permissions['is_admin'];

        if (!$canCreate) {
            foreach ($permissions['permissions'] as $deptPerms) {
                if ($deptPerms['can_create_tickets'] ?? false) {
                    $canCreate = true;
                    break;
                }
            }
        }

        return [
            CreateAction::make()
                ->visible($canCreate),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        $user = Filament::auth()->user();
        
        if (!$user) {
            return [];
        }

        $field = config('creators-ticketing.navigation_visibility.field', 'email');
        $allowed = config('creators-ticketing.navigation_visibility.allowed', []);

        if (!in_array($user->{$field} ?? null, $allowed, true)) {
            return [];
        }

        return [
            TicketStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        $user = Filament::auth()->user();
        $permissions = $this->getUserPermissions();
        
        if ($permissions['is_admin']) {
            return $this->getAdminTabs($user);
        }

        if (empty($permissions['departments'])) {
            return $this->getRequesterTabs($user);
        }

        return $this->getAgentTabs($user, $permissions);
    }

    protected function getAdminTabs($user): array
    {
        return [
            'all' => Tab::make(__('creators-ticketing::resources.ticket.tabs.all'))
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false))
                )
                ->badge(Ticket::whereHas('status', fn ($q) => $q->where('is_closing_status', false))->count())
                ->icon('heroicon-o-ticket'),

            'my_tickets' => Tab::make(__('creators-ticketing::resources.ticket.tabs.my_tickets'))
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('user_id', $user->getKey())
                )
                ->badge(Ticket::where('user_id', $user->getKey())
                    ->count())
                ->icon('heroicon-o-user'),

            'open' => Tab::make(__('creators-ticketing::resources.ticket.tabs.open'))
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false))
                )
                ->badge(Ticket::whereHas('status', fn ($q) => $q->where('is_closing_status', false))->count())
                ->icon('heroicon-o-clock'),

            'unassigned' => Tab::make(__('creators-ticketing::resources.ticket.tabs.unassigned'))
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereNull('assignee_id')
                        ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false))
                )
                ->badge(Ticket::whereNull('assignee_id')
                    ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                    ->count())
                ->icon('heroicon-o-user-minus'),

            'closed' => Tab::make(__('creators-ticketing::resources.ticket.tabs.closed'))
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', true))
                )
                ->badge(Ticket::whereHas('status', fn ($q) => $q->where('is_closing_status', true))->count())
                ->icon('heroicon-o-check-circle'),
        ];
    }

    protected function getRequesterTabs($user): array
    {
        return [
            'my_tickets' => Tab::make(__('creators-ticketing::resources.ticket.tabs.my_tickets'))
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('user_id', $user->getKey())
                )
                ->badge(Ticket::where('user_id', $user->getKey())
                    ->count())
                ->icon('heroicon-o-user'),

            'open' => Tab::make(__('creators-ticketing::resources.ticket.tabs.open'))
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('user_id', $user->getKey())
                        ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false))
                )
                ->badge(Ticket::where('user_id', $user->getKey())
                    ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                    ->count())
                ->icon('heroicon-o-clock'),

            'closed' => Tab::make(__('creators-ticketing::resources.ticket.tabs.closed'))
                ->modifyQueryUsing(fn (Builder $query) =>
                    $query->where('user_id', $user->getKey())
                        ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', true))
                )
                ->badge(Ticket::where('user_id', $user->getKey())
                    ->whereHas('status', fn ($q) => $q->where('is_closing_status', true))
                    ->count())
                ->icon('heroicon-o-check-circle'),
        ];
    }

    protected function getAgentTabs($user, $permissions): array
    {
        $departmentIds = $permissions['departments'];
        $canViewAll = $this->canUserViewAllTickets();

        $tabs = [];

        if ($canViewAll) {
            $tabs['department_all'] = Tab::make(__('creators-ticketing::resources.ticket.tabs.department'))
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereIn('department_id', $departmentIds)
                        ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false))
                )
                ->badge(Ticket::whereIn('department_id', $departmentIds)
                    ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                    ->count())
                ->icon('heroicon-o-building-office-2');
        }

        $tabs['assigned_to_me'] = Tab::make(__('creators-ticketing::resources.ticket.tabs.assigned_to_me'))
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('assignee_id', $user->getKey())
                    ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false))
            )
            ->badge(Ticket::where('assignee_id', $user->getKey())
                ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                ->count())
            ->icon('heroicon-o-user-circle');

        $tabs['my_tickets'] = Tab::make(__('creators-ticketing::resources.ticket.tabs.my_tickets'))
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('user_id', $user->getKey())
            )
            ->badge(Ticket::where('user_id', $user->getKey())
                ->count())
            ->icon('heroicon-o-user');

        if ($canViewAll) {
            $tabs['unassigned'] = Tab::make(__('creators-ticketing::resources.ticket.tabs.unassigned'))
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereIn('department_id', $departmentIds)
                        ->whereNull('assignee_id')
                        ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false))
                )
                ->badge(Ticket::whereIn('department_id', $departmentIds)
                    ->whereNull('assignee_id')
                    ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                    ->count())
                ->icon('heroicon-o-user-minus');
        }

        $tabs['open'] = Tab::make(__('creators-ticketing::resources.ticket.tabs.open'))
            ->modifyQueryUsing(function (Builder $query) use ($user, $departmentIds, $canViewAll) {
                if ($canViewAll) {
                    return $query->whereIn('department_id', $departmentIds)
                        ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false));
                }
                return $query->where(function ($q) use ($user) {
                    $q->where('assignee_id', $user->getKey())
                    ->orWhere('user_id', $user->getKey());
                })->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', false));
            })
            ->badge(function () use ($user, $departmentIds, $canViewAll) {
                if ($canViewAll) {
                    return Ticket::whereIn('department_id', $departmentIds)
                        ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                        ->count();
                }
                return Ticket::where(function ($q) use ($user) {
                    $q->where('assignee_id', $user->getKey())
                    ->orWhere('user_id', $user->getKey());
                })->whereHas('status', fn ($q) => $q->where('is_closing_status', false))->count();
            })
            ->icon('heroicon-o-clock');

        $tabs['closed'] = Tab::make(__('creators-ticketing::resources.ticket.tabs.closed'))
            ->modifyQueryUsing(function (Builder $query) use ($user, $departmentIds, $canViewAll) {
                if ($canViewAll) {
                    return $query->whereIn('department_id', $departmentIds)
                        ->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', true));
                }
                return $query->where(function ($q) use ($user) {
                    $q->where('assignee_id', $user->getKey())
                    ->orWhere('user_id', $user->getKey());
                })->whereHas('status', fn (Builder $q) => $q->where('is_closing_status', true));
            })
            ->badge(function () use ($user, $departmentIds, $canViewAll) {
                if ($canViewAll) {
                    return Ticket::whereIn('department_id', $departmentIds)
                        ->whereHas('status', fn ($q) => $q->where('is_closing_status', true))
                        ->count();
                }
                return Ticket::where(function ($q) use ($user) {
                    $q->where('assignee_id', $user->getKey())
                    ->orWhere('user_id', $user->getKey());
                })->whereHas('status', fn ($q) => $q->where('is_closing_status', true))->count();
            })
            ->icon('heroicon-o-check-circle');

        return $tabs;
    }

    protected function getTableQuery(): Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        $user = Filament::auth()->user();
        $permissions = $this->getUserPermissions();
        
        $query = parent::getTableQuery();

        if (!$permissions['is_admin']) {
            $query = $query->forUser($user->getKey());
        }

        return $query;
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return parent::table($table)
            ->recordClasses(fn (Model $record) => 
                (method_exists($record, 'isUnseen') && $record->isUnseen()) 
                ? 'font-bold bg-primary-50/50 dark:bg-primary-900/20' 
                : null
            );
    }
}