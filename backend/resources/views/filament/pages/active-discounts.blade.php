<div class="space-y-2">
    @foreach($discounts as $discount)
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div>
                <span class="font-medium">{{ $discount->name }}</span>
                <div class="text-sm text-gray-500">
                    Скидка: {{ $discount->value }}%
                    @if($discount->code)
                        <span class="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-xs">Промокод: {{ $discount->code }}</span>
                    @endif
                </div>
                @if($discount->start_date || $discount->end_date)
                    <div class="text-xs text-gray-400">
                        @if($discount->start_date) с {{ $discount->start_date->format('d.m.Y') }} @endif
                        @if($discount->end_date) по {{ $discount->end_date->format('d.m.Y') }} @endif
                    </div>
                @endif
            </div>
            <div class="text-green-600 font-bold text-lg">-{{ $discount->value }}%</div>
        </div>
    @endforeach
</div>