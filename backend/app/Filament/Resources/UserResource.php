<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Управление пользователями';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Пользователь')
                    ->tabs([
                        Tab::make('Основная информация')
                            ->schema([
                                Section::make('Данные пользователя')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Имя')
                                                    ->required()
                                                    ->maxLength(255),
                                                
                                                TextInput::make('email')
                                                    ->label('Email')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true),
                                                
                                                TextInput::make('phone')
                                                    ->label('Телефон')
                                                    ->tel()
                                                    ->maxLength(20)
                                                    ->unique(ignoreRecord: true),
                                                
                                                Select::make('timezone')
                                                    ->label('Часовой пояс')
                                                    ->options([
                                                        'UTC' => 'UTC',
                                                        'Europe/Moscow' => 'Москва',
                                                        'Europe/Kaliningrad' => 'Калининград',
                                                        'Europe/Samara' => 'Самара',
                                                        'Asia/Yekaterinburg' => 'Екатеринбург',
                                                        'Asia/Omsk' => 'Омск',
                                                        'Asia/Krasnoyarsk' => 'Красноярск',
                                                        'Asia/Irkutsk' => 'Иркутск',
                                                        'Asia/Yakutsk' => 'Якутск',
                                                        'Asia/Vladivostok' => 'Владивосток',
                                                        'Asia/Kamchatka' => 'Камчатка',
                                                    ])
                                                    ->default('UTC'),
                                                
                                                TextInput::make('password')
                                                    ->label('Пароль')
                                                    ->password()
                                                    ->dehydrated(fn ($state) => filled($state))
                                                    ->required(fn (string $context): bool => $context === 'create')
                                                    ->maxLength(255),
                                            ]),
                                    ]),
                                
                                Section::make('Статус и верификация')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Toggle::make('is_active')
                                                    ->label('Активен')
                                                    ->default(true)
                                                    ->helperText('Заблокировать пользователя'),
                                                
                                                DateTimePicker::make('email_verified_at')
                                                    ->label('Email подтвержден')
                                                    ->nullable(),
                                                
                                                DateTimePicker::make('phone_verified_at')
                                                    ->label('Телефон подтвержден')
                                                    ->nullable(),
                                            ]),
                                    ]),
                                
                                Section::make('Социальные сети')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('provider')
                                                    ->label('Провайдер')
                                                    ->disabled()
                                                    ->dehydrated(false),
                                                
                                                TextInput::make('provider_id')
                                                    ->label('ID провайдера')
                                                    ->disabled()
                                                    ->dehydrated(false),
                                            ]),
                                    ])
                                    ->visible(fn ($record) => $record && $record->provider),
                            ]),
                        
                        Tab::make('Роли и права')
                            ->schema([
                                Section::make('Управление ролями')
                                    ->schema([
                                        Select::make('roles')
                                            ->label('Роли')
                                            ->multiple()
                                            ->relationship('roles', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->options(function () {
                                                return Role::pluck('name', 'id');
                                            })
                                            ->helperText('Назначьте роли пользователю (admin, manager, user)'),
                                    ]),
                                
                                Section::make('Пермиссии')
                                    ->schema([
                                        Forms\Components\Placeholder::make('permissions')
                                            ->label('Права доступа')
                                            ->content(function ($record) {
                                                if (!$record || !$record->roles->count()) {
                                                    return 'Нет назначенных прав';
                                                }
                                                
                                                $permissions = $record->getAllPermissions()->pluck('name')->toArray();
                                                
                                                if (empty($permissions)) {
                                                    return 'Нет прав доступа';
                                                }
                                                
                                                $html = '<div class="grid grid-cols-3 gap-2">';
                                                foreach ($permissions as $permission) {
                                                    $html .= '<div class="text-sm bg-gray-100 px-2 py-1 rounded">' . $permission . '</div>';
                                                }
                                                $html .= '</div>';
                                                
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        
                        Tab::make('Адреса доставки')
                            ->schema([
                                Repeater::make('addresses')
                                    ->relationship()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('postal_code')
                                                    ->label('Индекс')
                                                    ->maxLength(10),
                                                
                                                TextInput::make('city')
                                                    ->label('Город')
                                                    ->maxLength(255),
                                                
                                                TextInput::make('street')
                                                    ->label('Улица, дом, квартира')
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                        
                        Tab::make('Заказы')
                            ->schema([
                                Forms\Components\Placeholder::make('orders_list')
                                    ->label('')
                                    ->content(function ($record) {
                                        if (!$record || $record->orders->isEmpty()) {
                                            return 'Нет заказов';
                                        }
                                        
                                        $html = '<table class="w-full border-collapse">';
                                        $html .= '<thead><tr class="bg-gray-100">';
                                        $html .= '<th class="border p-2 text-left">№ заказа</th>';
                                        $html .= '<th class="border p-2 text-left">Статус</th>';
                                        $html .= '<th class="border p-2 text-left">Сумма</th>';
                                        $html .= '<th class="border p-2 text-left">Дата</th>';
                                        $html .= '</tr></thead><tbody>';
                                        
                                        foreach ($record->orders->sortByDesc('created_at')->take(10) as $order) {
                                            $html .= '<tr>';
                                            $html .= '<td class="border p-2">#' . $order->id . '</td>';
                                            $html .= '<td class="border p-2">' . $order->getStatusName($order->status) . '</td>';
                                            $html .= '<td class="border p-2">' . number_format($order->final_total, 2) . ' ₽</td>';
                                            $html .= '<td class="border p-2">' . $order->created_at->format('d.m.Y H:i') . '</td>';
                                            $html .= '</tr>';
                                        }
                                        
                                        $html .= '</tbody></table>';
                                        
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpanFull(),
                            ]),
                        
                        Tab::make('Скидки')
                            ->schema([
                                Forms\Components\Placeholder::make('discounts_list')
                                    ->label('Персональные скидки')
                                    ->content(function ($record) {
                                        if (!$record || $record->discounts->isEmpty()) {
                                            return 'Нет персональных скидок';
                                        }
                                        
                                        $html = '<table class="w-full border-collapse">';
                                        $html .= '<thead><tr class="bg-gray-100">';
                                        $html .= '<th class="border p-2 text-left">Скидка</th>';
                                        $html .= '<th class="border p-2 text-left">Размер</th>';
                                        $html .= '<th class="border p-2 text-left">Статус</th>';
                                        $html .= '<th class="border p-2 text-left">Использовано</th>';
                                        $html .= '<th class="border p-2 text-left">Активирована</th>';
                                        $html .= '</tr></thead><tbody>';
                                        
                                        foreach ($record->discounts as $discount) {
                                            $html .= '<tr>';
                                            $html .= '<td class="border p-2">' . $discount->name . '</td>';
                                            $html .= '<td class="border p-2">' . $discount->value . '%</td>';
                                            $html .= '<td class="border p-2">' . ($discount->pivot->is_used ? 'Использована' : 'Активна') . '</td>';
                                            $html .= '<td class="border p-2">' . $discount->pivot->used_count . '</td>';
                                            $html .= '<td class="border p-2">' . $discount->pivot->activated_at->format('d.m.Y H:i') . '</td>';
                                            $html .= '</tr>';
                                        }
                                        
                                        $html .= '</tbody></table>';
                                        
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpanFull(),
                            ]),
                        
                        Tab::make('Отзывы')
                            ->schema([
                                Forms\Components\Placeholder::make('reviews_list')
                                    ->label('')
                                    ->content(function ($record) {
                                        if (!$record || $record->reviews->isEmpty()) {
                                            return 'Нет отзывов';
                                        }
                                        
                                        $html = '<table class="w-full border-collapse">';
                                        $html .= '<thead><tr class="bg-gray-100">';
                                        $html .= '<th class="border p-2 text-left">Товар</th>';
                                        $html .= '<th class="border p-2 text-left">Рейтинг</th>';
                                        $html .= '<th class="border p-2 text-left">Комментарий</th>';
                                        $html .= '<th class="border p-2 text-left">Дата</th>';
                                        $html .= '</tr></thead><tbody>';
                                        
                                        foreach ($record->reviews->sortByDesc('created_at')->take(10) as $review) {
                                            $html .= '<tr>';
                                            $html .= '<td class="border p-2">' . ($review->product->name ?? '—') . '</td>';
                                            $html .= '<td class="border p-2">' . $review->rating . '/5</td>';
                                            $html .= '<td class="border p-2">' . ($review->comment ?? '—') . '</td>';
                                            $html .= '<td class="border p-2">' . $review->created_at->format('d.m.Y H:i') . '</td>';
                                            $html .= '</tr>';
                                        }
                                        
                                        $html .= '</tbody></table>';
                                        
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->columnSpanFull(),
                            ]),
                        
                        Tab::make('Системная информация')
                            ->schema([
                                Section::make('Данные системы')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                DateTimePicker::make('created_at')
                                                    ->label('Дата регистрации')
                                                    ->disabled()
                                                    ->dehydrated(false),
                                                
                                                DateTimePicker::make('updated_at')
                                                    ->label('Последнее обновление')
                                                    ->disabled()
                                                    ->dehydrated(false),
                                                
                                                DateTimePicker::make('deleted_at')
                                                    ->label('Дата удаления')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->visible(fn ($record) => $record && $record->trashed()),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
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
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->toggleable(),
                
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                IconColumn::make('email_verified_at')
                    ->label('Email верифицирован')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                
                IconColumn::make('phone_verified_at')
                    ->label('Телефон верифицирован')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),
                
                TextColumn::make('roles.name')
                    ->label('Роли')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'manager' => 'warning',
                        'user' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                
                TextColumn::make('orders_count')
                    ->label('Заказов')
                    ->counts('orders')
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('Зарегистрирован')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Роли')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                
                Filter::make('is_active')
                    ->label('Только активные')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
                
                Filter::make('email_verified')
                    ->label('Email подтвержден')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at')),
                
                Filter::make('phone_verified')
                    ->label('Телефон подтвержден')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('phone_verified_at')),
                
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('С даты'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('По дату'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count() ?: null;
    }
}