<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Split;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use daacreators\CreatorsTicketing\Support\UserNameResolver;

class InternalNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'internalNotes';
    
    #[On('internal-note-created')]
    public function refresh(): void
    {
    }
    
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('creators-ticketing::resources.internal_note.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('creators-ticketing::resources.internal_note.heading'))
            ->description(__('creators-ticketing::resources.internal_note.description'))
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('user')
                            ->icon('heroicon-o-shield-check')
                            ->badge()
                            ->color('warning')
                            ->weight(FontWeight::Bold)
                            ->formatStateUsing(fn($record) => UserNameResolver::resolve($record->user) . " • " . __('creators-ticketing::resources.internal_note.agent_note')),

                        TextColumn::make('created_at')
                            ->since()
                            ->color('gray')
                            ->weight(FontWeight::Medium)
                            ->size('sm')
                            ->alignRight(),
                    ])->from('md'),

                    TextColumn::make('content')
                        ->html()
                        ->extraAttributes([
                            'class' => '
                                rounded-2xl
                                bg-amber-100 dark:bg-amber-900/40
                                text-amber-800 dark:text-amber-100
                                px-4 py-3 mt-2 shadow-sm
                                ring-1 ring-amber-200/40 dark:ring-amber-800/40
                                prose-sm max-w-none
                            ',
                        ]),
                ])->space(3),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->poll('10s')
            ->striped(false)
            ->actions([])
            ->bulkActions([])
            ->headerActions([])
            ->contentGrid(['md' => 1])
            ->emptyStateHeading(__('creators-ticketing::resources.internal_note.empty_heading'))
            ->emptyStateDescription(__('creators-ticketing::resources.internal_note.empty_desc'))
            ->extraAttributes([
                'class' => '
                    divide-y divide-gray-200/10 dark:divide-gray-800/30
                    bg-gray-50 dark:bg-gray-900/50
                    rounded-2xl p-6 space-y-5
                ',
            ]);
    }
}
