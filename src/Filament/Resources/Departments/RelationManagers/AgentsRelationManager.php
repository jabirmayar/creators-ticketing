<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Departments\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DetachBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;

class AgentsRelationManager extends RelationManager
{
    protected static string $relationship = 'agents';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('creators-ticketing::resources.agent.title');
    }

    public function table(Table $table): Table
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('creators-ticketing::resources.agent.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('creators-ticketing::resources.agent.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pivot.role')
                    ->label(__('creators-ticketing::resources.agent.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => __('creators-ticketing::resources.agent.roles.admin'),
                        'editor' => __('creators-ticketing::resources.agent.roles.editor'),
                        'agent' => __('creators-ticketing::resources.agent.roles.agent'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'editor' => 'warning',
                        'agent' => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('pivot.can_create_tickets')
                    ->label(__('creators-ticketing::resources.agent.columns.create'))
                    ->boolean(),
                IconColumn::make('pivot.can_view_all_tickets')
                    ->label(__('creators-ticketing::resources.agent.columns.view_all'))
                    ->boolean(),
                IconColumn::make('pivot.can_assign_tickets')
                    ->label(__('creators-ticketing::resources.agent.columns.assign'))
                    ->boolean(),
                IconColumn::make('pivot.can_change_departments')
                    ->label(__('creators-ticketing::resources.agent.columns.change_dept'))
                    ->boolean(),
                IconColumn::make('pivot.can_change_status')
                    ->label(__('creators-ticketing::resources.agent.columns.status'))
                    ->boolean(),
                IconColumn::make('pivot.can_change_priority')
                    ->label(__('creators-ticketing::resources.agent.columns.priority'))
                    ->boolean(),
                IconColumn::make('pivot.can_reply_to_tickets')
                    ->label(__('creators-ticketing::resources.agent.columns.reply'))
                    ->boolean(),
                IconColumn::make('pivot.can_add_internal_notes')
                    ->label(__('creators-ticketing::resources.agent.columns.add_notes'))
                    ->boolean(),
                IconColumn::make('pivot.can_view_internal_notes')
                    ->label(__('creators-ticketing::resources.agent.columns.view_notes'))
                    ->boolean(),
                IconColumn::make('pivot.can_delete_tickets')
                    ->label(__('creators-ticketing::resources.agent.columns.delete'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('creators-ticketing::resources.form.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Action::make('Add Users')
                        ->label(__('creators-ticketing::resources.agent.add_agents'))
                        ->form([
                            Select::make('user_id')
                                ->label(__('creators-ticketing::resources.agent.select_agent'))
                                ->searchable()
                                ->multiple()
                                ->getSearchResultsUsing(function (string $search) use ($userModel) {
                                    return $userModel::query()
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($user) => [
                                            $user->id => "{$user->name} - {$user->email}"
                                        ]);
                                })
                                ->getOptionLabelsUsing(function (array $values) use ($userModel) {
                                    return $userModel::whereIn('id', $values)
                                        ->get()
                                        ->mapWithKeys(fn ($user) => [
                                            $user->id => "{$user->name} - {$user->email}"
                                        ])
                                        ->toArray();
                                })
                                ->preload(false)
                                ->required(),
                            Select::make('role')
                                ->label(__('creators-ticketing::resources.agent.role'))
                                ->options([
                                    'admin' => __('creators-ticketing::resources.agent.roles.admin'),
                                    'editor' => __('creators-ticketing::resources.agent.roles.editor'),
                                    'agent' => __('creators-ticketing::resources.agent.roles.agent'),
                                ])
                                ->default(null)
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state === 'admin') {
                                        $set('can_create_tickets', true);
                                        $set('can_view_all_tickets', true);
                                        $set('can_assign_tickets', true);
                                        $set('can_change_departments', true);
                                        $set('can_change_status', true);
                                        $set('can_change_priority', true);
                                        $set('can_delete_tickets', true);
                                        $set('can_reply_to_tickets', true);
                                        $set('can_add_internal_notes', true);
                                        $set('can_view_internal_notes', true);
                                    } elseif ($state === 'editor') {
                                        $set('can_create_tickets', false);
                                        $set('can_view_all_tickets', true);
                                        $set('can_assign_tickets', true);
                                        $set('can_change_departments', false);
                                        $set('can_change_status', true);
                                        $set('can_change_priority', true);
                                        $set('can_delete_tickets', false);
                                        $set('can_reply_to_tickets', true);
                                        $set('can_add_internal_notes', true);
                                        $set('can_view_internal_notes', true);
                                    } elseif ($state === 'agent') {
                                        $set('can_create_tickets', false);
                                        $set('can_view_all_tickets', false);
                                        $set('can_assign_tickets', false);
                                        $set('can_change_departments', false);
                                        $set('can_change_status', true);
                                        $set('can_change_priority', true);
                                        $set('can_delete_tickets', false);
                                        $set('can_reply_to_tickets', true);
                                        $set('can_add_internal_notes', false);
                                        $set('can_view_internal_notes', true);
                                    }
                                })
                                ->required(),
                            Section::make('Permissions')
                                ->label(__('creators-ticketing::resources.agent.permissions_section'))
                                ->schema([
                                    Toggle::make('can_create_tickets')
                                            ->label(__('creators-ticketing::resources.agent.permissions.can_create_tickets'))
                                            ->helperText(__('creators-ticketing::resources.agent.permissions.can_create_tickets_helper')),
                                    Toggle::make('can_view_all_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_view_all_tickets'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_view_all_tickets_helper')),
                                    Toggle::make('can_assign_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_assign_tickets'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_assign_tickets_helper')),
                                    Toggle::make('can_change_departments')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_change_departments'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_change_departments_helper')),
                                    Toggle::make('can_change_status')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_change_status'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_change_status_helper')),
                                    Toggle::make('can_change_priority')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_change_priority'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_change_priority_helper')),
                                    Toggle::make('can_reply_to_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_reply_to_tickets'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_reply_to_tickets_helper')),
                                    Toggle::make('can_add_internal_notes')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_add_internal_notes'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_add_internal_notes_helper')),
                                    Toggle::make('can_view_internal_notes')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_view_internal_notes'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_view_internal_notes_helper')),
                                    Toggle::make('can_delete_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_delete_tickets'))
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_delete_tickets_helper')),
                                ])
                                ->columns(2),
                        ])
                        ->action(function (array $data) {
                            $existingAgents = $this->getOwnerRecord()->agents()->get();
                            $existingAgentIds = $existingAgents->pluck('id')->toArray();

                            $newAgentIds = array_diff($data['user_id'], $existingAgentIds);

                            if (empty($newAgentIds)) {
                                Notification::make()
                                    ->warning()
                                    ->title(__('creators-ticketing::resources.agent.notifications.no_new_title'))
                                    ->body(__('creators-ticketing::resources.agent.notifications.no_new_body'))
                                    ->send();
                                return;
                            }

                            $syncData = [];
                            $permissionFields = [
                                'role',
                                'can_create_tickets',
                                'can_view_all_tickets',
                                'can_assign_tickets',
                                'can_change_departments',
                                'can_change_status',
                                'can_change_priority',
                                'can_delete_tickets',
                                'can_reply_to_tickets',
                                'can_add_internal_notes',
                                'can_view_internal_notes',
                            ];

                            foreach ($newAgentIds as $userId) {
                                $userPermissions = [];
                                foreach ($permissionFields as $field) {
                                    $userPermissions[$field] = $data[$field] ?? false;
                                }
                                $syncData[$userId] = $userPermissions;
                            }

                            $this->getOwnerRecord()->agents()->attach($syncData);

                            Notification::make()
                                ->success()
                                ->title(__('creators-ticketing::resources.agent.notifications.attached_title'))
                                ->body(__('creators-ticketing::resources.agent.notifications.attached_body', ['count' => count($newAgentIds)]))
                                ->send();
                        })
                        ->icon('heroicon-o-user-plus')
                        ->modalHeading(__('creators-ticketing::resources.agent.add_agents'))
                        ->modalSubmitActionLabel(__('creators-ticketing::resources.agent.add_submit')),
            ])
            ->actions([
                EditAction::make()
                    ->form(function (Model $record) {
                        return [
                            Select::make('role')
                                ->label(__('creators-ticketing::resources.agent.role'))
                                ->options([
                                    'admin' => __('creators-ticketing::resources.agent.roles.admin'),
                                    'editor' => __('creators-ticketing::resources.agent.roles.editor'),
                                    'agent' => __('creators-ticketing::resources.agent.roles.agent'),
                                ])
                                ->default($record->pivot->role)
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state === 'admin') {
                                        $set('can_create_tickets', true);
                                        $set('can_view_all_tickets', true);
                                        $set('can_assign_tickets', true);
                                        $set('can_change_departments', true);
                                        $set('can_change_status', true);
                                        $set('can_change_priority', true);
                                        $set('can_delete_tickets', true);
                                        $set('can_reply_to_tickets', true);
                                        $set('can_add_internal_notes', true);
                                        $set('can_view_internal_notes', true);
                                    } elseif ($state === 'editor') {
                                        $set('can_create_tickets', false);
                                        $set('can_view_all_tickets', true);
                                        $set('can_assign_tickets', true);
                                        $set('can_change_departments', false);
                                        $set('can_change_status', true);
                                        $set('can_change_priority', true);
                                        $set('can_delete_tickets', false);
                                        $set('can_reply_to_tickets', true);
                                        $set('can_add_internal_notes', true);
                                        $set('can_view_internal_notes', true);
                                    } elseif ($state === 'agent') {
                                        $set('can_create_tickets', false);
                                        $set('can_view_all_tickets', false);
                                        $set('can_assign_tickets', false);
                                        $set('can_change_departments', false);
                                        $set('can_change_status', true);
                                        $set('can_change_priority', true);
                                        $set('can_delete_tickets', false);
                                        $set('can_reply_to_tickets', true);
                                        $set('can_add_internal_notes', false);
                                        $set('can_view_internal_notes', true);
                                    }
                                })
                                ->required(),
                            Section::make('Permissions')
                                ->label(__('creators-ticketing::resources.agent.permissions_section'))
                                ->schema([
                                    Toggle::make('can_create_tickets')
                                            ->label(__('creators-ticketing::resources.agent.permissions.can_create_tickets'))
                                            ->default($record->pivot->can_create_tickets)
                                            ->helperText(__('creators-ticketing::resources.agent.permissions.can_create_tickets_helper')),
                                    Toggle::make('can_view_all_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_view_all_tickets'))
                                        ->default($record->pivot->can_view_all_tickets)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_view_all_tickets_helper')),
                                    Toggle::make('can_assign_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_assign_tickets'))
                                        ->default($record->pivot->can_assign_tickets)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_assign_tickets_helper')),
                                    Toggle::make('can_change_departments')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_change_departments'))
                                        ->default($record->pivot->can_change_departments)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_change_departments_helper')),
                                    Toggle::make('can_change_status')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_change_status'))
                                        ->default($record->pivot->can_change_status)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_change_status_helper')),
                                    Toggle::make('can_change_priority')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_change_priority'))
                                        ->default($record->pivot->can_change_priority)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_change_priority_helper')),
                                    Toggle::make('can_reply_to_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_reply_to_tickets'))
                                        ->default($record->pivot->can_reply_to_tickets)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_reply_to_tickets_helper')),
                                    Toggle::make('can_add_internal_notes')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_add_internal_notes'))
                                        ->default($record->pivot->can_add_internal_notes)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_add_internal_notes_helper')),
                                    Toggle::make('can_view_internal_notes')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_view_internal_notes'))
                                        ->default($record->pivot->can_view_internal_notes)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_view_internal_notes_helper')),
                                    Toggle::make('can_delete_tickets')
                                        ->label(__('creators-ticketing::resources.agent.permissions.can_delete_tickets'))
                                        ->default($record->pivot->can_delete_tickets)
                                        ->helperText(__('creators-ticketing::resources.agent.permissions.can_delete_tickets_helper')),
                                ])
                                ->columns(2),
                        ];
                    })
                    ->action(function (Model $record, array $data) {
                        $record->pivot->update([
                            'role' => $data['role'],
                            'can_create_tickets' => $data['can_create_tickets'],
                            'can_view_all_tickets' => $data['can_view_all_tickets'],
                            'can_assign_tickets' => $data['can_assign_tickets'],
                            'can_change_departments' => $data['can_change_departments'],
                            'can_change_status' => $data['can_change_status'],
                            'can_change_priority' => $data['can_change_priority'],
                            'can_delete_tickets' => $data['can_delete_tickets'],
                            'can_reply_to_tickets' => $data['can_reply_to_tickets'],
                            'can_add_internal_notes' => $data['can_add_internal_notes'],
                            'can_view_internal_notes' => $data['can_view_internal_notes'],
                        ]);
                        Notification::make()
                            ->success()
                            ->title(__('creators-ticketing::resources.agent.notifications.permissions_updated'))
                            ->send();
                    }),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}