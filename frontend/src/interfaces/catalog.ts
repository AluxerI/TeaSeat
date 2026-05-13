import { Warehouse } from "./warehouse";
import Images from "../utils/Images";

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

export interface Product {
  id: number;
  name: string;
  original_price:number;
  final_price:number;
  ingredients: string;
  description: string;
  main_image: string;
  background_image: string;
  discount: {
    id: number;
    name: string;
    type:string;
    value:number;
    code:string;
  };
  discount_percent: number;
  galery:typeof Images[];
  weight_grams: number;
  brand: string;
  brand_id:number;
  category_path: {
    category: string;
    subcategory: string;
    sub_subcategory: string;
  };
  inventory: Warehouse[];
  total_quantity: number;
  is_available: boolean;
  sold_count: number;
  created_at: Date;
  update_at: Date;
}

export interface Meta {
  total_products: number;
  has_pagination: boolean;
}

export interface Catalog {
  categories: Category[];
  products: Product[];
  meta: Meta;
}
