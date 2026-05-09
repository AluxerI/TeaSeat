{{-- resources/views/filament/pages/import-images-result.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-4">
        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <h3 class="text-lg font-medium mb-2">Результат импорта изображений</h3>
            <p><strong>Импортировано:</strong> {{ $result['imported'] ?? 0 }}</p>
            <p><strong>Не удалось:</strong> {{ $result['failed'] ?? 0 }}</p>
        </div>

        @if(!empty($result['errors']))
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <p class="font-bold mb-2">Ошибки:</p>
                <ul class="list-disc pl-5 space-y-1 max-h-96 overflow-y-auto">
                    @foreach ($result['errors'] as $error)
                        <li class="text-sm">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex gap-3">
            <x-filament::button tag="a" href="{{ route('filament.admin.resources.products.index') }}">
                Вернуться к списку товаров
            </x-filament::button>
            
            <x-filament::button tag="a" href="{{ route('filament.admin.resources.products.import-images') }}" color="secondary">
                Импортировать ещё изображения
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>