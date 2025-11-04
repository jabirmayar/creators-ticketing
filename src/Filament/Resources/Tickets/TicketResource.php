<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets;

use BackedEnum;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Forms\Components\Radio;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Component;
use Filament\Infolists\Components\TextEntry;
use daacreators\CreatorsTicketing\Models\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use Filament\Infolists\Components\Section as InfoSection;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\RelationManagers\InternalNotesRelationManager;

class TicketResource extends Resource
{
    use HasTicketPermissions;

    protected static ?string $model = Ticket::class;
    
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    public static function canViewAny(): bool
    {
        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }
        $user = Filament::auth()->user();
        if (!$user) {
            return false;
        }
        return !empty($permissions['departments']) || $user->tickets()->exists();
    }

    public static function canCreate(): bool
    {
        $permissions = (new static)->getUserPermissions();
        
        if ($permissions['is_admin']) {
            return true;
        }
        
        foreach ($permissions['permissions'] as $deptPerms) {
            if ($deptPerms['can_create_tickets']) {
                return true;
            }
        }
        
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        if (!$record instanceof Ticket) {
            return false;
        }

        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }

        $user = Filament::auth()->user();
        if (!$user) {
            return false;
        }

        if ($record->user_id === $user->id) {
            return true;
        }

        if (in_array($record->department_id, $permissions['departments'])) {
            $departmentPermission = $permissions['permissions'][$record->department_id] ?? null;
            if ($departmentPermission && ($departmentPermission['can_assign_tickets'] || $departmentPermission['can_change_status'] || $departmentPermission['can_change_priority'] || $departmentPermission['can_reply_to_tickets'])) {
                return true;
            }
        }
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        if (!$record instanceof Ticket) {
            return false;
        }

        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }

        if (in_array($record->department_id, $permissions['departments'])) {
            $departmentPermission = $permissions['permissions'][$record->department_id] ?? null;
            if ($departmentPermission && $departmentPermission['can_delete_tickets']) {
                return true;
            }
        }
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $permissions = (new static)->getUserPermissions();
        $user = Filament::auth()->user();

        return $schema->schema([
            Group::make()->schema([
                Section::make('Ticket Details')
                    ->schema([
                        Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name', function ($query) use ($permissions) {
                                $query->where('is_active', true);
                                
                                if (!$permissions['is_admin'] && !empty($permissions['departments'])) {
                                    $departmentsWithCreatePermission = collect($permissions['permissions'])
                                        ->filter(fn($perm) => $perm['can_create_tickets'] ?? false)
                                        ->keys()
                                        ->toArray();
                                    
                                    if (!empty($departmentsWithCreatePermission)) {
                                        $query->whereIn('id', $departmentsWithCreatePermission);
                                    }
                                }
                            })
                            ->required()
                            ->visible(fn (?Model $record) => $record === null)
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('custom_fields', []);
                            }),
                        
                        Group::make()
                            ->schema(fn (Get $get, ?Model $record): array => static::getDynamicFormFields($record, $get('department_id'), $permissions, $user))
                            ->visible(fn (?Model $record, Get $get) => 
                                $record !== null || $get('department_id') !== null
                            )
                            ->columnSpanFull(),
                    ]),
            ])->columnSpan(2),
            
            Group::make()->schema([
                Section::make('Properties')
                    ->schema([
                        Select::make('user_id')
                            ->label('Requester')
                            ->relationship('requester', 'name')
                            ->searchable()
                            ->required()
                            ->default(auth()->id())
                            ->visible(fn (?Model $record) => 
                                $permissions['is_admin'] || 
                                ($record === null && !empty(collect($permissions['permissions'])->filter(fn($p) => $p['can_assign_tickets'] ?? false))) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false))
                            )
                            ->disabled(fn (?Model $record) => $record instanceof Ticket && !$permissions['is_admin']),
                        
                        Select::make('assignee_id')
                            ->label('Assignee')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => 
                                User::where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email])
                            )
                            ->getOptionLabelUsing(fn ($value): ?string => 
                                User::find($value)?->name . ' - ' . User::find($value)?->email
                            )
                            ->preload(false)
                            ->native(false)
                            ->visible(fn (?Model $record, Get $get) => 
                                $permissions['is_admin'] || 
                                ($record === null && $get('department_id') && ($permissions['permissions'][$get('department_id')]['can_assign_tickets'] ?? false)) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false))
                            )
                            ->disabled(fn (?Model $record, Get $get) => 
                                $record instanceof Ticket && !$permissions['is_admin'] && 
                                !($record->department && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false))
                            ),
                        
                        Select::make('department_id')
                            ->relationship('department', 'name')
                            ->required()
                            ->visible(fn (?Model $record) => 
                                $record !== null && $permissions['is_admin']
                            )
                            ->disabled(fn (?Model $record) => $record instanceof Ticket && !$permissions['is_admin']),
                        
                        Select::make('ticket_status_id')
                            ->label('Status')
                            ->relationship('status', 'name')
                            ->visible(fn (?Model $record, Get $get) => 
                                $permissions['is_admin'] || 
                                ($record === null && $get('department_id') && ($permissions['permissions'][$get('department_id')]['can_change_status'] ?? false)) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_status'] ?? false))
                            )
                            ->disabled(fn (?Model $record, Get $get) => 
                                $record instanceof Ticket && !$permissions['is_admin'] && 
                                !($record->department && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_status'] ?? false))
                            ),
                        
                        Select::make('priority')
                            ->label('Priority')
                            ->options(TicketPriority::class)
                            ->enum(TicketPriority::class)
                            ->required()
                            ->default(TicketPriority::LOW)
                            ->visible(fn (?Model $record, Get $get) => 
                                $permissions['is_admin'] || 
                                ($record === null && $get('department_id') && ($permissions['permissions'][$get('department_id')]['can_change_priority'] ?? false)) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_priority'] ?? false))
                            )
                            ->disabled(fn (?Model $record, Get $get) => 
                                $record instanceof Ticket && !$permissions['is_admin'] && 
                                !($record->department && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_priority'] ?? false))
                            ),
                    ]),
            ])->columnSpan(1),
            ])->columns(3);
    }

    protected static function getDynamicFormFields(?Model $record, $departmentId, $permissions, $user): array
    {
        if (!$departmentId) {
            $departmentId = $record?->department_id;
        }

        if (!$departmentId) {
            return [];
        }

        $department = Department::find($departmentId);
        if (!$department) {
            return [];
        }

        $form = $department->forms()->with('fields')->first();
        if (!$form || !$form->fields->count()) {
            return [
                Placeholder::make('no_form')
                    ->label('')
                    ->content('No form configured for this department')
                    ->columnSpanFull(),
            ];
        }

        $fields = [];
        $isDisabled = $record instanceof Ticket && !$permissions['is_admin'] && 
                      $record->user_id !== $user->id && 
                      !in_array($record->department_id, $permissions['departments']);

        foreach ($form->fields as $field) {
            $fieldComponent = null;
            $fieldName = "custom_fields.{$field->name}";

            switch ($field->type) {
                case 'text':
                    $fieldComponent = TextInput::make($fieldName)
                        ->label($field->label)
                        ->required($field->is_required)
                        ->disabled($isDisabled);
                    break;

                case 'textarea':
                    $fieldComponent = Textarea::make($fieldName)
                        ->label($field->label)
                        ->required($field->is_required)
                        ->rows(4)
                        ->disabled($isDisabled)
                        ->columnSpanFull();
                    break;

                case 'rich_editor':
                    $fieldComponent = RichEditor::make($fieldName)
                        ->label($field->label)
                        ->required($field->is_required)
                        ->disabled($isDisabled)
                        ->columnSpanFull();
                    break;

                case 'select':
                    $fieldComponent = Select::make($fieldName)
                        ->label($field->label)
                        ->options($field->options ?? [])
                        ->required($field->is_required)
                        ->disabled($isDisabled);
                    break;

                case 'radio':
                    $fieldComponent = Radio::make($fieldName)
                        ->label($field->label)
                        ->options($field->options ?? [])
                        ->required($field->is_required)
                        ->disabled($isDisabled);
                    break;

                case 'checkbox':
                    $fieldComponent = Checkbox::make($fieldName)
                        ->label($field->label)
                        ->required($field->is_required)
                        ->disabled($isDisabled);
                    break;

                case 'toggle':
                    $fieldComponent = Toggle::make($fieldName)
                        ->label($field->label)
                        ->required($field->is_required)
                        ->disabled($isDisabled);
                    break;

                case 'file':
                    $fieldComponent = FileUpload::make($fieldName)
                        ->label($field->label)
                        ->required($field->is_required)
                        ->disabled($isDisabled)
                        ->columnSpanFull();
                    break;
            }

            if ($fieldComponent) {
                $fields[] = $fieldComponent;
            }
        }

        return $fields;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                InfoSection::make('Ticket Information')
                    ->schema([
                        TextEntry::make('ticket_uid')
                            ->label('Ticket ID'),
                        
                        TextEntry::make('title'),
                        
                        TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),
                        
                        TextEntry::make('requester.name')
                            ->label('Requester'),
                        
                        TextEntry::make('assignee.name')
                            ->label('Assignee')
                            ->default('Unassigned'),
                        
                        TextEntry::make('department.name')
                            ->label('Department'),
                        
                        TextEntry::make('status.name')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($record) => $record->status?->color ?? 'gray'),
                        
                        TextEntry::make('priority')
                            ->badge(),
                        
                        TextEntry::make('created_at')
                            ->dateTime(),
                        
                        TextEntry::make('last_activity_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                
                InfoSection::make('Custom Fields')
                    ->schema(function (Ticket $record) {
                        $department = $record->department;
                        $form = $department?->forms()->with('fields')->first();
                        
                        if (!$form || !$form->fields->count() || empty($record->custom_fields)) {
                            return [
                                TextEntry::make('no_custom_fields')
                                    ->label('')
                                    ->default('No custom fields')
                                    ->columnSpanFull(),
                            ];
                        }
                        
                        $schema = [];
                        
                        foreach ($form->fields as $field) {
                            $value = $record->custom_fields[$field->name] ?? null;
                            
                            if ($value !== null) {
                                $schema[] = TextEntry::make("custom_fields.{$field->name}")
                                    ->label($field->label)
                                    ->formatStateUsing(function ($state) use ($field) {
                                        if ($field->type === 'checkbox' || $field->type === 'toggle') {
                                            return $state ? 'Yes' : 'No';
                                        }
                                        
                                        if ($field->type === 'select' || $field->type === 'radio') {
                                            $options = $field->options ?? [];
                                            return $options[$state] ?? $state;
                                        }
                                        
                                        if ($field->type === 'file') {
                                            return is_array($state) ? implode(', ', $state) : $state;
                                        }
                                        
                                        return $state;
                                    });
                            }
                        }
                        
                        return $schema ?: [
                            TextEntry::make('no_data')
                                ->label('')
                                ->default('No custom field data')
                                ->columnSpanFull(),
                        ];
                    })
                    ->columns(2)
                    ->visible(fn (Ticket $record) => 
                        !empty($record->custom_fields) || 
                        $record->department?->forms()->first()?->fields->count() > 0
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $permissions = (new static)->getUserPermissions();
        $user = Filament::auth()->user();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($permissions, $user) {
                if (!$user) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                if ($permissions['is_admin']) {
                    return;
                }

                if (!empty($permissions['departments'])) {
                    $canViewAllInDepartments = (new static)->canUserViewAllTickets();
                    $departmentIds = $permissions['departments'];
                    
                    $query->where(function (Builder $q) use ($user, $departmentIds, $canViewAllInDepartments) {
                        if ($canViewAllInDepartments) {
                            $q->orWhereIn('department_id', $departmentIds);
                        } else {
                            $q->orWhere(function (Builder $subQ) use ($user, $departmentIds) {
                                $subQ->whereIn('department_id', $departmentIds)
                                     ->where('assignee_id', $user->id);
                            });
                        }
                        $q->orWhere('user_id', $user->id);
                    });
                } else {
                    $query->where('user_id', $user->id);
                }
            })
            ->columns([
                TextColumn::make('ticket_uid')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('title')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('ticket_uid', 'like', "%{$search}%")
                            ->orWhereRaw("JSON_EXTRACT(custom_fields, '$.*') LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->limit(40)
                    ->tooltip(fn (Ticket $record): string => $record->title),
                
                TextColumn::make('department.name')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('requester.name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('assignee.name')
                    ->searchable()
                    ->sortable()
                    ->default('Unassigned')
                    ->formatStateUsing(fn ($state) => $state ?: 'Unassigned'),
                
                TextColumn::make('status.name')
                    ->formatStateUsing(fn ($record) => 
                        $record->status?->name ? 
                            "<span style='
                                display: inline-flex;
                                align-items: center;
                                background-color: {$record->status->color}10;
                                color: {$record->status->color};
                                padding: 0.3rem 0.8rem;
                                border-radius: 9999px;
                                font-size: 0.7rem;
                                font-weight: 600;
                                line-height: 1;
                                border: 1.5px solid {$record->status->color};
                                white-space: nowrap;
                            '>{$record->status->name}</span>" 
                        : ''
                    )
                    ->html(),
                
                TextColumn::make('priority')
                    ->badge(),
                
                TextColumn::make('last_activity_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->relationship('department', 'name')
                    ->preload(),
                
                SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->preload(),
                
                SelectFilter::make('priority')
                    ->options(TicketPriority::class)
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (Model $record) => 
                        $record instanceof Ticket && (
                        $permissions['is_admin'] || 
                        (in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false)))
                    )
                    ->form([
                         (config('creators-ticketing.ticket_assign_scope') === 'department_only') 
                           ? Select::make('assignee_id')
                                    ->label('Select Assignee')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search, Component $component) use ($userModel) {
                                        $departmentId = $component->getContainer()->getRecord()?->department_id;

                                        return $userModel::when(
                                            config('creators-ticketing.ticket_assign_scope') === 'department_only' && $departmentId !== null,
                                            fn ($query) => $query->whereExists(function ($subquery) use ($departmentId) {
                                                $subquery->select(\DB::raw(1))
                                                    ->from(config('creators-ticketing.table_prefix') . 'department_users')
                                                    ->whereColumn(config('creators-ticketing.table_prefix') . 'department_users.user_id', 'users.id')
                                                    ->where(config('creators-ticketing.table_prefix') . 'department_users.department_id', $departmentId);
                                            })
                                        )
                                        ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email]);
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => 
                                        $userModel::find($value)?->name . ' - ' . $userModel::find($value)?->email
                                    )
                                    ->options(function (Component $component) use ($userModel): array {
                                        if (config('creators-ticketing.ticket_assign_scope') === 'department_only') {
                                            $departmentId = $component->getContainer()->getRecord()?->department_id;
                                            if ($departmentId) {
                                                return Department::find($departmentId)?->agents->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email])->toArray() ?? [];
                                            }
                                        }
                                        return $userModel::limit(50)
                                            ->get()
                                            ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email])
                                            ->toArray();
                                    })
                                    ->default(fn (Model $record) => $record instanceof Ticket ? $record->assignee_id : null)
                                    ->preload(fn(Model $record) => $record instanceof Ticket && config('creators-ticketing.ticket_assign_scope') === 'department_only' && $record->department_id !== null)
                                    ->required()
                                    ->native(false)
                        
                        :   Select::make('assignee_id')
                                ->label('Select Assignee')
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) use ($userModel) {
                                    return $userModel::where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email]);
                                })
                            ->getOptionLabelUsing(fn ($value): ?string => 
                                $userModel::find($value)?->name . ' - ' . $userModel::find($value)?->email
                            )
                            ->default(fn (Model $record) => $record instanceof Ticket ? $record->assignee_id : null)
                            ->preload(false)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Model $record, array $data) use ($userModel) {
                        if (!$record instanceof Ticket) return;
                        $record->update(['assignee_id' => $data['assignee_id']]);

                        $record->activities()->create([
                            'user_id' => auth()->id(),
                            'description' => 'Ticket assigned',
                            'new_value' => $userModel::find($data['assignee_id'])?->name,
                        ]);
                        
                        Notification::make()->title('Ticket assigned')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => 
                           $permissions['is_admin'] || 
                           collect($permissions['permissions'])->pluck('can_delete_tickets')->contains(true)
                        ),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InternalNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}