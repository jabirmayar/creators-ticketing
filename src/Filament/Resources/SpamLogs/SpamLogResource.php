<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\SpamLogs;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use daacreators\CreatorsTicketing\Models\SpamLog;
use daacreators\CreatorsTicketing\Support\UserNameResolver;
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Filament\Resources\SpamLogs\Pages;

class SpamLogResource extends Resource
{
    use HasTicketPermissions, HasTicketingNavGroup;

    protected static ?string $model = SpamLog::class;
    
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.spam_log.title');
    }

    public static function getCluster(): ?string
    {
        return config('creators-ticketing.cluster') ?? static::$cluster;
    }

    public static function canViewAny(): bool
    {
        return static::userCan('can_view_spam_logs');
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
        return __('creators-ticketing::resources.spam_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('creators-ticketing::resources.spam_log.plural_label');
    }

    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('creators-ticketing::resources.spam_log.date'))
                    ->dateTime()
                    ->sortable(),
                
                TextColumn::make('user.name')
                    ->label(__('creators-ticketing::resources.spam_log.user'))
                    ->formatStateUsing(fn ($record) => $record->user ? UserNameResolver::resolve($record->user) : __('creators-ticketing::resources.spam_log.guest'))
                    ->searchable(),
                
                TextColumn::make('email')
                    ->label(__('creators-ticketing::resources.spam_log.email'))
                    ->searchable(),
                
                TextColumn::make('ip_address')
                    ->label(__('creators-ticketing::resources.spam_log.ip'))
                    ->searchable(),
                
                TextColumn::make('filter_type')
                    ->label(__('creators-ticketing::resources.spam_log.filter_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'keyword' => __('creators-ticketing::resources.spam_filter.types.keyword'),
                        'email' => __('creators-ticketing::resources.spam_filter.types.email'),
                        'ip' => __('creators-ticketing::resources.spam_filter.types.ip'),
                        'pattern' => __('creators-ticketing::resources.spam_filter.types.pattern'),
                        'rate_limit' => __('creators-ticketing::resources.spam_log.rate_limit'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'keyword' => 'warning',
                        'email' => 'danger',
                        'ip' => 'info',
                        'pattern' => 'success',
                        'rate_limit' => 'gray',
                        default => 'gray',
                    }),
                
                TextColumn::make('action_taken')
                    ->label(__('creators-ticketing::resources.spam_log.action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'blocked' => __('creators-ticketing::resources.spam_log.actions.blocked'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'blocked' => 'danger',
                        default => 'gray',
                    }),
                
                TextColumn::make('matched_value')
                    ->label(__('creators-ticketing::resources.spam_log.matched_value'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('filter_rule')
                    ->label(__('creators-ticketing::resources.spam_log.filter_rule'))
                    ->state(function ($record) {
                        if (!$record->spamFilter || !is_array($record->spamFilter->values)) {
                            return '-';
                        }
                        $values = $record->spamFilter->values;
                        $count = count($values);
                        if ($count === 0) return '-';
                        if ($count === 1) return $values[0];
                        return $values[0] . ' +' . ($count - 1) . ' more';
                    })
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('filter_type')
                    ->label(__('creators-ticketing::resources.spam_log.filter_type'))
                    ->options([
                        'keyword' => __('creators-ticketing::resources.spam_filter.types.keyword'),
                        'email' => __('creators-ticketing::resources.spam_filter.types.email'),
                        'ip' => __('creators-ticketing::resources.spam_filter.types.ip'),
                        'pattern' => __('creators-ticketing::resources.spam_filter.types.pattern'),
                        'rate_limit' => __('creators-ticketing::resources.spam_log.rate_limit'),
                    ]),
                SelectFilter::make('action_taken')
                    ->label(__('creators-ticketing::resources.spam_log.action'))
                    ->options([
                        'blocked' => __('creators-ticketing::resources.spam_log.actions.blocked'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('creators-ticketing::resources.spam_log.details'))
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('creators-ticketing::resources.spam_log.date'))
                            ->dateTime(),
                        
                        TextEntry::make('user.name')
                            ->label(__('creators-ticketing::resources.spam_log.user'))
                            ->formatStateUsing(fn ($record) => $record->user ? UserNameResolver::resolve($record->user) : __('creators-ticketing::resources.spam_log.guest')),
                        
                        TextEntry::make('email')
                            ->label(__('creators-ticketing::resources.spam_log.email')),
                        
                        TextEntry::make('ip_address')
                            ->label(__('creators-ticketing::resources.spam_log.ip')),
                        
                        TextEntry::make('filter_type')
                            ->label(__('creators-ticketing::resources.spam_log.filter_type'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'keyword' => __('creators-ticketing::resources.spam_filter.types.keyword'),
                                'email' => __('creators-ticketing::resources.spam_filter.types.email'),
                                'ip' => __('creators-ticketing::resources.spam_filter.types.ip'),
                                'pattern' => __('creators-ticketing::resources.spam_filter.types.pattern'),
                                'rate_limit' => __('creators-ticketing::resources.spam_log.rate_limit'),
                                default => $state,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'keyword' => 'warning',
                                'email' => 'danger',
                                'ip' => 'info',
                                'pattern' => 'success',
                                'rate_limit' => 'gray',
                                default => 'gray',
                            }),
                        
                        TextEntry::make('action_taken')
                            ->label(__('creators-ticketing::resources.spam_log.action'))
                            ->badge()
                            ->color('danger'),
                        
                        TextEntry::make('matched_value')
                            ->label(__('creators-ticketing::resources.spam_log.matched_value'))
                            ->placeholder(__('creators-ticketing::resources.spam_log.no_match')),
                        
                        TextEntry::make('spamFilter.values')
                            ->label(__('creators-ticketing::resources.spam_log.filter_rule'))
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                            ->placeholder(__('creators-ticketing::resources.spam_log.no_filter')),

                        KeyValueEntry::make('ticket_data')
                            ->label(__('creators-ticketing::resources.spam_log.ticket_data'))
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpamLogs::route('/'),
            'view' => Pages\ViewSpamLog::route('/{record}'),
        ];
    }
}