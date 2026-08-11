<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryTimeSlotResource\Pages;
use App\Models\DeliveryMethod;
use App\Models\DeliveryTimeSlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeliveryTimeSlotResource extends Resource
{
    protected static ?string $model = DeliveryTimeSlot::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Заказы';
    protected static ?string $navigationLabel = 'Интервалы доставки';
    protected static ?string $modelLabel = 'интервал доставки';
    protected static ?string $pluralModelLabel = 'интервалы доставки';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('delivery_method_id')
                ->label('Способ доставки')
                ->options(fn (): array => DeliveryMethod::query()
                    ->whereIn('type', [
                        DeliveryMethod::TYPE_COURIER,
                        DeliveryMethod::TYPE_EXPRESS,
                    ])
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('weekday')
                ->label('День недели')
                ->options(self::weekdayOptions())
                ->required(),
            Forms\Components\TimePicker::make('time_from')
                ->label('Начало')
                ->seconds(false)
                ->required(),
            Forms\Components\TimePicker::make('time_to')
                ->label('Окончание')
                ->seconds(false)
                ->after('time_from')
                ->required(),
            Forms\Components\TextInput::make('capacity')
                ->label('Заказов в интервале')
                ->helperText('Курьер сам выбирает порядок доставок внутри интервала.')
                ->integer()
                ->minValue(1)
                ->required(),
            Forms\Components\Toggle::make('is_active')
                ->label('Доступен для новых заказов')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('deliveryMethod.name')
                    ->label('Способ доставки')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weekday')
                    ->label('День')
                    ->formatStateUsing(fn (int $state): string =>
                        self::weekdayOptions()[$state] ?? (string) $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('interval')
                    ->label('Интервал')
                    ->state(fn (DeliveryTimeSlot $record): string =>
                        $record->timeFrom() . '–' . $record->timeTo()),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Вместимость')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('delivery_method_id')
                    ->label('Способ доставки')
                    ->relationship('deliveryMethod', 'name'),
                Tables\Filters\SelectFilter::make('weekday')
                    ->label('День недели')
                    ->options(self::weekdayOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (DeliveryTimeSlot $record): bool =>
                        !$record->orders()->exists()),
            ])
            ->defaultSort('weekday');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage delivery schedules') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && !$record->orders()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryTimeSlots::route('/'),
            'create' => Pages\CreateDeliveryTimeSlot::route('/create'),
            'edit' => Pages\EditDeliveryTimeSlot::route('/{record}/edit'),
        ];
    }

    /** @return array<int, string> */
    private static function weekdayOptions(): array
    {
        return [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];
    }
}
