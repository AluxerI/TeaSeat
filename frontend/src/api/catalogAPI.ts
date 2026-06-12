import { Catalog, Category, Meta, Product } from "../interfaces/catalog";
import { api } from "./api";

const catalog_path = `/catalog`;

async function getCatalog(): Promise<Catalog | undefined> {

  try {
    const response = await api.get<{ data: Catalog }>(catalog_path);
    return response.data.data;
  } catch (e: unknown) {
    if (e instanceof Error) {
      console.error(`Error when loading catalog: ${e}`);
    } else {

      console.error(`Unknown error`);
    }
    return undefined;
  }
}

export const catalogApi = {
  async getProducts(): Promise<Product[]> {
    const ans = await getCatalog();
    return ans?.products ?? [];
  },
  async getCategory(): Promise<Category[]> {
    const ans = await getCatalog();
    return ans?.categories ?? [];
  },
  async getMeta(): Promise<Meta> {
    const ans = await getCatalog();
    return ans?.meta ?? { total_products: 0, has_pagination: false };
  },

};
