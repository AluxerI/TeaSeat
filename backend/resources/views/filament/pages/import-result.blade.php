<x-filament-panels::page>
    <div class="space-y-4">
        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <h3 class="text-lg font-medium mb-2">Результат импорта</h3>
            <p><strong>✅ Импортировано новых товаров:</strong> {{ $result['imported'] ?? 0 }}</p>
            <p><strong>🔄 Обновлено существующих товаров:</strong> {{ $result['updated'] ?? 0 }}</p>
            <p><strong>📦 Обновлено остатков:</strong> {{ $result['stock_updated'] ?? 0 }}</p>
            <p><strong>⚠️ Пропущено (дубликаты):</strong> {{ $result['duplicates'] ?? 0 }}</p>
        </div>

        @if(!empty($result['errors']))
            <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                <p class="font-bold mb-2">❌ Ошибки ({{ count($result['errors']) }}):</p>
                <div class="max-h-96 overflow-y-auto">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($result['errors'] as $error)
                            <li class="text-sm text-red-700 dark:text-red-300">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="flex gap-3">
            <x-filament::button tag="a" href="{{ route('filament.admin.resources.products.index') }}">
                Вернуться к списку товаров
            </x-filament::button>
            
            <x-filament::button tag="a" href="{{ route('filament.admin.resources.products.import') }}" color="secondary">
                Импортировать ещё товары
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>