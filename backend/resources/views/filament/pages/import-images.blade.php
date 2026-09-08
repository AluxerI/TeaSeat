<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="parseArchive">
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button type="submit" color="primary">
                    Загрузить и показать предпросмотр
                </x-filament::button>
            </div>
        </form>
        
        @if(!empty($this->previewData))
            <div class="mt-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Предварительный просмотр изображений</h3>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="toggleSelectAll" color="secondary">
                            @if(count($selectedFiles) === count($previewData))
                                Снять все
                            @else
                                Выбрать все
                            @endif
                        </x-filament::button>
                        <x-filament::button wire:click="confirmImport" color="success">
                            Импортировать выбранные ({{ count($selectedFiles) }})
                        </x-filament::button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($previewData as $index => $item)
                    <div class="border rounded-lg p-4 {{ in_array($index, $selectedFiles) ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200' }}">
                        <div class="mb-3 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                            <img
                                src="{{ $item['preview_url'] }}"
                                alt="Предпросмотр: {{ $item['filename'] }}"
                                class="h-40 w-full object-contain"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                        <div class="flex items-start gap-3">
                            <input 
                                type="checkbox" 
                                wire:model="selectedFiles" 
                                value="{{ $index }}"
                                class="mt-1 rounded border-gray-300"
                            >
                            <div class="flex-1">
                                <div class="font-medium break-all">{{ $item['filename'] }}</div>
                                <div class="text-sm text-gray-500">Товар: {{ $item['product_name'] }}</div>
                                <div class="text-xs mt-1 flex flex-wrap gap-1">
                                    @if($item['type'] == 'main')
                                        <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Главное</span>
                                    @elseif($item['type'] == 'background')
                                        <span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded">Фоновое</span>
                                    @else
                                        <span class="bg-gray-100 text-gray-800 px-2 py-0.5 rounded">Не определено</span>
                                    @endif
                                    
                                    @if($item['exists'])
                                        <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded">Товар найден</span>
                                    @else
                                        <span class="bg-red-100 text-red-800 px-2 py-0.5 rounded">Товар не найден</span>
                                    @endif
                                </div>
                                @if(!empty($item['errors']))
                                    <div class="text-red-500 text-xs mt-2">
                                        {{ implode(', ', $item['errors']) }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>