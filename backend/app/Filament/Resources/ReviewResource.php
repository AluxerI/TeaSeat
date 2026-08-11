<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\ContentModerationLog;
use App\Models\Review;
use App\Services\ReviewModerationService;
use App\Traits\HasNavigationBadge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReviewResource extends Resource
{
    use HasNavigationBadge;

    protected static ?string $model = Review::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Модерация';
    protected static ?string $navigationLabel = 'Отзывы о товарах';
    protected static ?string $modelLabel = 'Отзыв о товаре';
    protected static ?string $pluralModelLabel = 'Отзывы о товарах';
    protected static ?string $recordTitleAttribute = 'comment';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user', 'product', 'reply', 'latestModeration',
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Отзыв клиента')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('user.name')
                        ->label('Покупатель')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('product.name')
                        ->label('Товар')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('rating')
                        ->label('Оценка')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('status')
                        ->label('Статус')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('order_product_id')
                        ->label('Позиция заказа')->disabled()->dehydrated(false),
                ]),
                Forms\Components\Textarea::make('comment')
                    ->label('Текст клиента')
                    ->rows(6)->disabled()->dehydrated(false)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Ответ и модерация')->schema([
                Forms\Components\Placeholder::make('company_reply')
                    ->label('Ответ компании')
                    ->content(fn (?Review $record): string =>
                        $record?->reply?->body ?: 'Ответ ещё не опубликован'),
                Forms\Components\Placeholder::make('latest_moderation')
                    ->label('Последнее действие')
                    ->content(function (?Review $record): string {
                        $log = $record?->latestModeration;
                        return $log
                            ? "{$log->action}: {$log->comment} ({$log->moderator_name})"
                            : 'Модерация не применялась';
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Покупатель')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')->searchable()->sortable()->limit(35),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Оценка')
                    ->formatStateUsing(fn ($state): string =>
                        str_repeat('★', (int) $state) . str_repeat('☆', 5 - (int) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label('Текст клиента')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Review::STATUS_PUBLISHED => 'Опубликован',
                        Review::STATUS_HIDDEN => 'Скрыт',
                        default => $state,
                    })
                    ->color(fn (string $state): string =>
                        $state === Review::STATUS_PUBLISHED ? 'success' : 'danger'),
                Tables\Columns\IconColumn::make('reply.id')
                    ->label('Ответ компании')->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создан')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')->options([
                        Review::STATUS_PUBLISHED => 'Опубликован',
                        Review::STATUS_HIDDEN => 'Скрыт',
                    ]),
                Tables\Filters\SelectFilter::make('rating')
                    ->label('Оценка')->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5']),
                Tables\Filters\SelectFilter::make('product')
                    ->label('Товар')->relationship('product', 'name')->searchable()->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('reply')
                    ->label('Ответить')->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->visible(fn (): bool =>
                        auth()->user()?->can('moderate reviews') ?? false)
                    ->form([
                        Forms\Components\Textarea::make('body')
                            ->label('Ответ от лица компании')
                            ->default(fn (Review $record): ?string => $record->reply?->body)
                            ->required()->maxLength(5000)->rows(6),
                    ])
                    ->action(fn (Review $record, array $data) =>
                        app(ReviewModerationService::class)->reply(
                            auth()->user(), $record->id, $data['body']
                        )),
                Tables\Actions\Action::make('hide')
                    ->label('Скрыть')->icon('heroicon-o-eye-slash')->color('danger')
                    ->visible(fn (Review $record): bool =>
                        $record->status === Review::STATUS_PUBLISHED
                        && (auth()->user()?->can('moderate reviews') ?? false))
                    ->form([
                        Forms\Components\Select::make('reason_code')
                            ->label('Нарушение')->options(ContentModerationLog::reasonOptions())
                            ->required(),
                        Forms\Components\Textarea::make('comment')
                            ->label('Комментарий модератора')->required()->maxLength(2000),
                    ])
                    ->action(fn (Review $record, array $data) =>
                        app(ReviewModerationService::class)->hideReview(
                            auth()->user(), $record->id,
                            $data['reason_code'], $data['comment']
                        )),
                Tables\Actions\Action::make('restore')
                    ->label('Восстановить')->icon('heroicon-o-arrow-path')->color('success')
                    ->visible(fn (Review $record): bool =>
                        $record->status === Review::STATUS_HIDDEN
                        && (auth()->user()?->can('moderate reviews') ?? false))
                    ->form([
                        Forms\Components\Textarea::make('comment')
                            ->label('Причина восстановления')->required()->maxLength(2000),
                    ])
                    ->action(fn (Review $record, array $data) =>
                        app(ReviewModerationService::class)->restoreReview(
                            auth()->user(), $record->id, $data['comment']
                        )),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'view' => Pages\ViewReview::route('/{record}'),
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
