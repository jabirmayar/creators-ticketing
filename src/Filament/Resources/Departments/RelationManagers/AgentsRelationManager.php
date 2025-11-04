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

    public function table(Table $table): Table
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'editor' => 'warning',
                        'agent' => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('pivot.can_create_tickets')
                    ->label('Create')
                    ->boolean(),
                IconColumn::make('pivot.can_view_all_tickets')
                    ->label('View All')
                    ->boolean(),
                IconColumn::make('pivot.can_assign_tickets')
                    ->label('Assign')
                    ->boolean(),
                IconColumn::make('pivot.can_change_departments')
                    ->label('Change Department')
                    ->boolean(),
                IconColumn::make('pivot.can_change_status')
                    ->label('Status')
                    ->boolean(),
                IconColumn::make('pivot.can_change_priority')
                    ->label('Priority')
                    ->boolean(),
                IconColumn::make('pivot.can_reply_to_tickets')
                    ->label('Reply')
                    ->boolean(),
                IconColumn::make('pivot.can_add_internal_notes')
                    ->label('Add Notes')
                    ->boolean(),
                IconColumn::make('pivot.can_view_internal_notes')
                    ->label('View Notes')
                    ->boolean(),
                IconColumn::make('pivot.can_delete_tickets')
                    ->label('Delete')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Action::make('Add Users')
                        ->form([
                            Select::make('user_id')
                                ->label('Agent')
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
                                ->label('Role')
                                ->options([
                                    'admin' => 'Department Admin',
                                    'editor' => 'Editor',
                                    'agent' => 'Agent',
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
                                ->schema([
                                    Toggle::make('can_create_tickets')
                                            ->label('Create tickets')
                                            ->helperText('Can create tickets in this department.'),
                                    Toggle::make('can_view_all_tickets')
                                        ->label('View all department tickets')
                                        ->helperText('Can view all tickets in this department, not just assigned ones.'),
                                    Toggle::make('can_assign_tickets')
                                        ->label('Assign tickets')
                                        ->helperText('Can assign tickets to other agents.'),
                                    Toggle::make('can_change_departments')
                                        ->label('Change ticket department')
                                        ->helperText('Can change the department of tickets.'),
                                    Toggle::make('can_change_status')
                                        ->label('Change ticket status')
                                        ->helperText('Can change the status of tickets.'),
                                    Toggle::make('can_change_priority')
                                        ->label('Change ticket priority')
                                        ->helperText('Can change the priority of tickets.'),
                                    Toggle::make('can_reply_to_tickets')
                                        ->label('Reply to tickets')
                                        ->helperText('Can send public replies to tickets.'),
                                    Toggle::make('can_add_internal_notes')
                                        ->label('Add internal notes')
                                        ->helperText('Can add internal notes on tickets.'),
                                    Toggle::make('can_view_internal_notes')
                                        ->label('View internal notes')
                                        ->helperText('Can view internal notes on tickets.'),
                                    Toggle::make('can_delete_tickets')
                                        ->label('Delete tickets')
                                        ->helperText('Can delete tickets (use with caution).'),
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
                                    ->title('No new agents to attach')
                                    ->body('Selected agents are already attached to this department.')
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
                                ->title('Agents attached')
                                ->body(count($newAgentIds) . ' agent(s) attached successfully.')
                                ->send();
                        })
                        ->icon('heroicon-o-user-plus')
                        ->modalHeading('Add Agents')
                        ->modalSubmitActionLabel('Add'),
            ])
            ->actions([
                EditAction::make()
                    ->form(function (Model $record) {
                        return [
                            Select::make('role')
                                ->label('Role')
                                ->options([
                                    'admin' => 'Department Admin',
                                    'editor' => 'Editor',
                                    'agent' => 'Agent',
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
                                ->schema([
                                    Toggle::make('can_create_tickets')
                                            ->label('Create tickets')
                                            ->default($record->pivot->can_create_tickets)
                                            ->helperText('Can create tickets in this department.'),
                                    Toggle::make('can_view_all_tickets')
                                        ->label('View all department tickets')
                                        ->default($record->pivot->can_view_all_tickets)
                                        ->helperText('Can view all tickets in this department, not just assigned ones.'),
                                    Toggle::make('can_assign_tickets')
                                        ->label('Assign tickets')
                                        ->default($record->pivot->can_assign_tickets)
                                        ->helperText('Can assign tickets to other agents.'),
                                    Toggle::make('can_change_departments')
                                        ->label('Change ticket department')
                                        ->default($record->pivot->can_change_departments)
                                        ->helperText('Can change the department of tickets.'),
                                    Toggle::make('can_change_status')
                                        ->label('Change ticket status')
                                        ->default($record->pivot->can_change_status)
                                        ->helperText('Can change the status of tickets.'),
                                    Toggle::make('can_change_priority')
                                        ->label('Change ticket priority')
                                        ->default($record->pivot->can_change_priority)
                                        ->helperText('Can change the priority of tickets.'),
                                    Toggle::make('can_reply_to_tickets')
                                        ->label('Reply to tickets')
                                        ->default($record->pivot->can_reply_to_tickets)
                                        ->helperText('Can send public replies to tickets.'),
                                    Toggle::make('can_add_internal_notes')
                                        ->label('Add internal notes')
                                        ->default($record->pivot->can_add_internal_notes)
                                        ->helperText('Can add internal notes on tickets.'),
                                    Toggle::make('can_view_internal_notes')
                                        ->label('View internal notes')
                                        ->default($record->pivot->can_view_internal_notes)
                                        ->helperText('Can view internal notes on tickets.'),
                                    Toggle::make('can_delete_tickets')
                                        ->label('Delete tickets')
                                        ->default($record->pivot->can_delete_tickets)
                                        ->helperText('Can delete tickets (use with caution).'),
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
                            ->title('Agent permissions updated')
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