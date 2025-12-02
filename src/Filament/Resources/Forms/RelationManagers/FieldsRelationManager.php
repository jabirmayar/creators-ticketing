<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Forms\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $recordTitleAttribute = 'label';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('creators-ticketing::resources.form.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label(__('creators-ticketing::resources.field.label'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->label(__('creators-ticketing::resources.field.name')),

                TextColumn::make('type')
                    ->label(__('creators-ticketing::resources.field.type'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('is_required')
                    ->label(__('creators-ticketing::resources.field.is_required'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state
                        ? __('creators-ticketing::resources.field.required')
                        : __('creators-ticketing::resources.field.optional')
                    )
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('order')
                    ->label(__('creators-ticketing::resources.field.order'))
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->headerActions([
                CreateAction::make()
                    ->form($this->getFieldForm()),
            ])
            ->actions([
                EditAction::make()
                    ->form($this->getFieldForm()),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('order');
    }

    protected function getFieldForm(): array
    {
        return [
            TextInput::make('name')
                ->label(__('creators-ticketing::resources.field.name'))
                ->required()
                ->maxLength(255)
                ->helperText(__('creators-ticketing::resources.field.name_helper')),

            TextInput::make('label')
                ->label(__('creators-ticketing::resources.field.label'))
                ->required()
                ->maxLength(255)
                ->helperText(__('creators-ticketing::resources.field.label_helper')),

            Select::make('type')
                ->label(__('creators-ticketing::resources.field.type'))
                ->required()
                ->options([
                    'text' => __('creators-ticketing::resources.field.types.text'),
                    'textarea' => __('creators-ticketing::resources.field.types.textarea'),
                    'email' => __('creators-ticketing::resources.field.types.email'),
                    'tel' => __('creators-ticketing::resources.field.types.tel'),
                    'number' => __('creators-ticketing::resources.field.types.number'),
                    'url' => __('creators-ticketing::resources.field.types.url'),
                    'select' => __('creators-ticketing::resources.field.types.select'),
                    'radio' => __('creators-ticketing::resources.field.types.radio'),
                    'checkbox' => __('creators-ticketing::resources.field.types.checkbox'),
                    'toggle' => __('creators-ticketing::resources.field.types.toggle'),
                    'date' => __('creators-ticketing::resources.field.types.date'),
                    'datetime' => __('creators-ticketing::resources.field.types.datetime'),
                    'file' => __('creators-ticketing::resources.field.types.file'),
                ])
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (! in_array($state, ['select', 'radio'])) {
                        $set('options', null);
                    }
                }),

            KeyValue::make('options')
                ->label(__('creators-ticketing::resources.field.options'))
                ->keyLabel(__('creators-ticketing::resources.field.options_key'))
                ->valueLabel(__('creators-ticketing::resources.field.options_value'))
                ->visible(fn ($get) => in_array($get('type'), ['select', 'radio']))
                ->helperText(__('creators-ticketing::resources.field.options_helper'))
                ->columnSpanFull(),

            Toggle::make('is_required')
                ->label(__('creators-ticketing::resources.field.is_required'))
                ->default(false),

            Textarea::make('help_text')
                ->label(__('creators-ticketing::resources.field.help_text'))
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),

            Textarea::make('validation_rules')
                ->label(__('creators-ticketing::resources.field.validation_rules'))
                ->rows(2)
                ->helperText(__('creators-ticketing::resources.field.validation_helper'))
                ->columnSpanFull(),

            TextInput::make('order')
                ->label(__('creators-ticketing::resources.field.order'))
                ->numeric()
                ->default(function ($record) {
                    if ($record) {
                        return $record->order;
                    }

                    $maxOrder = $this->getOwnerRecord()->fields()->max('order');

                    return $maxOrder !== null ? $maxOrder + 1 : 0;
                })
                ->helperText(__('creators-ticketing::resources.field.order_helper')),
        ];
    }
}
