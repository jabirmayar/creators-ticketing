<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Departments;

use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use daacreators\CreatorsTicketing\Models\Form;
use Filament\Schemas\Components\Utilities\Set;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Traits\HasNavigationVisibility;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\Pages;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\RelationManagers\AgentsRelationManager;

class DepartmentResource extends Resource
{
    use HasNavigationVisibility;
    
    protected static ?string $model = Department::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(Department::class, 'slug', ignoreRecord: true),

            Select::make('visibility')
                ->required()
                ->options([
                    'public' => 'Public',
                    'internal' => 'Internal',
                ])
                ->default('public')
                ->helperText('Public departments are visible to all users, internal departments are for staff only'),

            Select::make('form_id')
                ->label('Ticket Form')
                ->options(Form::where('is_active', true)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->helperText('Select the form to use for tickets in this department')
                ->dehydrated(false),

            Textarea::make('description')
                ->rows(3)
                ->maxLength(65535)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug'),

                TextColumn::make('form_name')
                    ->label('Form')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (Department $record) {
                        return $record->forms()->first()?->name ?? 'None';
                    }),

                TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'internal' => 'warning',
                    }),

                IconColumn::make('is_active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'internal' => 'Internal',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AgentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}