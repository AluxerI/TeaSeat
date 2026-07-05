/** Данные для входа */
export interface LoginData {
  email: string;
  password: string;
}

/** Данные для регистрации */
export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_conf: string;
}

/** Пользователь */
export interface User {
  id: number;
  name: string;
  email: string;
  email_verif_at: string;
  phone_verif: number;
  created_at: Date;
  updated_at: Date;
}

/** Товар (Item) */
export interface Item {
  id: number;
  name: string;
  description: string;
  price: number;
  user_id: number;
  created_at: Date;
  updated_at: Date;
}

/** Форма создания товара */
export interface FormItem {
  name: string;
  description: string;
  price: number;
  user_id: number;
}

/** Стандартный ответ API (обёртка с массивом data) */
export interface Responce<T> {
  data: T[];
}

/** Фильтры для списка товаров */
export interface ItemFilters {
  category_id?: number;
  min_price?: number;
  max_price?: number;
  in_stock?: boolean;
  sort_by?: "name" | "price" | "created_at";
  sort_order?: "asc" | "desc";
}
