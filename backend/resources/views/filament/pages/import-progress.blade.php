<x-filament-panels::page>
    <div class="space-y-6">
        @if($hasError)
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg text-red-600">
                <p class="font-bold">Ошибка</p>
                <p>{{ $errorMessage }}</p>
                <div class="mt-4">
                    <x-filament::button tag="a" href="{{ route('filament.admin.resources.products.index') }}">
                        Вернуться к списку товаров
                    </x-filament::button>
                </div>
            </div>
        @else
            <div class="text-center">
                <h2 class="text-2xl font-bold mb-4">
                    @if($isFinished)
                        Импорт завершён!
                    @else
                        Импорт выполняется...
                    @endif
                </h2>

                <div class="w-full bg-gray-200 rounded-full h-4 mb-4">
                    <div class="bg-primary-600 h-4 rounded-full transition-all duration-500" 
                         style="width: {{ $progress }}%">
                    </div>
                </div>

                <p class="text-lg mb-2">{{ $progress }}%</p>

                @if(!$isFinished)
                    <p class="text-sm text-gray-500 mb-4">
                        Пожалуйста, подождите. Импорт выполняется в фоновом режиме.
                    </p>
                    <div class="mt-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                    </div>
                    <!-- wire:poll вместо meta refresh -->
                    <div wire:poll.2s="checkProgress"></div>
                @else
                    @if($result)
                        <p class="text-sm text-green-600 mb-4">
                            Импорт успешно завершён! Нажмите кнопку для просмотра результатов.
                        </p>
                    @else
                        <p class="text-sm text-yellow-600 mb-4">
                            Импорт завершён, но результат не получен.
                        </p>
                    @endif
                    <x-filament::button wire:click="goToResult" color="success">
                        Посмотреть результат
                    </x-filament::button>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>