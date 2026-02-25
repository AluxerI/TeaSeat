<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubcategoryResource\Pages;
use App\Models\Subcategory;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class SubcategoryResource extends Resource
{
    protected static ?string $model = Subcategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Управление каталогом';

    protected static ?string $navigationLabel = 'Подкатегории';

    protected static ?string $modelLabel = 'Подкатегория';

    protected static ?string $pluralModelLabel = 'Подкатегории';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Основная информация')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('category_id')
                                    ->label('Родительская категория')
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                
                                TextInput::make('name')
                                    ->label('Название подкатегории')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug())),
                                
                                TextInput::make('slug')
                                    ->label('URL-алиас')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                
                                FileUpload::make('icon')
                                    ->label('Иконка')
                                    ->image()
                                    ->directory('category-icons/subcategories')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg']),
                            ]),
                    ]),

                Section::make('Под-подкатегории')
                    ->schema([
                        Repeater::make('sub_subcategories')
                            ->relationship()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Название')
                                            ->required()
                                            ->maxLength(255),
                                        
                                        FileUpload::make('icon')
                                            ->label('Иконка')
                                            ->image()
                                            ->directory('category-icons/sub-subcategories')
                                            ->visibility('public')
                                            ->maxSize(1024),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('category.name')
                    ->label('Категория')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('sub_subcategories_count')
                    ->label('Под-подкатегории')
                    ->counts('sub_subcategories')
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->getStateUsing(function ($record) {
                        return \App\Models\Product::whereHas('sub_subcategories', function ($query) use ($record) {
                            $query->whereIn('sub_subcategories.id', $record->sub_subcategories->pluck('id'));
                        })->count();
                    })
                    ->sortable()
                    ->alignCenter(),
                
                ImageColumn::make('icon_url')
                    ->label('Иконка')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-icon.png')),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubcategories::route('/'),
            'create' => Pages\CreateSubcategory::route('/create'),
            'edit' => Pages\EditSubcategory::route('/{record}/edit'),
            'view' => Pages\ViewSubcategory::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }
}