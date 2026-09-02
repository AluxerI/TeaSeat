import { Warehouse } from "./warehouse";
import Images from "../utils/Images";
import type { StockUnit } from "../types/productMeasurement";

/** Категория товаров с вложенными подкатегориями */
export interface Category {
  id: number;
  name: string;
  subcategories: {
    id: number;
    name: string;
    category_id: number;
    sub_subcategories: {
      id: number;
      name: string;
      subcategory_id: number;
    }[];
  }[];
}

/** Полная информация о товаре в каталоге */
export interface Product {
  id: number;
  name: string;
  /** Цена без скидки */
  original_price: number;
  /** Цена со скидкой */
  final_price: number;
  ingredients: string;
  description: string;
  main_image: string;
  background_image: string;
  discount: {
    id: number;
    name: string;
    type: string;
    value: number;
    code: string;
  } | null;
  discount_percent: number;
  /** Aggregates only; full reviews are loaded on demand. */
  rating_average?: number | null;
  reviews_count?: number;
  galery: typeof Images[];
  weight_grams: number;
  // Backend задаёт единицу хранения, минимальный шаг продажи и количество,
  // за которое указана цена. Это особенно важно для развесного чая.
  stock_unit: StockUnit;
  sale_step: number;
  price_unit_quantity: number;
  brand: string;
  brand_id: number;
  category_path: {
    category: string;
    subcategory: string;
    sub_subcategory: string;
  };
  /** Остатки по складам */
  inventory: Warehouse[];
  total_quantity: number;
  is_available: boolean;
  sold_count: number;
  created_at: Date;
  update_at: Date;
}

/** Мета-информация о каталоге */
export interface Meta {
  total_products: number;
  has_pagination: boolean;
}

/** Полный ответ каталога (одна ручка /catalog отдаёт всё сразу) */
export interface Catalog {
  categories: Category[];
  products: Product[];
  meta: Meta;
}
