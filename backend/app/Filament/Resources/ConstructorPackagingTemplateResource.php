<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConstructorPackagingTemplateResource\Pages;
use App\Models\ConstructorPackagingTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConstructorPackagingTemplateResource extends Resource
{
    protected static ?string $model = ConstructorPackagingTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Упаковка конструктора';
    protected static ?string $modelLabel = 'шаблон упаковки';
    protected static ?string $pluralModelLabel = 'шаблоны упаковки';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Код')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('kind')
                ->label('Вид упаковки')
                ->options([
                    ConstructorPackagingTemplate::KIND_POUCH => 'Пакет',
                    ConstructorPackagingTemplate::KIND_WRAPPER => 'Обёртка сладости',
                    ConstructorPackagingTemplate::KIND_JAR => 'Баночка',
                    ConstructorPackagingTemplate::KIND_OTHER => 'Другое',
                ])
                ->default(ConstructorPackagingTemplate::KIND_OTHER)
                ->required(),
            Forms\Components\FileUpload::make('image_path')
                ->label('Изображение упаковки')
                ->disk('public')
                ->directory('constructor-packaging')
                ->visibility('public')
                ->image()
                ->required()
                ->helperText('Лучше использовать PNG или WebP с прозрачным фоном.'),
            Forms\Components\Hidden::make('disk')->default('public'),
            Forms\Components\Toggle::make('is_active')
                ->label('Активен')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Изображение')
                    ->disk('public'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Вид')
                    ->badge(),
                Tables\Columns\TextColumn::make('product_sizes_count')
                    ->label('Форматов')
                    ->counts('productSizes'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConstructorPackagingTemplates::route('/'),
            'create' => Pages\CreateConstructorPackagingTemplate::route('/create'),
            'edit' => Pages\EditConstructorPackagingTemplate::route('/{record}/edit'),
        ];
    }
}
