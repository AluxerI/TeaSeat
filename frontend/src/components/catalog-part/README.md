# FilterPanel

Компонент панели фильтров каталога.

## Интерфейсы

### FilterState
Состояние всех фильтров:
```typescript
interface FilterState {
  categories: string[];   // ID выбранных категорий
  priceFrom: string;      // Мин. цена (число как строка)
  priceTo: string;        // Макс. цена (число как строка)
  priceRange: string | null; // ID предустановленного диапазона цен
  brands: string[];       // ID выбранных брендов
  rating: number | null;  // Мин. рейтинг (4 или 5)
}
```

### FilterPanelProps
```typescript
interface FilterPanelProps {
  onApply?: (filters: FilterState) => void;  // Колбэк при нажатии "Применить"
  onReset?: () => void;                       // Колбэк при сбросе фильтров
}
```

## Структура данных

### Категории
```typescript
const CATEGORIES: Category[] = [
  { id: "tea",          label: "Чай",              count: 156 },
  { id: "coffee",       label: "Кофе",              count: 89  },
  { id: "desserts",     label: "Десерты",           count: 67  },
  { id: "gift_sets",     label: "Подарочные наборы",  count: 23  },
  { id: "accessories",   label: "Аксессуары",        count: 45  },
];
```

### Диапазоны цен
```typescript
const PRICE_RANGES: PriceRange[] = [
  { id: "0-500",     label: "До 500 ₽"        },
  { id: "500-1500",  label: "500 – 1 500 ₽"   },
  { id: "1500-3000", label: "1 500 – 3 000 ₽" },
  { id: "3000+",     label: "Свыше 3 000 ₽"   },
];
```

### Бренды
```typescript
const BRANDS: Brand[] = [
  { id: "ahmad",      label: "Ahmad Tea"    },
  { id: "jacobs",     label: "Jacobs"       },
  { id: "twinings",   label: "Twinings"     },
  { id: "greenfield", label: "Greenfield"    },
];
```

## Использование

```tsx
import FilterPanel from './components/catalog-part/FilterPanel';

// Базовое использование
<FilterPanel />

// С обработкой фильтров
<FilterPanel 
  onApply={(filters) => {
    console.log(filters);
    // { categories: ["tea"], priceFrom: "", priceTo: "", ... }
  }} 
/>

// Со сбросом
<FilterPanel 
  onReset={() => console.log('Фильтры сброшены')} 
/>
```

## Пример ответа `onApply`

```typescript
{
  categories: ["tea", "coffee"],
  priceFrom: "500",
  priceTo: "",
  priceRange: "500-1500",
  brands: ["ahmad", "twinings"],
  rating: 4
}
```

## Заметки

- При выборе предустановленного диапазона цен поля `priceFrom` и `priceTo` очищаются
- При вводе кастомной цены `priceRange` сбрасывается в `null`
- Рейтинг может быть `4` или `5` (и выше)
- Данные категорий/брендов можно заменить на fetch с бекенда
