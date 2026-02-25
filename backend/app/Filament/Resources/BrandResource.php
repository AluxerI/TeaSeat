<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Управление каталогом';

    protected static ?string $navigationLabel = 'Бренды';

    protected static ?string $modelLabel = 'Бренд';

    protected static ?string $pluralModelLabel = 'Бренды';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Информация о бренде')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Название бренда')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true),
                                
                                TextInput::make('country')
                                    ->label('Страна')
                                    ->maxLength(100)
                                    ->placeholder('Например: Россия, Китай, Индия')
                                    ->helperText('Страна происхождения бренда'),
                                
                                FileUpload::make('logo')
                                    ->label('Логотип бренда')
                                    ->image()
                                    ->directory('brands')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Статистика')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('products_count')
                                    ->label('Товаров бренда')
                                    ->content(fn ($record) => $record?->products()->count() ?? 0)
                                    ->extraAttributes(['class' => 'text-xl font-bold']),
                                
                                Forms\Components\Placeholder::make('total_sold')
                                    ->label('Продано товаров')
                                    ->content(fn ($record) => $record?->products->sum('sold_count') ?? 0)
                                    ->extraAttributes(['class' => 'text-xl font-bold']),
                                
                                Forms\Components\Placeholder::make('avg_price')
                                    ->label('Средняя цена')
                                    ->content(fn ($record) => $record?->products->avg('price') 
                                        ? number_format($record->products->avg('price'), 2) . ' ₽' 
                                        : '—')
                                    ->extraAttributes(['class' => 'text-xl font-bold']),
                            ])
                            ->visible(fn ($record) => $record !== null),
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
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('country')
                    ->label('Страна')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),
                
                TextColumn::make('total_sold')
                    ->label('Продано')
                    ->getStateUsing(fn ($record) => $record->products->sum('sold_count'))
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),
                
                TextColumn::make('created_at')
                    ->label('Добавлен')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_products')
                    ->label('Есть товары')
                    ->query(fn (Builder $query): Builder => $query->has('products')),
                
                Tables\Filters\Filter::make('no_products')
                    ->label('Нет товаров')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('products')),
                
                Tables\Filters\SelectFilter::make('country')
                    ->label('Страна')
                    ->options(function () {
                        return Brand::distinct()->pluck('country', 'country')->filter()->toArray();
                    })
                    ->multiple(),
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
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
            'view' => Pages\ViewBrand::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count() ?: null;
    }
}