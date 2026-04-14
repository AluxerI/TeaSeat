import { Catalog, Category, Meta, Product } from "../interfaces/catalog";
import { Json } from "../types/utils";
import { api } from "./api";

const catalog_path = `/catalog`;

async function getCatalog(): Promise<Catalog | undefined> {
  
  try {
    const response = await api.get<Catalog>(catalog_path);
    return response.data as Catalog;
  } catch (e: unknown) {
    if (e instanceof Error) {
      console.error(`Error when loading catalog: ${e}`);
    } else {
      
      console.error(`Unknow error`);
    }
    return undefined;
  }
}


async function parseCatalog() {
  let catalog: Catalog | string = (await getCatalog())!;
  catalog = JSON.stringify(catalog);
  const answer: Json = JSON.parse(catalog);
  const ans: Catalog = answer["data"] as Catalog;
  return ans;
}

export const catalogApi = {
  async getProducts(): Promise<Product[]> {
    const ans: Catalog = await parseCatalog();
    return ans["products"] as Product[];
  },
  async getCategory(): Promise<Category[]> {
    const ans: Catalog = await parseCatalog();
    return ans["categories"] as Category[];
  },
  async getMeta(): Promise<Meta> {
    const ans: Catalog = await parseCatalog();
    return ans["meta"] as Meta;
  },

  
};
