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
use daacreators\CreatorsTicketing\Traits\HasNavigationVisibility;
use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\Pages;

class TicketStatusResource extends Resource
{
    use HasNavigationVisibility;
    
    protected static ?string $model = TicketStatus::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

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
                ->unique(TicketStatus::class, 'slug', ignoreRecord: true),

            ColorPicker::make('color')
                ->required(),

            Toggle::make('is_default_for_new')
                ->helperText('This status will be automatically assigned to all new tickets.'),

            Toggle::make('is_closing_status')
                ->helperText('Tickets with this status are considered "closed" in reports and filters.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                ColorColumn::make('color'),

                IconColumn::make('is_default_for_new')
                    ->boolean()
                    ->label('Default'),

                IconColumn::make('is_closing_status')
                    ->boolean()
                    ->label('Is Closing'),

                TextColumn::make('created_at')
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
