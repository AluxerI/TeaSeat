<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Traits\HasNavigationBadge;
use Filament\Notifications\Notification;

class BrandResource extends Resource
{
    use HasNavigationBadge;
    
    protected static ?string $model = Brand::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Управление каталогом';
    protected static ?string $navigationLabel = 'Бренды';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('products')
            ->addSelect([
                'total_sold' => Product::selectRaw('COALESCE(SUM(sold_count), 0)')
                    ->whereColumn('brand_id', 'brands.id')
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Информация о бренде')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Название бренда')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                
                                Forms\Components\TextInput::make('country')
                                    ->label('Страна')
                                    ->maxLength(100)
                                    ->placeholder('Например: Россия, Китай, Индия'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                
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
                    ->color('info'),
                
                // ✅ Используем withCount из запроса
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),
                
                // ✅ Используем addSelect из запроса
                TextColumn::make('total_sold')
                    ->label('Продано')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label('Страна')
                    ->options(fn () => Brand::distinct()->whereNotNull('country')->pluck('country', 'country'))
                    ->multiple()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->products()->count() > 0) {
                            Notification::make()
                                ->title('Невозможно удалить бренд')
                                ->body('У этого бренда есть товары. Сначала удалите или переназначьте товары.')
                                ->danger()
                                ->send();

                            $this->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->products()->count() > 0) {
                                    Notification::make()
                                        ->title('Невозможно удалить некоторые бренды')
                                        ->body('Бренды с товарами не могут быть удалены.')
                                        ->danger()
                                        ->send();

                                    $this->halt();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}