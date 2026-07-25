<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\SpamFilters;

use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
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
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Filament\Schemas\Components\Utilities\Get;
use daacreators\CreatorsTicketing\Models\SpamFilter;
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Filament\Resources\SpamFilters\Pages;

class SpamFilterResource extends Resource
{
    use HasTicketPermissions, HasTicketingNavGroup;

    protected static ?string $model = SpamFilter::class;
    
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.spam_filter.title');
    }

    public static function getCluster(): ?string
    {
        return config('creators-ticketing.cluster') ?? static::$cluster;
    }

    public static function canViewAny(): bool
    {
        return static::userCan('can_manage_spam_filters');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getModelLabel(): string
    {
        return __('creators-ticketing::resources.spam_filter.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('creators-ticketing::resources.spam_filter.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('type')
                ->label(__('creators-ticketing::resources.spam_filter.type'))
                ->options([
                    'keyword' => __('creators-ticketing::resources.spam_filter.types.keyword'),
                    'email' => __('creators-ticketing::resources.spam_filter.types.email'),
                    'ip' => __('creators-ticketing::resources.spam_filter.types.ip'),
                    'pattern' => __('creators-ticketing::resources.spam_filter.types.pattern'),
                ])
                ->required()
                ->live(),
            
            Select::make('action')
                ->label(__('creators-ticketing::resources.spam_filter.action'))
                ->options([
                    'block' => __('creators-ticketing::resources.spam_filter.actions.block'),
                    'allow' => __('creators-ticketing::resources.spam_filter.actions.allow'),
                ])
                ->required()
                ->default('block'),
            
            TagsInput::make('values')
                ->label(__('creators-ticketing::resources.spam_filter.values'))
                ->required()
                ->placeholder(__('creators-ticketing::resources.spam_filter.values_placeholder'))
                ->helperText(fn (Get $get) => match ($get('type')) {
                    'keyword' => __('creators-ticketing::resources.spam_filter.helpers.keyword'),
                    'email' => __('creators-ticketing::resources.spam_filter.helpers.email'),
                    'ip' => __('creators-ticketing::resources.spam_filter.helpers.ip'),
                    'pattern' => __('creators-ticketing::resources.spam_filter.helpers.pattern'),
                    default => __('creators-ticketing::resources.spam_filter.helpers.default'),
                })
                ->columnSpanFull(),
            
            Toggle::make('case_sensitive')
                ->label(__('creators-ticketing::resources.spam_filter.case_sensitive'))
                ->default(false)
                ->visible(fn (Get $get) => in_array($get('type'), ['keyword', 'email'])),
            
            TextInput::make('priority')
                ->label(__('creators-ticketing::resources.spam_filter.priority'))
                ->numeric()
                ->default(0)
                ->helperText(__('creators-ticketing::resources.spam_filter.priority_helper')),
            
            Textarea::make('reason')
                ->label(__('creators-ticketing::resources.spam_filter.reason'))
                ->helperText(__('creators-ticketing::resources.spam_filter.reason_helper'))
                ->rows(3)
                ->columnSpanFull(),
            
            Toggle::make('is_active')
                ->label(__('creators-ticketing::resources.spam_filter.is_active'))
                ->default(true),
        ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('creators-ticketing::resources.spam_filter.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'keyword' => __('creators-ticketing::resources.spam_filter.types.keyword'),
                        'email' => __('creators-ticketing::resources.spam_filter.types.email'),
                        'ip' => __('creators-ticketing::resources.spam_filter.types.ip'),
                        'pattern' => __('creators-ticketing::resources.spam_filter.types.pattern'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'keyword' => 'warning',
                        'email' => 'danger',
                        'ip' => 'info',
                        'pattern' => 'success',
                        default => 'gray',
                    }),
                
                TextColumn::make('values')
                    ->label(__('creators-ticketing::resources.spam_filter.values'))
                    ->state(function ($record) {
                        if (!is_array($record->values)) return $record->values;
                        $count = count($record->values);
                        if ($count === 0) return '-';
                        if ($count === 1) return $record->values[0];
                        if ($count === 2) return $record->values[0] . ', ' . $record->values[1];
                        return $record->values[0] . ', ' . $record->values[1] . ' +' . ($count - 2) . ' more';
                    })
                    ->searchable(),
                
                TextColumn::make('action')
                    ->label(__('creators-ticketing::resources.spam_filter.action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'block' => __('creators-ticketing::resources.spam_filter.actions.block'),
                        'allow' => __('creators-ticketing::resources.spam_filter.actions.allow'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'block' => 'danger',
                        'allow' => 'success',
                        default => 'gray',
                    }),
                
                IconColumn::make('is_active')
                    ->label(__('creators-ticketing::resources.spam_filter.is_active'))
                    ->boolean(),
                
                TextColumn::make('priority')
                    ->label(__('creators-ticketing::resources.spam_filter.priority'))
                    ->sortable(),
                
                TextColumn::make('hits')
                    ->label(__('creators-ticketing::resources.spam_filter.hits'))
                    ->sortable()
                    ->default(0),
                
                TextColumn::make('last_triggered_at')
                    ->label(__('creators-ticketing::resources.spam_filter.last_triggered'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('creators-ticketing::resources.spam_filter.type'))
                    ->options([
                        'keyword' => __('creators-ticketing::resources.spam_filter.types.keyword'),
                        'email' => __('creators-ticketing::resources.spam_filter.types.email'),
                        'ip' => __('creators-ticketing::resources.spam_filter.types.ip'),
                        'pattern' => __('creators-ticketing::resources.spam_filter.types.pattern'),
                    ]),
                SelectFilter::make('action')
                    ->label(__('creators-ticketing::resources.spam_filter.action'))
                    ->options([
                        'block' => __('creators-ticketing::resources.spam_filter.actions.block'),
                        'allow' => __('creators-ticketing::resources.spam_filter.actions.allow'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpamFilters::route('/'),
            'create' => Pages\CreateSpamFilter::route('/create'),
            'edit' => Pages\EditSpamFilter::route('/{record}/edit'),
        ];
    }
}