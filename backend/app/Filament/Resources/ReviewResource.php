<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;
use App\Traits\HasNavigationBadge;

class ReviewResource extends Resource
{
    use HasNavigationBadge; 

    protected static ?string $model = Review::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Модерация';
    protected static ?string $navigationLabel = 'Отзывы';

    protected static ?string $modelLabel = 'Отзыв';

    protected static ?string $pluralModelLabel = 'Отзывы';

    protected static ?string $recordTitleAttribute = 'comment';

    // Указываем ключ для маршрутов
    public static function getRecordRouteKeyName(): ?string
    {
        return 'review_key';
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'product']);
    }

    // Разрешение модели по составному ключу
    public static function resolveRecordRouteBinding(int | string $key): ?Model
    {
        $parts = explode('-', (string) $key);
        if (count($parts) === 2) {
            return Review::where('product_id', $parts[0])
                ->where('user_id', $parts[1])
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Информация об отзыве')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Пользователь')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->email . ')'),
                                
                                Select::make('product_id')
                                    ->label('Товар')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->disabled()
                                    ->dehydrated(false),
                                
                                TextInput::make('rating')
                                    ->label('Оценка')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($state) => $state . ' / 5 ★'),
                                
                                Placeholder::make('rating_stars')
                                    ->label('Рейтинг')
                                    ->content(fn ($record) => $record?->rating_stars ?? '')
                                    ->extraAttributes(['class' => 'text-2xl text-yellow-400']),
                            ]),
                        
                        Textarea::make('comment')
                            ->label('Комментарий')
                            ->rows(5)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('created_at')
                                    ->label('Дата создания')
                                    ->content(fn ($record) => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                                
                                Placeholder::make('updated_at')
                                    ->label('Дата обновления')
                                    ->content(fn ($record) => $record?->updated_at?->format('d.m.Y H:i') ?? '—'),
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
                    ->getStateUsing(fn ($record) => $record->product_id . '-' . $record->user_id)
                    ->sortable(false)
                    ->toggleable(),
                
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                ImageColumn::make('product.main_image_url')
                    ->label('Фото')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-product.jpg')),
                
                TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                
                TextColumn::make('rating')
                    ->label('Оценка')
                    ->formatStateUsing(fn ($state) => str_repeat('★', $state) . str_repeat('☆', 5 - $state))
                    ->extraAttributes(['class' => 'text-yellow-400 font-bold'])
                    ->sortable()
                    ->alignCenter(),
                
                TextColumn::make('comment')
                    ->label('Отзыв')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->comment)
                    ->searchable(),
                
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('rating')
                    ->label('Оценка')
                    ->options([
                        1 => '1 ★',
                        2 => '2 ★',
                        3 => '3 ★',
                        4 => '4 ★',
                        5 => '5 ★',
                    ]),
                
                SelectFilter::make('product')
                    ->label('Товар')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('user')
                    ->label('Пользователь')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('has_comment')
                    ->label('С комментарием')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('comment')->where('comment', '!=', '')),
                
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Review $record): string => route('filament.admin.resources.reviews.view', [
                        'record' => $record->review_key
                    ])),
                
                Tables\Actions\DeleteAction::make(),
                
                Action::make('view_product')
                    ->label('Товар')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Review $record): string => route('filament.admin.resources.products.view', [
                        'record' => $record->product_id
                    ]))
                    ->openUrlInNewTab(),
                
                Action::make('view_user')
                    ->label('Пользователь')
                    ->icon('heroicon-o-user')
                    ->url(fn (Review $record): string => route('filament.admin.resources.users.view', [
                        'record' => $record->user_id
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListReviews::route('/'),
            'view' => Pages\ViewReview::route('/{record}'),
        ];
    }

    // Отключаем создание и редактирование (только просмотр и удаление)
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}