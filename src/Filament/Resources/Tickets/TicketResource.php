<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets;

use App\Models\User;
use BackedEnum;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\RelationManagers\InternalNotesRelationManager;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\Form;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TicketResource extends Resource
{
    use HasTicketingNavGroup, HasTicketPermissions;

    protected static ?string $model = Ticket::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.ticket.title');
    }

    public static function getModelLabel(): string
    {
        return __('creators-ticketing::resources.ticket.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('creators-ticketing::resources.ticket.plural_label');
    }

    public static function canViewAny(): bool
    {
        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }
        $user = Filament::auth()->user();
        if (! $user) {
            return false;
        }

        return ! empty($permissions['departments']) || $user->tickets()->exists();
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
        if (! $record instanceof Ticket) {
            return false;
        }

        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }

        $user = Filament::auth()->user();
        if (! $user) {
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
        if (! $record instanceof Ticket) {
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
                Section::make(__('creators-ticketing::resources.ticket.details'))
                    ->schema([
                        Select::make('department_id')
                            ->label(__('creators-ticketing::resources.ticket.department'))
                            ->relationship('department', 'name', function ($query) use ($permissions) {
                                $query->where('is_active', true);

                                if (! $permissions['is_admin'] && ! empty($permissions['departments'])) {
                                    $departmentsWithCreatePermission = collect($permissions['permissions'])
                                        ->filter(fn ($perm) => $perm['can_create_tickets'] ?? false)
                                        ->keys()
                                        ->toArray();

                                    if (! empty($departmentsWithCreatePermission)) {
                                        $query->whereIn('id', $departmentsWithCreatePermission);
                                    }
                                }
                            })
                            ->required()
                            ->visible(fn (?Model $record) => $record === null)
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('form_id', null);
                                $set('custom_fields', []);
                            }),

                        Select::make('form_id')
                            ->label(__('creators-ticketing::resources.ticket.form_label') ?? 'Ticket Form')
                            ->options(function (Get $get) {
                                $departmentId = $get('department_id');
                                if (! $departmentId) {
                                    return [];
                                }

                                $department = Department::find($departmentId);
                                if (! $department) {
                                    return [];
                                }

                                $table = (new Form)->getTable();

                                return $department->forms()
                                    ->where($table.'.is_active', true)
                                    ->pluck($table.'.name', $table.'.id')
                                    ->toArray();
                            })
                            ->required(fn (Get $get) => ! empty(Department::find($get('department_id'))?->forms()->exists()))
                            ->visible(fn (Get $get, ?Model $record) => ($record === null || $record->form_id === null) &&
                                $get('department_id') !== null &&
                                Department::find($get('department_id'))?->forms()->count() > 1
                            )
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('custom_fields', []);
                            })
                            ->default(function (Get $get) {
                                $departmentId = $get('department_id');
                                $department = Department::find($departmentId);
                                if (! $department) {
                                    return null;
                                }

                                $table = (new Form)->getTable();

                                return $department->forms()
                                    ->where($table.'.is_active', true)
                                    ->first()
                                    ?->id;
                            }),

                        Group::make()
                            ->schema(fn (Get $get, ?Model $record): array => static::getDynamicFormFields($record, $get('department_id'), $get('form_id'), $permissions, $user))
                            ->visible(fn (?Model $record, Get $get) => $record !== null || $get('department_id') !== null
                            )
                            ->columnSpanFull(),
                    ]),
            ])->columnSpan(2),

            Group::make()->schema([
                Section::make(__('creators-ticketing::resources.ticket.properties'))
                    ->schema([
                        Select::make('user_id')
                            ->label(__('creators-ticketing::resources.ticket.requester'))
                            ->relationship('requester', 'name')
                            ->searchable()
                            ->required()
                            ->default(auth()->id())
                            ->visible(fn (?Model $record) => $permissions['is_admin'] ||
                                ($record === null && ! empty(collect($permissions['permissions'])->filter(fn ($p) => $p['can_assign_tickets'] ?? false))) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false))
                            )
                            ->disabled(fn (?Model $record) => $record instanceof Ticket && ! $permissions['is_admin']),

                        Select::make('assignee_id')
                            ->label(__('creators-ticketing::resources.ticket.assignee'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($user) => [$user->id => $user->name.' - '.$user->email])
                            )
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name.' - '.User::find($value)?->email
                            )
                            ->preload(false)
                            ->native(false)
                            ->visible(fn (?Model $record, Get $get) => $permissions['is_admin'] ||
                                ($record === null && $get('department_id') && ($permissions['permissions'][$get('department_id')]['can_assign_tickets'] ?? false)) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false))
                            )
                            ->disabled(fn (?Model $record, Get $get) => $record instanceof Ticket && ! $permissions['is_admin'] &&
                                ! ($record->department && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false))
                            ),

                        Select::make('department_id')
                            ->relationship('department', 'name')
                            ->required()
                            ->visible(fn (?Model $record) => $record !== null && $permissions['is_admin']
                            )
                            ->disabled(fn (?Model $record) => $record instanceof Ticket && ! $permissions['is_admin']),

                        Select::make('ticket_status_id')
                            ->label(__('creators-ticketing::resources.ticket.status'))
                            ->relationship('status', 'name')
                            ->visible(fn (?Model $record, Get $get) => $permissions['is_admin'] ||
                                ($record === null && $get('department_id') && ($permissions['permissions'][$get('department_id')]['can_change_status'] ?? false)) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_status'] ?? false))
                            )
                            ->disabled(fn (?Model $record, Get $get) => $record instanceof Ticket && ! $permissions['is_admin'] &&
                                ! ($record->department && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_status'] ?? false))
                            ),

                        Select::make('priority')
                            ->label(__('creators-ticketing::resources.ticket.priority'))
                            ->options(TicketPriority::class)
                            ->enum(TicketPriority::class)
                            ->required()
                            ->default(TicketPriority::LOW)
                            ->visible(fn (?Model $record, Get $get) => $permissions['is_admin'] ||
                                ($record === null && $get('department_id') && ($permissions['permissions'][$get('department_id')]['can_change_priority'] ?? false)) ||
                                ($record instanceof Ticket && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_priority'] ?? false))
                            )
                            ->disabled(fn (?Model $record, Get $get) => $record instanceof Ticket && ! $permissions['is_admin'] &&
                                ! ($record->department && in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_change_priority'] ?? false))
                            ),
                    ]),
            ])->columnSpan(1),
        ])->columns(3);
    }

    protected static function getDynamicFormFields(?Model $record, $departmentId, $formId, $permissions, $user): array
    {
        if (! $departmentId) {
            $departmentId = $record?->department_id;
        }

        if (! $departmentId) {
            return [];
        }

        $department = Department::find($departmentId);
        if (! $department) {
            return [];
        }

        $form = null;

        if ($formId) {
            $form = Form::with('fields')->find($formId);
        } elseif ($record instanceof Ticket) {
            if (isset($record->form_id)) {
                $form = Form::with('fields')->find($record->form_id);
            }

            if (! $form) {
                $form = $department->forms()->with('fields')->first();
            }
        } elseif ($department->forms()->count() === 1) {
            $form = $department->forms()->with('fields')->first();
        }

        if (! $form || ! $form->fields->count()) {
            if ($department->forms()->count() > 0 && ! $formId) {
                return [
                    TextEntry::make('select_form')
                        ->label('')
                        ->state(__('creators-ticketing::resources.ticket.select_form_message') ?? 'Please select a form.')
                        ->columnSpanFull(),
                ];
            }

            return [
                TextEntry::make('no_form')
                    ->label('')
                    ->state(__('creators-ticketing::resources.ticket.no_form'))
                    ->columnSpanFull(),
            ];
        }

        $fields = [];
        $isDisabled = $record instanceof Ticket && ! $permissions['is_admin'] &&
                      $record->user_id !== $user->id &&
                      ! in_array($record->department_id, $permissions['departments']);

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
                InfoSection::make(__('creators-ticketing::resources.ticket.information'))
                    ->schema([
                        TextEntry::make('ticket_uid')
                            ->label(__('creators-ticketing::resources.ticket.ticket_id')),

                        TextEntry::make('title'),

                        TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),

                        TextEntry::make('requester.name')
                            ->label(__('creators-ticketing::resources.ticket.requester')),

                        TextEntry::make('assignee.name')
                            ->label(__('creators-ticketing::resources.ticket.assignee'))
                            ->default(__('creators-ticketing::resources.ticket.unassigned')),

                        TextEntry::make('department.name')
                            ->label(__('creators-ticketing::resources.ticket.department')),

                        TextEntry::make('status.name')
                            ->label(__('creators-ticketing::resources.ticket.status'))
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

                InfoSection::make(__('creators-ticketing::resources.ticket.custom_fields'))
                    ->schema(function (Ticket $record) {
                        $department = $record->department;

                        $forms = collect();

                        if (isset($record->form_id)) {
                            $forms = Form::where('id', $record->form_id)->with('fields')->get();
                        }

                        if ($forms->isEmpty() && $department) {
                            $forms = $department->forms()->with('fields')->get();
                        }

                        if ($forms->isEmpty() || empty($record->custom_fields)) {
                            return [
                                TextEntry::make('no_custom_fields')
                                    ->label('')
                                    ->default(__('creators-ticketing::resources.ticket.no_custom_fields'))
                                    ->columnSpanFull(),
                            ];
                        }

                        $schema = [];
                        $processedFields = [];

                        foreach ($forms as $form) {
                            foreach ($form->fields as $field) {
                                if (in_array($field->name, $processedFields)) {
                                    continue;
                                }

                                $value = $record->custom_fields[$field->name] ?? null;

                                if ($value !== null) {
                                    $processedFields[] = $field->name;
                                    $schema[] = TextEntry::make("custom_fields.{$field->name}")
                                        ->label($field->label)
                                        ->formatStateUsing(function ($state) use ($field) {
                                            if ($field->type === 'checkbox' || $field->type === 'toggle') {
                                                return $state ? __('creators-ticketing::resources.ticket.yes') : __('creators-ticketing::resources.ticket.no');
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
                        }

                        return $schema ?: [
                            TextEntry::make('no_data')
                                ->label('')
                                ->default(__('creators-ticketing::resources.ticket.no_custom_data'))
                                ->columnSpanFull(),
                        ];
                    })
                    ->columns(2)
                    ->visible(fn (Ticket $record) => ! empty($record->custom_fields)
                    ),
            ]);
    }

    public static function table(Table $table): Table
    {
        $userModel = config('creators-ticketing.user_model');
        $permissions = (new static)->getUserPermissions();
        $user = Filament::auth()->user();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($permissions, $user) {
                if (! $user) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                if ($permissions['is_admin']) {
                    return;
                }

                if (! empty($permissions['departments'])) {
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
            ->recordClasses(fn (Model $record) => match (true) {
                method_exists($record, 'isUnseen') && $record->isUnseen() => 'font-bold bg-primary-50/50 dark:bg-primary-900/20',
                default => null,
            })
            ->columns([
                TextColumn::make('unread_indicator')
                    ->badge()
                    ->label('')
                    ->color('info')
                    ->size('sm')
                    ->state(fn (Model $record) => method_exists($record, 'isUnseen') && $record->isUnseen()
                                            ? __('creators-ticketing::resources.ticket.new')
                                            : null
                    )
                    ->extraAttributes(['class' => 'text-[11px]'])
                    ->tooltip(fn ($state) => $state ? __('creators-ticketing::resources.ticket.new_tool_tip') : null),

                TextColumn::make('ticket_uid')
                    ->label(__('creators-ticketing::resources.ticket.id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('creators-ticketing::resources.ticket.title_field'))
                    ->weight(fn (Model $record) => (method_exists($record, 'isUnseen') && $record->isUnseen()) ? 'bold' : 'medium')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('ticket_uid', 'like', "%{$search}%")
                                ->orWhereRaw("JSON_EXTRACT(custom_fields, '$.*') LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->limit(40)
                    ->tooltip(fn (Ticket $record): string => $record->title),

                TextColumn::make('department.name')
                    ->label(__('creators-ticketing::resources.ticket.department'))
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label(__('creators-ticketing::resources.ticket.requester'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignee.name')
                    ->label(__('creators-ticketing::resources.ticket.assignee'))
                    ->searchable()
                    ->sortable()
                    ->default(__('creators-ticketing::resources.ticket.unassigned'))
                    ->formatStateUsing(fn ($state) => $state ?: __('creators-ticketing::resources.ticket.unassigned')),

                TextColumn::make('status.name')
                    ->label(__('creators-ticketing::resources.ticket.status'))
                    ->formatStateUsing(fn ($record) => $record->status?->name ?
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
                    ->label(__('creators-ticketing::resources.ticket.priority'))
                    ->badge(),

                TextColumn::make('last_activity_at')
                    ->label(__('creators-ticketing::resources.ticket.last_activity'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->label(__('creators-ticketing::resources.ticket.department'))
                    ->relationship('department', 'name')
                    ->preload(),

                SelectFilter::make('status')
                    ->label(__('creators-ticketing::resources.ticket.status'))
                    ->relationship('status', 'name')
                    ->preload(),

                SelectFilter::make('priority')
                    ->label(__('creators-ticketing::resources.ticket.priority'))
                    ->options(TicketPriority::class)
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('assign')
                    ->label(__('creators-ticketing::resources.ticket.actions.assign'))
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (Model $record) => $record instanceof Ticket && (
                        $permissions['is_admin'] ||
                        (in_array($record->department_id, $permissions['departments']) && ($permissions['permissions'][$record->department_id]['can_assign_tickets'] ?? false)))
                    )
                    ->schema([
                        (config('creators-ticketing.ticket_assign_scope') === 'department_only')
                          ? Select::make('assignee_id')
                              ->label(__('creators-ticketing::resources.ticket.actions.select_assignee'))
                              ->searchable()
                              ->getSearchResultsUsing(function (string $search, Component $component) use ($userModel) {
                                  $departmentId = $component->getContainer()->getRecord()?->department_id;
                                  $userInstance = new $userModel;
                                  $userKey = $userInstance->getKeyName();
                                  $pivotUserColumn = "user_{$userKey}";

                                  return $userModel::when(
                                      config('creators-ticketing.ticket_assign_scope') === 'department_only' && $departmentId !== null,
                                      fn ($query) => $query->whereExists(function ($subquery) use ($departmentId, $pivotUserColumn, $userKey) {
                                          $subquery->select(DB::raw(1))
                                              ->from(config('creators-ticketing.table_prefix').'department_users')
                                              ->whereColumn(
                                                  config('creators-ticketing.table_prefix')."department_users.{$pivotUserColumn}",
                                                  "users.{$userKey}"
                                              )
                                              ->where(config('creators-ticketing.table_prefix').'department_users.department_id', $departmentId);
                                      })
                                  )
                                      ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                      ->limit(50)
                                      ->get()
                                      ->mapWithKeys(fn ($user) => [$user->id => $user->name.' - '.$user->email]);
                              })
                              ->getOptionLabelUsing(fn ($value): ?string => $userModel::find($value)?->name.' - '.$userModel::find($value)?->email
                              )
                              ->options(function (Component $component) use ($userModel): array {
                                  if (config('creators-ticketing.ticket_assign_scope') === 'department_only') {
                                      $departmentId = $component->getContainer()->getRecord()?->department_id;
                                      if ($departmentId) {
                                          return Department::find($departmentId)?->agents->mapWithKeys(fn ($user) => [$user->id => $user->name.' - '.$user->email])->toArray() ?? [];
                                      }
                                  }

                                  return $userModel::limit(50)
                                      ->get()
                                      ->mapWithKeys(fn ($user) => [$user->id => $user->name.' - '.$user->email])
                                      ->toArray();
                              })
                              ->default(fn (Model $record) => $record instanceof Ticket ? $record->assignee_id : null)
                              ->preload(fn (Model $record) => $record instanceof Ticket && config('creators-ticketing.ticket_assign_scope') === 'department_only' && $record->department_id !== null)
                              ->native(false)

                        : Select::make('assignee_id')
                            ->label(__('creators-ticketing::resources.ticket.actions.select_assignee'))
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) use ($userModel) {
                                return $userModel::where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => $user->name.' - '.$user->email]);
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => $userModel::find($value)?->name.' - '.$userModel::find($value)?->email
                            )
                            ->default(fn (Model $record) => $record instanceof Ticket ? $record->assignee_id : null)
                            ->preload(false)
                            ->native(false),
                    ])
                    ->action(function (Model $record, array $data) use ($userModel) {
                        if (! $record instanceof Ticket) {
                            return;
                        }
                        $record->update(['assignee_id' => $data['assignee_id']]);

                        $record->activities()->create([
                            'user_id' => auth()->id(),
                            'description' => 'Ticket assigned',
                            'new_value' => $userModel::find($data['assignee_id'])?->name,
                        ]);

                        Notification::make()
                            ->title(__('creators-ticketing::resources.ticket.notifications.assigned'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => $permissions['is_admin'] ||
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
