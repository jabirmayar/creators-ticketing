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
use Filament\Resources\RelationManagers\RelationManager;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $recordTitleAttribute = 'label';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->label('Field Name'),

                TextColumn::make('type')
                    ->badge()
                    ->color('info'),

                TextColumn::make('is_required')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Required' : 'Optional')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('order')
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
                ->required()
                ->maxLength(255)
                ->helperText('Field name used internally (e.g., phone_number, company_name)'),

            TextInput::make('label')
                ->required()
                ->maxLength(255)
                ->helperText('Label shown to users'),

            Select::make('type')
                ->required()
                ->options([
                    'text' => 'Text Input',
                    'textarea' => 'Text Area',
                    'email' => 'Email',
                    'tel' => 'Phone',
                    'number' => 'Number',
                    'url' => 'URL',
                    'select' => 'Dropdown',
                    'radio' => 'Radio Buttons',
                    'checkbox' => 'Checkbox',
                    'toggle' => 'Toggle',
                    'date' => 'Date',
                    'datetime' => 'Date & Time',
                    'file' => 'File Upload',
                ])
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (!in_array($state, ['select', 'radio'])) {
                        $set('options', null);
                    }
                }),

            KeyValue::make('options')
                ->keyLabel('Value')
                ->valueLabel('Label')
                ->visible(fn ($get) => in_array($get('type'), ['select', 'radio']))
                ->helperText('Add options for dropdown or radio fields')
                ->columnSpanFull(),

            Toggle::make('is_required')
                ->label('Required Field')
                ->default(false),

            Textarea::make('help_text')
                ->label('Help Text')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),

            Textarea::make('validation_rules')
                ->label('Additional Validation Rules')
                ->rows(2)
                ->helperText('Laravel validation rules (e.g., min:5|max:100)')
                ->columnSpanFull(),

            TextInput::make('order')
                ->numeric()
                ->default(function ($record) {
                    if ($record) {
                        return $record->order;
                    }
                    
                    $maxOrder = $this->getOwnerRecord()->fields()->max('order');
                    return $maxOrder !== null ? $maxOrder + 1 : 0;
                })
                ->helperText('Display order (lower numbers appear first)'),
        ];
    }
}