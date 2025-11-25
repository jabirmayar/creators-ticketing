<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses;

use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ColorColumn;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Utilities\Set;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Traits\HasNavigationVisibility;
use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\Pages;

class TicketStatusResource extends Resource
{
    use HasNavigationVisibility, HasTicketingNavGroup;
    
    protected static ?string $model = TicketStatus::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.ticket_status.title');
    }

    public static function getModelLabel(): string
    {
        return __('creators-ticketing::resources.ticket_status.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('creators-ticketing::resources.ticket_status.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label(__('creators-ticketing::resources.ticket_status.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->label(__('creators-ticketing::resources.ticket_status.slug'))
                ->required()
                ->maxLength(255)
                ->unique(TicketStatus::class, 'slug', ignoreRecord: true),

            ColorPicker::make('color')
                ->label(__('creators-ticketing::resources.ticket_status.color'))
                ->required(),

            Toggle::make('is_default_for_new')
                ->label(__('creators-ticketing::resources.ticket_status.is_default'))
                ->helperText(__('creators-ticketing::resources.ticket_status.is_default_helper')),

            Toggle::make('is_closing_status')
                ->label(__('creators-ticketing::resources.ticket_status.is_closing'))
                ->helperText(__('creators-ticketing::resources.ticket_status.is_closing_helper')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('creators-ticketing::resources.ticket_status.name'))
                    ->searchable(),

                ColorColumn::make('color')
                    ->label(__('creators-ticketing::resources.ticket_status.color')),

                IconColumn::make('is_default_for_new')
                    ->boolean()
                    ->label(__('creators-ticketing::resources.ticket_status.columns.default')),

                IconColumn::make('is_closing_status')
                    ->boolean()
                    ->label(__('creators-ticketing::resources.ticket_status.columns.is_closing')),

                TextColumn::make('created_at')
                    ->label(__('creators-ticketing::resources.ticket_status.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderable('order_column')
            ->filters([
                //
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketStatuses::route('/'),
            'create' => Pages\CreateTicketStatus::route('/create'),
            'edit' => Pages\EditTicketStatus::route('/{record}/edit'),
        ];
    }
}
