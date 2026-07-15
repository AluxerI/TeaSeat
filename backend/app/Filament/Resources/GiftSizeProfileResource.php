<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GiftSizeProfileResource\Pages;
use App\Models\GiftSizeProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GiftSizeProfileResource extends Resource
{
    protected static ?string $model = GiftSizeProfile::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Управление товарами';
    protected static ?string $navigationLabel = 'Размеры подарков';
    protected static ?string $modelLabel = 'профиль размера';
    protected static ?string $pluralModelLabel = 'профили размеров';

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
                ->label('Назначение')
                ->options([
                    GiftSizeProfile::KIND_ITEM => 'Размер товара',
                    GiftSizeProfile::KIND_BOX => 'Размер коробки',
                ])
                ->required()
                ->live(),
            Forms\Components\TextInput::make('width_cells')
                ->label('Ширина в ячейках')
                ->integer()->minValue(1)->required(),
            Forms\Components\TextInput::make('height_cells')
                ->label('Высота в ячейках')
                ->integer()->minValue(1)->required(),
            Forms\Components\Toggle::make('can_rotate')
                ->label('Разрешить поворот')
                ->default(true),
            Forms\Components\TextInput::make('max_weight_grams')
                ->label('Максимальный вес')
                ->integer()->minValue(1)->suffix('г')
                ->visible(fn ($get) => $get('kind') === GiftSizeProfile::KIND_BOX),
            Forms\Components\TextInput::make('default_markup_amount')
                ->label('Наценка коробки и сборки')
                ->numeric()->minValue(0)->default(0)->prefix('₽')
                ->visible(fn ($get) => $get('kind') === GiftSizeProfile::KIND_BOX),
            Forms\Components\Toggle::make('simple_constructor_enabled')
                ->label('Доступна в простом конструкторе')
                ->helperText('Количество чая и сладостей задаётся отдельно для каждой коробки.')
                ->default(false)
                ->live()
                ->visible(fn ($get) => $get('kind') === GiftSizeProfile::KIND_BOX)
                ->dehydrateStateUsing(fn ($state, $get): bool =>
                    $get('kind') === GiftSizeProfile::KIND_BOX && (bool) $state
                ),
            Forms\Components\TextInput::make('simple_tea_count')
                ->label('Позиций чая')
                ->integer()
                ->minValue(1)
                ->maxValue(config('gifts.max_layout_items', 40))
                ->required(fn ($get): bool =>
                    $get('kind') === GiftSizeProfile::KIND_BOX
                    && (bool) $get('simple_constructor_enabled')
                )
                ->visible(fn ($get): bool =>
                    $get('kind') === GiftSizeProfile::KIND_BOX
                    && (bool) $get('simple_constructor_enabled')
                )
                ->dehydrateStateUsing(fn ($state, $get) =>
                    $get('kind') === GiftSizeProfile::KIND_BOX
                    && (bool) $get('simple_constructor_enabled')
                        ? $state
                        : null
                ),
            Forms\Components\TextInput::make('simple_sweet_count')
                ->label('Позиций сладостей')
                ->integer()
                ->minValue(1)
                ->maxValue(config('gifts.max_layout_items', 40))
                ->required(fn ($get): bool =>
                    $get('kind') === GiftSizeProfile::KIND_BOX
                    && (bool) $get('simple_constructor_enabled')
                )
                ->visible(fn ($get): bool =>
                    $get('kind') === GiftSizeProfile::KIND_BOX
                    && (bool) $get('simple_constructor_enabled')
                )
                ->dehydrateStateUsing(fn ($state, $get) =>
                    $get('kind') === GiftSizeProfile::KIND_BOX
                    && (bool) $get('simple_constructor_enabled')
                        ? $state
                        : null
                ),
            Forms\Components\Toggle::make('is_active')
                ->label('Активен')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Код')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Название')->searchable(),
                Tables\Columns\TextColumn::make('kind')->label('Назначение')->badge(),
                Tables\Columns\TextColumn::make('dimensions')
                    ->label('Размер')
                    ->state(fn (GiftSizeProfile $record): string =>
                        "{$record->width_cells} × {$record->height_cells}"
                    ),
                Tables\Columns\TextColumn::make('default_markup_amount')->label('Наценка')->money('RUB'),
                Tables\Columns\TextColumn::make('simple_rule')
                    ->label('Простой набор')
                    ->state(fn (GiftSizeProfile $record): string =>
                        $record->simple_constructor_enabled
                            ? "{$record->simple_tea_count} чая + {$record->simple_sweet_count} сладости"
                            : '—'
                    ),
                Tables\Columns\IconColumn::make('is_active')->label('Активен')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->options([
                    GiftSizeProfile::KIND_ITEM => 'Товары',
                    GiftSizeProfile::KIND_BOX => 'Коробки',
                ]),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGiftSizeProfiles::route('/'),
            'create' => Pages\CreateGiftSizeProfile::route('/create'),
            'edit' => Pages\EditGiftSizeProfile::route('/{record}/edit'),
        ];
    }
}
