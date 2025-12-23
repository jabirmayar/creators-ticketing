<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Resources\RelationManagers\RelationManager;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;

class AutomationLogsRelationManager extends RelationManager
{
    use HasTicketPermissions;

    protected static string $relationship = 'logs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('creators-ticketing::resources.logs.title');
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return static::userCan('can_view_automation_logs');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                IconColumn::make('success')
                    ->label(__('creators-ticketing::resources.logs.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('ticket.title')
                    ->label(__('creators-ticketing::resources.logs.ticket'))
                    ->searchable()
                    ->description(fn ($record) => $record->ticket ? 'ID: #' . $record->ticket->ticket_uid : 'Deleted Ticket')
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('trigger_event')
                    ->label(__('creators-ticketing::resources.logs.trigger'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'ticket_created' => 'success',
                        'ticket_updated', 'status_changed' => 'info',
                        'priority_changed' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label(__('creators-ticketing::resources.logs.executed_at'))
                    ->dateTime('M d, Y H:i:s')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans())
                    ->color('gray'),
            ])
            ->filters([
                TernaryFilter::make('success')
                    ->label(__('creators-ticketing::resources.logs.status'))
                    ->trueLabel(__('creators-ticketing::resources.logs.success'))
                    ->falseLabel(__('creators-ticketing::resources.logs.failed')),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make()
                    ->label(__('creators-ticketing::resources.logs.view_details'))
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->mutateRecordDataUsing(function (array $data): array {
                        $formatter = fn ($items) => collect($items ?? [])
                            ->mapWithKeys(fn ($v, $k) => [
                                ucwords(str_replace('_', ' ', $k)) => is_array($v) 
                                    ? (empty($v) ? 'Any/None' : implode(', ', $v)) 
                                    : ($v ?? 'N/A')
                            ])
                            ->toArray();

                        $data['conditions_formatted'] = $formatter($data['conditions_met'] ?? []);
                        $data['actions_formatted'] = $formatter($data['actions_performed'] ?? []);
                        
                        return $data;
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('trigger_event')
                                ->label(__('creators-ticketing::resources.logs.trigger'))
                                ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                                ->disabled(),

                            TextInput::make('created_at')
                                ->label(__('creators-ticketing::resources.logs.executed_at'))
                                ->disabled(),

                            TextInput::make('success')
                                ->label(__('creators-ticketing::resources.logs.status'))
                                ->formatStateUsing(fn ($state) => $state ? __('creators-ticketing::resources.logs.success') : __('creators-ticketing::resources.logs.failed'))
                                ->extraInputAttributes(fn ($state) => [
                                    'class' => $state ? 'text-success-600 font-bold' : 'text-danger-600 font-bold',
                                ])
                                ->disabled(),
                        ]),
                    
                    TextInput::make('ticket.title')
                        ->label(__('creators-ticketing::resources.logs.ticket'))
                        ->prefix(fn ($record) => $record->ticket ? '#' . $record->ticket->ticket_uid : '#')
                        ->disabled()
                        ->columnSpanFull(),

                    Textarea::make('error_message')
                        ->label(__('creators-ticketing::resources.logs.error'))
                        ->visible(fn ($record) => !empty($record->error_message))
                        ->rows(3)
                        ->extraAttributes(['class' => 'bg-red-50 text-red-700 border-red-200'])
                        ->columnSpanFull()
                        ->disabled(),
                ]),

            Section::make(__('creators-ticketing::resources.logs.conditions'))
                ->schema([
                    KeyValue::make('conditions_formatted')
                        ->hiddenLabel()
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->compact(),

            Section::make(__('creators-ticketing::resources.logs.actions'))
                ->schema([
                    KeyValue::make('actions_formatted')
                        ->hiddenLabel()
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->compact(),
        ]);
    }
}