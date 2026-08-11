<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderFeedbackResource\Pages;
use App\Models\ContentModerationLog;
use App\Models\OrderFeedback;
use App\Services\ReviewModerationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderFeedbackResource extends Resource
{
    protected static ?string $model = OrderFeedback::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'Модерация';
    protected static ?string $navigationLabel = 'Оценки заказов';
    protected static ?string $modelLabel = 'Оценка заказа';
    protected static ?string $pluralModelLabel = 'Оценки заказов';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'order', 'latestModeration']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Оценка клиента')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('order_id')
                        ->label('Заказ')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('user.name')
                        ->label('Покупатель')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('delivery_rating')
                        ->label('Доставка')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('packing_rating')
                        ->label('Сборка')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('service_rating')
                        ->label('Сервис')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('status')
                        ->label('Статус')->disabled()->dehydrated(false),
                ]),
                Forms\Components\Textarea::make('comment')
                    ->label('Комментарий клиента')
                    ->rows(6)->disabled()->dehydrated(false)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_id')->label('Заказ')->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Покупатель')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('delivery_rating')->label('Доставка')->sortable(),
                Tables\Columns\TextColumn::make('packing_rating')->label('Сборка')->sortable(),
                Tables\Columns\TextColumn::make('service_rating')->label('Сервис')->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label('Комментарий')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        $state === OrderFeedback::STATUS_PUBLISHED ? 'Опубликована' : 'Скрыта')
                    ->color(fn (string $state): string =>
                        $state === OrderFeedback::STATUS_PUBLISHED ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создана')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    OrderFeedback::STATUS_PUBLISHED => 'Опубликована',
                    OrderFeedback::STATUS_HIDDEN => 'Скрыта',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('hide')
                    ->label('Скрыть')->icon('heroicon-o-eye-slash')->color('danger')
                    ->visible(fn (OrderFeedback $record): bool =>
                        $record->status === OrderFeedback::STATUS_PUBLISHED
                        && (auth()->user()?->can('moderate reviews') ?? false))
                    ->form([
                        Forms\Components\Select::make('reason_code')
                            ->label('Нарушение')->options(ContentModerationLog::reasonOptions())
                            ->required(),
                        Forms\Components\Textarea::make('comment')
                            ->label('Комментарий модератора')->required()->maxLength(2000),
                    ])
                    ->action(fn (OrderFeedback $record, array $data) =>
                        app(ReviewModerationService::class)->hideFeedback(
                            auth()->user(), $record->id,
                            $data['reason_code'], $data['comment']
                        )),
                Tables\Actions\Action::make('restore')
                    ->label('Восстановить')->icon('heroicon-o-arrow-path')->color('success')
                    ->visible(fn (OrderFeedback $record): bool =>
                        $record->status === OrderFeedback::STATUS_HIDDEN
                        && (auth()->user()?->can('moderate reviews') ?? false))
                    ->form([
                        Forms\Components\Textarea::make('comment')
                            ->label('Причина восстановления')->required()->maxLength(2000),
                    ])
                    ->action(fn (OrderFeedback $record, array $data) =>
                        app(ReviewModerationService::class)->restoreFeedback(
                            auth()->user(), $record->id, $data['comment']
                        )),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderFeedback::route('/'),
            'view' => Pages\ViewOrderFeedback::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view reviews') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
