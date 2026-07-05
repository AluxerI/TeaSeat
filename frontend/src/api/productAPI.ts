import { FormItem, Item, ItemFilters, Responce } from "../interfaces/clients.api";
import { api } from "./api";

type ProductId = number;

export const ItemApi = {
  /** Получить товары с фильтрацией (категория, цена, сортировка) */
  async getItemsByfilter(filter: ItemFilters = {}): Promise<Responce<Item>> {
    const params = new URLSearchParams();
    Object.entries(filter).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        params.append(key, String(value));
      }
    });

    const response = await api.get<Item[]>("/item", { params });
    return response.data as unknown as Responce<Item>;
  },

  /** Получить один товар по ID */
  async getItem(id: ProductId): Promise<Responce<Item>> {
    const response = await api.get<Item>(`/item/${id}`);
    return response.data as unknown as Responce<Item>;
  },

  /** Получить все товары */
  async getAllItem(): Promise<Responce<Item[]>> {
    const response = await api.get<Item[]>(`/item`);
    return response.data as unknown as Responce<Item[]>;
  },

  /** Создать новый товар (FormData) */
  async createItem(body: FormItem): Promise<Item> {
    const bodyObj = new FormData();
    const response = await api.post<Item>("/items", bodyObj, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
    return response.data;
  },
};
