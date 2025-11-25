<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Forms;

use BackedEnum;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\TernaryFilter;
use daacreators\CreatorsTicketing\Models\Form;
use Filament\Schemas\Components\Utilities\Set;
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Filament\Resources\Forms\Pages;
use daacreators\CreatorsTicketing\Traits\HasNavigationVisibility;
use daacreators\CreatorsTicketing\Filament\Resources\Forms\RelationManagers\FieldsRelationManager;

class FormResource extends Resource
{
    use HasNavigationVisibility, HasTicketingNavGroup;
    
    protected static ?string $model = Form::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-duplicate';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.form.title');
    }

    public static function getModelLabel(): string
    {
        return __('creators-ticketing::resources.form.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label(__('creators-ticketing::resources.form.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->label(__('creators-ticketing::resources.form.slug'))
                ->required()
                ->maxLength(255)
                ->unique(Form::class, 'slug', ignoreRecord: true),

            Textarea::make('description')
                ->label(__('creators-ticketing::resources.form.description'))
                ->rows(3)
                ->maxLength(65535)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label(__('creators-ticketing::resources.form.is_active'))
                ->required()
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('creators-ticketing::resources.form.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('creators-ticketing::resources.form.slug'))
                    ->searchable(),

                TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label(__('creators-ticketing::resources.form.fields')),

                TextColumn::make('departments_count')
                    ->counts('departments')
                    ->label(__('creators-ticketing::resources.form.departments')),

                IconColumn::make('is_active')
                    ->label(__('creators-ticketing::resources.form.is_active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('creators-ticketing::resources.form.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('creators-ticketing::resources.form.filters.active'))
                    ->boolean()
                    ->trueLabel(__('creators-ticketing::resources.form.filters.active_only'))
                    ->falseLabel(__('creators-ticketing::resources.form.filters.inactive_only'))
                    ->native(false),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
    }
}