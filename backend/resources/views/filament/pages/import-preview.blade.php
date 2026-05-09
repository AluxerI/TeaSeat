<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="parseFile">
            {{ $this->form }}
            <div class="mt-4">
                <x-filament::button type="submit" color="primary">
                    Загрузить и показать предпросмотр
                </x-filament::button>
            </div>
        </form>
        
        {{-- ПОКАЗЫВАЕМ ОШИБКИ ВАЛИДАЦИИ ФАЙЛА --}}
        @if(session()->has('import_validation_errors'))
            <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-200 mb-2">
                    ⚠️ Ошибки в файле (пропущенные строки)
                </h3>
                <div class="max-h-60 overflow-y-auto">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach(session('import_validation_errors') as $error)
                            <li class="text-sm text-yellow-700 dark:text-yellow-300">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @php session()->forget('import_validation_errors') @endphp
        @endif
        
        @if(!empty($this->previewData))
            <div class="mt-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Предварительный просмотр (валидные строки)</h3>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="toggleSelectAll" color="secondary">
                            @if(count($selectedRows) === count($previewData))
                                Снять все
                            @else
                                Выбрать все
                            @endif
                        </x-filament::button>
                        <x-filament::button wire:click="confirmImport" color="success">
                            Импортировать выбранные ({{ count($selectedRows) }})
                        </x-filament::button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800">
                                <th class="p-2 text-left">Выбрать</th>
                                <th class="p-2 text-left">№</th>
                                <th class="p-2 text-left">Название</th>
                                <th class="p-2 text-left">Цена</th>
                                <th class="p-2 text-left">Бренд</th>
                                <th class="p-2 text-left">Статус</th>
                                <th class="p-2 text-left">Остаток (г)</th>
                                <th class="p-2 text-left">Остаток (шт)</th>
                                <th class="p-2 text-left">Итоговое кол-во (шт)</th>
                              </tr>
                        </thead>
                        <tbody>
                            @foreach($previewData as $index => $item)
                            <tr class="border-t dark:border-gray-700">
                                <td class="p-2">
                                    <input 
                                        type="checkbox" 
                                        wire:model="selectedRows" 
                                        value="{{ $index }}"
                                        class="rounded border-gray-300"
                                    >
                                 </td>
                                <td class="p-2">{{ $item['row'] }}</td>
                                <td class="p-2 font-medium">{{ $item['name'] }}</td>
                                <td class="p-2">{{ $item['price'] }}</td>
                                <td class="p-2">{{ $item['brand'] }}</td>
                                <td class="p-2">
                                    @if(!empty($item['errors']))
                                        <span class="text-red-500 text-xs">{{ implode(', ', $item['errors']) }}</span>
                                    @else
                                        <span class="text-green-500 text-xs">Готов к импорту</span>
                                    @endif
                                 </td>
                                <td class="p-2">{{ $item['stock_weight'] ?? '' }}</td>
                                <td class="p-2">{{ $item['stock_pieces'] ?? '' }}</td>
                                <td class="p-2">
                                    {{ $item['computed_pieces'] ?? 0 }}
                                    @if(!empty($item['remainder_grams']) && $item['remainder_grams'] > 0)
                                        <span class="text-xs text-gray-500 ml-1">(остаток {{ $item['remainder_grams'] }} г)</span>
                                    @endif
                                 </td>
                             </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>