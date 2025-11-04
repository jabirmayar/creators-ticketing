<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Resources\RelationManagers\RelationManager;

class PublicRepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'publicReplies';
    protected static ?string $title = 'Conversation';

    public function table(Table $table): Table
    {
        return $table
            ->heading(false)
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('user.name')
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-o-user-circle')
                            ->badge()
                            ->color(fn($record) => $record?->user_id === $record?->ticket?->user_id ? 'warning' : 'success')
                            ->formatStateUsing(fn($state, $record) => 
                                $record?->user_id === $record?->ticket?->user_id
                                    ? "{$state} (Requester)"
                                    : "{$state} (Agent)"
                            ),

                        TextColumn::make('created_at')
                            ->since()
                            ->color('gray')
                            ->grow(false),
                    ]),

                    TextColumn::make('content')
                        ->html()
                        ->extraAttributes(fn($record) => [
                            'class' => $record?->user_id === $record?->ticket?->user_id
                                ? 'bg-gray-700 text-gray-100 px-4 py-2 rounded-2xl rounded-tl-sm w-fit ml-auto'
                                : 'bg-emerald-800 text-white px-4 py-2 rounded-2xl rounded-tr-sm w-fit',
                        ]),
                ])->space(2),
            ])
            ->defaultSort('created_at', 'asc')
            ->paginated(false)
            ->poll('5s')
            ->striped(false)
            ->actions([])
            ->bulkActions([])
            ->headerActions([])
            ->contentGrid(['md' => 1])
            ->extraAttributes(['class' => 'space-y-3 p-4 bg-gray-900 rounded-2xl']);
    }
}