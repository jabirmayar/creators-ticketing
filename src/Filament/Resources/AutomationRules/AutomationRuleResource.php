<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\AutomationRules;

use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Component;
use Filament\Tables\Filters\TernaryFilter;
use daacreators\CreatorsTicketing\Models\Form;
use Filament\Schemas\Components\Utilities\Set;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Models\AutomationRule;
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\Pages;
use daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\RelationManagers\AutomationLogsRelationManager;

class AutomationRuleResource extends Resource
{
    use HasTicketPermissions, HasTicketingNavGroup;
    
    protected static ?string $model = AutomationRule::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.automation.nav_label');
    }

   public static function canViewAny(): bool
    {
        return static::userCan('can_manage_automations');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $allowedUserField = config('creators-ticketing.navigation_visibility.field');
        $allowedUserValues = config('creators-ticketing.navigation_visibility.allowed');
        
        $tablePrefix = config('creators-ticketing.table_prefix');
        $deptTableName = $tablePrefix . 'departments';
        $pivotTableName = $tablePrefix . 'department_users';

        $getFilteredUserOptions = function (callable $get) use ($userModel, $allowedUserField, $allowedUserValues, $pivotTableName) {
            $selectedDepartmentIds = $get('conditions.department_id');
            $allUsers = collect();
            
            $userInstance = new $userModel;
            $userKey = $userInstance->getKeyName();
            $nameColumn = config('creators-ticketing.user_name_column', 'name');

            if (!empty($selectedDepartmentIds)) {
                $userIds = DB::table($pivotTableName)
                    ->whereIn('department_id', $selectedDepartmentIds)
                    ->pluck('user_id')
                    ->toArray();

                $departmentUsers = $userModel::whereIn($userKey, $userIds)->get();
                $allUsers = $allUsers->merge($departmentUsers);
            }

            $configAllowedUsers = $userModel::whereIn($allowedUserField, $allowedUserValues)->get();
            $allUsers = $allUsers->merge($configAllowedUsers);

            return $allUsers->unique($userKey)->sortBy($nameColumn)->pluck($nameColumn, $userKey)->toArray();
        };
        
        return $schema->schema([
            Tabs::make(__('creators-ticketing::resources.automation.tabs.configuration'))
                ->tabs([
                    Tab::make(__('creators-ticketing::resources.automation.tabs.basic_info'))
                        ->schema([
                            Placeholder::make('explanation')
                                ->label(__('creators-ticketing::resources.automation.form.explanation_label'))
                                ->content(new HtmlString(__('creators-ticketing::resources.automation.form.explanation_content')))
                                ->columnSpanFull(),

                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->label(__('creators-ticketing::resources.automation.form.name')),
                            
                            Textarea::make('description')
                                ->rows(3)
                                ->maxLength(500)
                                ->label(__('creators-ticketing::resources.automation.form.description')),
                            
                            Toggle::make('is_active')
                                ->label(__('creators-ticketing::resources.automation.form.is_active'))
                                ->default(true)
                                ->helperText(__('creators-ticketing::resources.automation.form.is_active_helper')),
                            
                            Select::make('trigger_event')
                                ->label(__('creators-ticketing::resources.automation.form.trigger_event'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (blank($state)) {
                                        return;
                                    }
                                    
                                    $maxOrder = AutomationRule::where('trigger_event', $state)->max('execution_order');
                                    $set('execution_order', $maxOrder !== null ? $maxOrder + 1 : 0);
                                })
                                ->options([
                                    'ticket_created' => __('creators-ticketing::resources.automation.triggers.ticket_created'),
                                    'ticket_updated' => __('creators-ticketing::resources.automation.triggers.ticket_updated'),
                                    'status_changed' => __('creators-ticketing::resources.automation.triggers.status_changed'),
                                    'priority_changed' => __('creators-ticketing::resources.automation.triggers.priority_changed'),
                                    'ticket_assigned' => __('creators-ticketing::resources.automation.triggers.ticket_assigned'),
                                    'reply_added' => __('creators-ticketing::resources.automation.triggers.reply_added'),
                                    'internal_note_added' => __('creators-ticketing::resources.automation.triggers.internal_note_added'),
                                ])
                                ->helperText(__('creators-ticketing::resources.automation.form.trigger_helper')),
                            
                            TextInput::make('execution_order')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->label(__('creators-ticketing::resources.automation.form.execution_order'))
                                ->helperText(__('creators-ticketing::resources.automation.form.order_helper')),
                            
                            Toggle::make('stop_processing')
                                ->label(__('creators-ticketing::resources.automation.form.stop_processing'))
                                ->helperText(__('creators-ticketing::resources.automation.form.stop_processing_helper')),
                        ])->columns(2),
                    
                    Tab::make(__('creators-ticketing::resources.automation.tabs.conditions'))
                        ->schema([
                            Section::make(__('creators-ticketing::resources.automation.sections.match_conditions'))
                                ->description(__('creators-ticketing::resources.automation.sections.match_conditions_desc'))
                                ->schema([
                                    Select::make('conditions.department_id')
                                        ->label(__('creators-ticketing::resources.automation.conditions.department'))
                                        ->multiple()
                                        ->options(Department::pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->reactive()
                                        ->afterStateUpdated(fn (callable $set) => $set('conditions.form_id', null))
                                        ->afterStateUpdated(fn (callable $set) => $set('conditions.assignee_id', null))
                                        ->afterStateUpdated(fn (callable $set) => $set('actions.assign_to', null)),
                                    
                                    Select::make('conditions.form_id')
                                        ->label(__('creators-ticketing::resources.automation.conditions.form'))
                                        ->multiple()
                                        ->options(function (callable $get) use ($deptTableName) {
                                            $selectedDepartmentIds = $get('conditions.department_id');
                                            if (empty($selectedDepartmentIds)) {
                                                return Form::pluck('name', 'id');
                                            }
                                            return Form::whereHas('departments', fn ($query) => 
                                                $query->whereIn($deptTableName . '.id', $selectedDepartmentIds)
                                            )->pluck('name', 'id');
                                        })
                                        ->searchable()
                                        ->preload(),
                                    
                                    Select::make('conditions.status_id')
                                        ->label(__('creators-ticketing::resources.automation.conditions.status'))
                                        ->multiple()
                                        ->options(TicketStatus::pluck('name', 'id'))
                                        ->searchable()
                                        ->preload(),
                                    
                                    Select::make('conditions.priority')
                                        ->label(__('creators-ticketing::resources.automation.conditions.priority'))
                                        ->multiple()
                                        ->options(TicketPriority::class),
                                    
                                    Select::make('conditions.assignee_id')
                                        ->label(__('creators-ticketing::resources.automation.conditions.assignee'))
                                        ->multiple()
                                        ->options(fn (callable $get): array => [
                                            'unassigned' => __('creators-ticketing::resources.automation.options.unassigned'),
                                            'assigned' => __('creators-ticketing::resources.automation.options.any_assigned'),
                                        ] + $getFilteredUserOptions($get))
                                        ->searchable()
                                        ->preload(),
                                
                                    Select::make('conditions.requester_id')
                                        ->label(__('creators-ticketing::resources.automation.conditions.requester'))
                                        ->multiple()
                                        ->searchable()
                                        ->getSearchResultsUsing(function (string $search) use ($userModel) {
                                            $userInstance = new $userModel;
                                            $userKey = $userInstance->getKeyName();
                                            $nameColumn = config('creators-ticketing.user_name_column', 'name');
                                            return $userModel::where($nameColumn, 'like', "%{$search}%")->limit(50)->pluck($nameColumn, $userKey);
                                        })
                                        ->getOptionLabelsUsing(function (array $values) use ($userModel) {
                                            $userInstance = new $userModel;
                                            $userKey = $userInstance->getKeyName();
                                            $nameColumn = config('creators-ticketing.user_name_column', 'name');
                                            return $userModel::whereIn($userKey, $values)->pluck($nameColumn, $userKey);
                                        })
                                        ->helperText(__('creators-ticketing::resources.automation.conditions.requester_helper')),
                                
                                    TextInput::make('conditions.created_within_hours')
                                        ->numeric()
                                        ->label(__('creators-ticketing::resources.automation.conditions.created_within'))
                                        ->helperText(__('creators-ticketing::resources.automation.conditions.created_within_helper')),
                                
                                    TextInput::make('conditions.last_activity_within_hours')
                                        ->numeric()
                                        ->label(__('creators-ticketing::resources.automation.conditions.last_activity_within'))
                                        ->helperText(__('creators-ticketing::resources.automation.conditions.last_activity_helper')),
                                
                                    Select::make('conditions.old_status_id')
                                        ->label(__('creators-ticketing::resources.automation.conditions.previous_status'))
                                        ->multiple()
                                        ->options(TicketStatus::pluck('name', 'id'))
                                        ->searchable()
                                        ->visible(fn (callable $get) => $get('trigger_event') === 'status_changed'),
                                
                                    Select::make('conditions.old_priority')
                                        ->label(__('creators-ticketing::resources.automation.conditions.previous_priority'))
                                        ->multiple()
                                        ->options(TicketPriority::class)
                                        ->visible(fn (callable $get) => $get('trigger_event') === 'priority_changed'),
                            ])->columns(2),
                    ]),
                
                Tab::make(__('creators-ticketing::resources.automation.tabs.actions'))
                    ->schema([
                        Section::make(__('creators-ticketing::resources.automation.sections.actions_perform'))
                            ->description(__('creators-ticketing::resources.automation.sections.actions_desc'))
                            ->schema([
                                Select::make('actions.assign_to')
                                    ->label(__('creators-ticketing::resources.automation.actions.assign_to'))
                                    ->options(fn (callable $get): array => [
                                        'unassigned' => __('creators-ticketing::resources.automation.options.leave_unassigned'),
                                        'round_robin' => __('creators-ticketing::resources.automation.options.round_robin'),
                                        'least_loaded' => __('creators-ticketing::resources.automation.options.least_loaded'),
                                    ] + $getFilteredUserOptions($get))
                                    ->searchable()
                                    ->helperText(__('creators-ticketing::resources.automation.actions.assign_to_helper')),
                                
                                Select::make('actions.set_status')
                                    ->label(__('creators-ticketing::resources.automation.actions.set_status'))
                                    ->options(TicketStatus::pluck('name', 'id'))
                                    ->searchable()
                                    ->helperText(__('creators-ticketing::resources.automation.actions.set_status_helper')),
                                
                                Select::make('actions.set_priority')
                                    ->label(__('creators-ticketing::resources.automation.actions.set_priority'))
                                    ->options(TicketPriority::class)
                                    ->helperText(__('creators-ticketing::resources.automation.actions.set_priority_helper')),
                                
                                Select::make('actions.transfer_to_department')
                                    ->label(__('creators-ticketing::resources.automation.actions.transfer'))
                                    ->options(Department::pluck('name', 'id'))
                                    ->searchable()
                                    ->helperText(__('creators-ticketing::resources.automation.actions.transfer_helper')),
                                
                                Textarea::make('actions.add_internal_note')
                                    ->label(__('creators-ticketing::resources.automation.actions.internal_note'))
                                    ->rows(3)
                                    ->helperText(__('creators-ticketing::resources.automation.actions.internal_note_helper')),
                                
                                Textarea::make('actions.add_public_reply')
                                    ->label(__('creators-ticketing::resources.automation.actions.public_reply'))
                                    ->rows(3)
                                    ->helperText(__('creators-ticketing::resources.automation.actions.public_reply_helper')),
                                
                            ])->columns(2),

                            Section::make(__('creators-ticketing::resources.automation.sections.placeholders'))
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    Placeholder::make('placeholders')
                                        ->content(
                                            '{ticket_id}, {ticket_title}, {department}, {requester}, {assignee}, ' .
                                            '{status}, {priority}, {rule_name}, {current_date}, {current_time}'
                                        ),
                                ])
                                ->columnSpanFull(),

                    ]),
                
            ])
            ->columnSpanFull(),
    ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight('bold'),
            
            TextColumn::make('trigger_event')
                ->label(__('creators-ticketing::resources.automation.columns.trigger'))
                ->badge()
                ->formatStateUsing(fn ($state) => __('creators-ticketing::resources.automation.triggers.' . $state)),
            
            IconColumn::make('is_active')
                ->label(__('creators-ticketing::resources.automation.columns.active'))
                ->boolean(),
            
            TextColumn::make('times_triggered')
                ->label(__('creators-ticketing::resources.automation.columns.executions'))
                ->sortable()
                ->alignCenter(),
            
            TextColumn::make('last_triggered_at')
                ->label(__('creators-ticketing::resources.automation.columns.last_executed'))
                ->dateTime()
                ->sortable()
                ->since(),
            
            TextColumn::make('execution_order')
                ->label(__('creators-ticketing::resources.automation.columns.order'))
                ->sortable()
                ->alignCenter(),
            
            IconColumn::make('stop_processing')
                ->label(__('creators-ticketing::resources.automation.columns.stop'))
                ->boolean()
                ->alignCenter(),
        ])
        ->filters([
            SelectFilter::make('trigger_event')
                ->options([
                    'ticket_created' => __('creators-ticketing::resources.automation.triggers.ticket_created'),
                    'ticket_updated' => __('creators-ticketing::resources.automation.triggers.ticket_updated'),
                    'status_changed' => __('creators-ticketing::resources.automation.triggers.status_changed'),
                    'priority_changed' => __('creators-ticketing::resources.automation.triggers.priority_changed'),
                    'ticket_assigned' => __('creators-ticketing::resources.automation.triggers.ticket_assigned'),
                ]),
            
            TernaryFilter::make('is_active')
                ->label(__('creators-ticketing::resources.form.filters.active'))
                ->boolean()
                ->trueLabel(__('creators-ticketing::resources.form.filters.active_only'))
                ->falseLabel(__('creators-ticketing::resources.form.filters.inactive_only'))
                ->native(false),
        ])
        ->actions([
            EditAction::make(),
            Action::make('view_logs')
                ->label(__('creators-ticketing::resources.logs.title'))
                ->icon('heroicon-o-clock')
                ->modalHeading(__('creators-ticketing::resources.logs.title'))
                ->modalWidth('5xl')
                ->slideOver()
                ->modalActions([])
                ->authorize(fn (): bool => static::userCan('can_view_automation_logs'))
                ->mountUsing(function ($form, $record) {
                    $form->fill([
                        'logs' => $record->logs()
                            ->latest()
                            ->take(20)
                            ->with('ticket')
                            ->get()
                            ->map(function ($log) {
                                $conds = collect($log->conditions_met ?? [])->mapWithKeys(fn($v, $k) => [
                                    ucwords(str_replace('_', ' ', $k)) => is_array($v) ? implode(', ', $v) : $v
                                ])->toArray();

                                $actions = collect($log->actions_performed ?? [])->mapWithKeys(fn($v, $k) => [
                                    ucwords(str_replace('_', ' ', $k)) => is_array($v) ? json_encode($v) : $v
                                ])->toArray();

                                return [
                                    'id' => $log->id,
                                    'created_at' => $log->created_at->format('M d, Y H:i:s'),
                                    'ticket_info' => $log->ticket ? "#{$log->ticket->ticket_uid} - " . str($log->ticket->title)->limit(40) : 'Ticket Deleted',
                                    'trigger_event' => ucwords(str_replace('_', ' ', $log->trigger_event)),
                                    'success' => $log->success,
                                    'error_message' => $log->error_message,
                                    'conditions_met' => $conds,
                                    'actions_performed' => $actions,
                                    'status_color' => $log->success ? 'text-success-600' : 'text-danger-600',
                                    'icon' => $log->success ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle',
                                ];
                            })
                            ->toArray()
                    ]);
                })
                ->form([
                    Repeater::make('logs')
                        ->label('')
                        ->hiddenLabel()
                        ->disableItemCreation()
                        ->disableItemDeletion()
                        ->disableItemMovement()
                        ->collapsed()
                        ->itemLabel(fn (array $state): HtmlString => new HtmlString(
                            sprintf(
                                '<div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3">
                                        <div class="%s">
                                            <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                %s
                                            </svg>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-sm">%s</span>
                                            <span class="text-xs text-gray-500">%s</span>
                                        </div>
                                    </div>
                                    <div class="text-xs font-mono text-gray-400">%s</div>
                                </div>',
                                $state['status_color'] ?? 'text-gray-500',
                                ($state['success'] ?? false)
                                    ? '<path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />'
                                    : '<path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" />',
                                $state['ticket_info'] ?? 'Unknown',
                                $state['trigger_event'] ?? 'Trigger',
                                $state['created_at'] ?? 'N/A'
                            )
                        ))
                        ->schema([
                            Hidden::make('id'),
                            
                            Grid::make(2)
                                ->schema([
                                    KeyValue::make('conditions_met')
                                        ->label(__('creators-ticketing::resources.logs.conditions'))
                                        ->disabled()
                                        ->columnSpan(1),
                                    
                                    KeyValue::make('actions_performed')
                                        ->label(__('creators-ticketing::resources.logs.actions'))
                                        ->disabled()
                                        ->columnSpan(1),
                                ]),

                            Textarea::make('error_message')
                                ->label(__('creators-ticketing::resources.logs.error'))
                                ->visible(fn ($get) => !empty($get('error_message')))
                                ->disabled()
                                ->rows(2)
                                ->columnSpanFull()
                                ->extraAttributes(['class' => 'bg-red-50 text-red-700 border-red-200']),
                        ])
                ]),
            
            Action::make('toggle')
                ->label(fn ($record) => $record->is_active ? __('creators-ticketing::resources.automation.actions.deactivate') : __('creators-ticketing::resources.automation.actions.activate'))
                ->icon(fn ($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                ->action(fn ($record) => $record->update(['is_active' => !$record->is_active])),
        ])
        ->bulkActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                BulkAction::make('activate')
                    ->label(__('creators-ticketing::resources.automation.actions.activate_selected'))
                    ->icon('heroicon-o-play')
                    ->action(fn ($records) => $records->each->update(['is_active' => true])),
                BulkAction::make('deactivate')
                    ->label(__('creators-ticketing::resources.automation.actions.deactivate_selected'))
                    ->icon('heroicon-o-pause')
                    ->action(fn ($records) => $records->each->update(['is_active' => false])),
            ]),
        ])
        ->defaultSort('execution_order');
}


    public static function getRelations(): array
    {
        return [
            AutomationLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomationRules::route('/'),
            'create' => Pages\CreateAutomationRule::route('/create'),
            'edit' => Pages\EditAutomationRule::route('/{record}/edit'),
        ];
    }
}