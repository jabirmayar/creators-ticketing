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
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Traits\HasNavigationVisibility;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\Pages;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\RelationManagers\AgentsRelationManager;

class DepartmentResource extends Resource
{
    use HasNavigationVisibility, HasTicketingNavGroup;
    
    protected static ?string $model = Department::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.department.title');
    }

    public static function getModelLabel(): string
    {
        return __('creators-ticketing::resources.department.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('creators-ticketing::resources.department.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label(__('creators-ticketing::resources.department.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->label(__('creators-ticketing::resources.department.slug'))
                ->required()
                ->maxLength(255)
                ->unique(Department::class, 'slug', ignoreRecord: true),

            Select::make('visibility')
                ->label(__('creators-ticketing::resources.department.visibility'))
                ->required()
                ->options([
                    'public' => __('creators-ticketing::resources.department.visibility_options.public'),
                    'internal' => __('creators-ticketing::resources.department.visibility_options.internal'),
                ])
                ->default('public')
                ->helperText(__('creators-ticketing::resources.department.visibility_helper')),

            Select::make('form_id')
                ->label(__('creators-ticketing::resources.department.form'))
                ->options(Form::where('is_active', true)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->helperText(__('creators-ticketing::resources.department.form_helper'))
                ->dehydrated(false),

            Textarea::make('description')
                ->label(__('creators-ticketing::resources.department.description'))
                ->rows(3)
                ->maxLength(65535)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label(__('creators-ticketing::resources.department.is_active'))
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('creators-ticketing::resources.department.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('creators-ticketing::resources.department.slug')),

                TextColumn::make('form_name')
                    ->label(__('creators-ticketing::resources.department.form'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function (Department $record) {
                        return $record->forms()->first()?->name ?? __('creators-ticketing::resources.department.form_none');
                    }),

                TextColumn::make('visibility')
                    ->label(__('creators-ticketing::resources.department.visibility'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'public' => __('creators-ticketing::resources.department.visibility_options.public'),
                        'internal' => __('creators-ticketing::resources.department.visibility_options.internal'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'internal' => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_active')
                    ->label(__('creators-ticketing::resources.department.is_active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('creators-ticketing::resources.department.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->label(__('creators-ticketing::resources.department.visibility'))
                    ->options([
                        'public' => __('creators-ticketing::resources.department.visibility_options.public'),
                        'internal' => __('creators-ticketing::resources.department.visibility_options.internal'),
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