export interface ConstructorItem {
  id: number;
  name: string;
  description: string;
  price: number;
  image: string;
  weight_grams: number;
  brand: string;
}

export const MOCK_TEAS: ConstructorItem[] = [
  {
    id: 101,
    name: "Ахмад Чай Зелёный",
    description: "Нежный зелёный чай с мягким травяным вкусом",
    price: 320,
    image: "/pages/catalog/details/tea.svg",
    weight_grams: 100,
    brand: "Ahmad Tea",
  },
  {
    id: 102,
    name: "Чёрный чай Эрл Грей",
    description: "Классический чёрный чай с бергамотом",
    price: 380,
    image: "/pages/catalog/details/tea.svg",
    weight_grams: 100,
    brand: "Ahmad Tea",
  },
  {
    id: 103,
    name: "Чай с жасмином",
    description: "Зелёный чай, ароматизированный жасмином",
    price: 420,
    image: "/pages/catalog/details/tea.svg",
    weight_grams: 120,
    brand: "Майский",
  },
  {
    id: 104,
    name: "Иван-чай",
    description: "Травяной чай из кипрея узколистного",
    price: 290,
    image: "/pages/catalog/details/tea.svg",
    weight_grams: 80,
    brand: "Русский чай",
  },
  {
    id: 105,
    name: "Чай Матча",
    description: "Японский порошковый зелёный чай",
    price: 580,
    image: "/pages/catalog/details/tea.svg",
    weight_grams: 50,
    brand: "Kagayaki",
  },
  {
    id: 106,
    name: "Ромашковый чай",
    description: "Травяной чай из ромашки аптечной",
    price: 240,
    image: "/pages/catalog/details/tea.svg",
    weight_grams: 60,
    brand: "Русский чай",
  },
];

export const MOCK_SWEETS: ConstructorItem[] = [
  {
    id: 201,
    name: "Шоколадный конфитюр",
    description: "Молочный шоколад с начинкой",
    price: 450,
    image: "/pages/catalog/details/swetty.svg",
    weight_grams: 200,
    brand: "Красный Октябрь",
  },
  {
    id: 202,
    name: "Медовик",
    description: "Классический мёдовый торт",
    price: 520,
    image: "/pages/catalog/details/swetty.svg",
    weight_grams: 300,
    brand: "Буше",
  },
  {
    id: 203,
    name: "Печенье Овсяное",
    description: "Хрустящее овсяное печенье с изюмом",
    price: 180,
    image: "/pages/catalog/details/swetty.svg",
    weight_grams: 250,
    brand: "Алечка",
  },
  {
    id: 204,
    name: "Пастила Яблочная",
    description: "Нежная яблочная пастила без сахара",
    price: 310,
    image: "/pages/catalog/details/swetty.svg",
    weight_grams: 150,
    brand: "Сладкоежка",
  },
  {
    id: 205,
    name: "Тёмный шоколад 72%",
    description: "Горький шоколад из какао Бразилии",
    price: 280,
    image: "/pages/catalog/details/swetty.svg",
    weight_grams: 100,
    brand: "Красный Октябрь",
  },
];
