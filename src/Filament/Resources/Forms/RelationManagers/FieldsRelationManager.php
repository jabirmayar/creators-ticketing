<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Forms\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\RelationManagers\RelationManager;

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
                    'file_multiple' =>  __('creators-ticketing::resources.field.types.file_multiple'), 
                ])
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if (!in_array($state, ['select', 'radio'])) {
                        $set('options', null);
                    }
                    
                    if (in_array($state, ['file', 'file_multiple']) && empty($get('validation_rules'))) {
                        $set('validation_rules', 'mimes:jpg,jpeg,png,pdf,doc,docx|max:5120');
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
                ->placeholder('e.g. mimes:jpg,png|max:2048')
                ->rows(3)
                ->columnSpanFull()
                ->visible(fn ($get) => !empty($get('type'))),

            Placeholder::make('validation_examples')
                ->label(__('creators-ticketing::resources.field.validation_helper'))
                ->content(fn ($get) => $this->getValidationExamples($get('type')))
                ->visible(fn ($get) => !empty($get('type')))
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

    protected function getValidationExamples(string $type): string
    {
        $examples = [
            'text' => 'min:3|max:255|regex:/^[a-zA-Z0-9\s]+$/',
            'textarea' => 'min:10|max:5000',
            'email' => 'email:rfc,dns',
            'tel' => 'regex:/^[0-9\+\-\(\)\s]+$/',
            'number' => 'integer|min:1|max:100',
            'url' => 'url|active_url',
            'file' => 'mimes:jpg,png,pdf|max:5120',
            'file_multiple' => 'mimes:jpg,png,pdf|max:5120|max_files:5',
            'date' => 'date|after:today',
            'datetime' => 'date|after:now',
        ];

        return $examples[$type] ?? 'max:255';
    }
}