<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Placeholder;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Основная информация')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Название товара')
                                ->disabled()
                                ->dehydrated(false),
                            
                            TextInput::make('price')
                                ->label('Цена')
                                ->disabled()
                                ->dehydrated(false)
                                ->prefix('₽'),
                            
                            TextInput::make('brand.name')
                                ->label('Бренд')
                                ->disabled()
                                ->dehydrated(false),
                            
                            TextInput::make('weight_grams')
                                ->label('Вес (грамм)')
                                ->disabled()
                                ->dehydrated(false)
                                ->suffix('г'),
                            
                            TextInput::make('sold_count')
                                ->label('Продано')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    
                    RichEditor::make('description')
                        ->label('Описание')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    
                    Placeholder::make('ingredients')
                        ->label('Ингредиенты/Состав')
                        ->content(fn ($record) => $record?->ingredients ?? '—')
                        ->columnSpanFull(),
                ]),

            Section::make('Категория')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('category_name')
                                ->label('Категория')
                                ->disabled()
                                ->dehydrated(false)
                                ->default(fn ($record) => $record->sub_subcategories->first()?->subcategory?->category?->name ?? '—'),
                            
                            TextInput::make('subcategory_name')
                                ->label('Подкатегория')
                                ->disabled()
                                ->dehydrated(false)
                                ->default(fn ($record) => $record->sub_subcategories->first()?->subcategory?->name ?? '—'),
                            
                            TextInput::make('sub_subcategory_name')
                                ->label('Под-подкатегория')
                                ->disabled()
                                ->dehydrated(false)
                                ->default(fn ($record) => $record->sub_subcategories->first()?->name ?? '—'),
                        ]),
                ]),

            Section::make('Изображения')
                ->schema([
                    Placeholder::make('images')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record || $record->images->isEmpty()) {
                                return 'Нет изображений';
                            }
                            
                            $html = '<div class="grid grid-cols-4 gap-4">';
                            foreach ($record->images as $image) {
                                $type = [];
                                if ($image->is_main) $type[] = 'Главное';
                                if ($image->is_background) $type[] = 'Фоновое';
                                $typeLabel = $type ? ' (' . implode(', ', $type) . ')' : '';
                                
                                $html .= '<div class="border rounded p-2">';
                                $html .= '<img src="' . $image->image_url . '" class="w-full h-32 object-cover mb-2">';
                                $html .= '<div class="text-xs text-center">' . $typeLabel . '</div>';
                                $html .= '<div class="text-xs text-center text-gray-500">порядок: ' . $image->sort_order . '</div>';
                                $html .= '</div>';
                            }
                            $html .= '</div>';
                            
                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('Склад и остатки')
                ->schema([
                    Placeholder::make('inventories')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record || $record->inventories->isEmpty()) {
                                return 'Нет записей о наличии на складах';
                            }
                            
                            $html = '<table class="w-full border-collapse">';
                            $html .= '<thead><tr class="bg-gray-100">';
                            $html .= '<th class="border p-2 text-left">Склад</th>';
                            $html .= '<th class="border p-2 text-left">Город</th>';
                            $html .= '<th class="border p-2 text-left">Количество</th>';
                            $html .= '<th class="border p-2 text-left">Последняя поставка</th>';
                            $html .= '</tr></thead><tbody>';
                            
                            foreach ($record->inventories as $inventory) {
                                $html .= '<tr>';
                                $html .= '<td class="border p-2">' . ($inventory->warehouse->name ?? '—') . '</td>';
                                $html .= '<td class="border p-2">' . ($inventory->warehouse->city ?? '—') . '</td>';
                                $html .= '<td class="border p-2">' . $inventory->quantity . '</td>';
                                $html .= '<td class="border p-2">' . ($inventory->last_restock_date ?? '—') . '</td>';
                                $html .= '</tr>';
                            }
                            
                            $html .= '</tbody></table>';
                            
                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('Поставщики')
                ->schema([
                    Placeholder::make('suppliers')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record || $record->suppliers->isEmpty()) {
                                return 'Нет поставщиков для этого товара';
                            }
                            
                            $html = '<table class="w-full border-collapse">';
                            $html .= '<thead><tr class="bg-gray-100">';
                            $html .= '<th class="border p-2 text-left">Поставщик</th>';
                            $html .= '<th class="border p-2 text-left">Закупочная цена</th>';
                            $html .= '<th class="border p-2 text-left">Срок поставки</th>';
                            $html .= '<th class="border p-2 text-left">Мин. заказ</th>';
                            $html .= '<th class="border p-2 text-left">Статус</th>';
                            $html .= '</tr></thead><tbody>';
                            
                            foreach ($record->suppliers as $supplier) {
                                $html .= '<tr>';
                                $html .= '<td class="border p-2">' . $supplier->name . '</td>';
                                $html .= '<td class="border p-2">' . ($supplier->pivot->cost_price ?? '—') . ' ₽</td>';
                                $html .= '<td class="border p-2">' . ($supplier->pivot->lead_time_days ?? '—') . ' дн.</td>';
                                $html .= '<td class="border p-2">' . ($supplier->pivot->min_order_quantity ?? '—') . '</td>';
                                $html .= '<td class="border p-2">' . ($supplier->pivot->is_active ? 'Активен' : 'Неактивен') . '</td>';
                                $html .= '</tr>';
                            }
                            
                            $html .= '</tbody></table>';
                            
                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->columnSpanFull(),
                ]),

            Section::make('Статистика')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('sold_count')
                                ->label('Продано всего')
                                ->disabled()
                                ->dehydrated(false),
                            
                            Placeholder::make('total_quantity')
                                ->label('Общий остаток')
                                ->content(fn ($record) => $record->inventories->sum('quantity') . ' шт.'),
                            
                            Placeholder::make('created_at')
                                ->label('Дата создания')
                                ->content(fn ($record) => $record->created_at?->format('d.m.Y H:i') ?? '—'),
                        ]),
                ]),
        ];
    }
}