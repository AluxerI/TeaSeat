import { Warehouse } from "./warehouse";

<<<<<<< HEAD
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
<<<<<<< HEAD
  }[];
=======
=======
export interface Category{
    id:number;
    name:string;
    subcategories:{
        id:number;
        name:string;
        category_id:number;
        sub_subcategories:{
            id:number;
            name:string;
            subcategory_id:number;
        }[]
    }[]
}
export interface Product{
    id:number;
    name:string;
    ingredients:string;
    description:string;
    images:{
        background:string;
        main:string;
    }
    pricing:{
        base_price: string;
        price_with_promotions:number;
        final_price:number;
        promotion_discount:number;
        personal_discount:number;
        has_discount:boolean;
    };
    weight_grams:number;
    brand:string;
    category_path:{
        category:string;
        subcategory:string;
        sub_subcategory:string;
    };
    inventory: Warehouse[];
>>>>>>> a9a77366 (Complete product List)
    total_quantity:number;
    is_available:boolean;
    sold_count:number;
    created_at: Date;
    update_at: Date;
>>>>>>> f092fd80 (Create component - category)
}
export interface Product {
  id: number;
  name: string;
  ingredients: string;
  description: string;
  images: {
    background: string;
    main: string;
  };
  pricing: {
    base_price: string;
    price_with_promotions: number;
    final_price: number;
    promotion_discount: number;
    personal_discount: number;
    has_discount: boolean;
  };
  weight_grams: number;
  brand: string;
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
