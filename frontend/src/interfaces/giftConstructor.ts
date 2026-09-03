import type { StockUnit } from "../types/productMeasurement";

export interface SimpleRequirements {
  tea_count: number;
  sweet_count: number;
  total_items: number;
  allow_duplicate_products: boolean;
}
export interface GiftSizeProfile {
  id: number;
  code: string;
  name: string;
  kind: "item" | "box";
  width_cells: number;
  height_cells: number;
  can_rotate: boolean;
  max_weight_grams: number | null;
  default_markup_amount: number;
  simple_constructor_enabled: boolean;
  simple_requirements: SimpleRequirements | null;
}

export interface ConstructorProduct {
  id: number;
  name: string;
  price: number;
  stock_unit: StockUnit;
  price_unit_quantity: number;
  image: string | null;
  description?: string | null;
  ingredients?: string | null;
  weight_grams?: number | null;
  assembly_instructions?: string | null;
  sold_count?: number;
  sku?: string | null;
  brand?: string | null;
  total_quantity: number;
}

export interface ConstructorPackagingTemplate {
  id: number;
  code: string;
  name: string;
  kind: "pouch" | "wrapper" | "jar" | "other" | string;
  image_url: string;
}

export interface ConstructorProductSize {
  id: number;
  label: string;
  constructor_role: "tea" | "sweet" | "general";
  product_quantity: number;
  packaging_template?: ConstructorPackagingTemplate | null;
  product: ConstructorProduct;
  size: GiftSizeProfile;
}

export interface SimpleConstructorOptions {
  cell_size_mm: number;
  boxes: GiftSizeProfile[];
  tea_product_sizes: ConstructorProductSize[];
  sweet_product_sizes: ConstructorProductSize[];
  selection_rules: {
    counts_source: "box_profile.simple_requirements";
    allow_duplicate_products: boolean;
  };
}

export interface SimpleGiftSelection {
  box_profile_id: number;
  tea_product_size_ids: number[];
  sweet_product_size_ids: number[];
}

export interface SimpleGiftQuoteRequest extends SimpleGiftSelection {
  quantity?: number;
  city?: string;
}

export interface SimpleGiftCreateRequest extends SimpleGiftSelection {
  name: string;
  description?: string | null;
}

export interface PricingQuote {
  currency: "RUB";
  products_total: number;
  gift_markup_total: number;
  promotion_discount: number;
  personal_discount: number;
  cart_discount: number;
  shipping_cost: number;
  shipping_discount: number;
  final_total: number;
}

export interface SimpleGiftQuote {
  valid: true;
  box: GiftSizeProfile;
  layout: Array<Record<string, unknown>>;
  quantity: number;
  required_products: Record<string, number>;
  totals: PricingQuote;
}

export interface Gift {
  id: number;
  name: string;
  description: string | null;
  status: "draft" | "active" | "archived";
  visibility: "private";
  version: number;
  markup_amount: number;
  box: GiftSizeProfile;
  layout: Array<Record<string, unknown>>;
  items: Array<Record<string, unknown>>;
  created_at: string | null;
  updated_at: string | null;
}

export interface AddGiftToCartRequest {
  gift_id: number;
  gift_version: number;
  quantity: number;
  client_instance_id: string;
  city?: string;
}

export interface LayoutPlacement {
  client_item_id: string;
  product_size_id: number;
  position_x: number;
  position_y: number;
  is_rotated: boolean;
}

export interface AdvancedGiftSelection {
  box_profile_id: number;
  items: LayoutPlacement[];
}

export interface AdvancedConstructorOptions {
  cell_size_mm: number;
  boxes: GiftSizeProfile[];
  product_sizes: ConstructorProductSize[];
}

export interface BoxProducts {
  box: GiftSizeProfile;
  product_sizes: ConstructorProductSize[];
}

export type ConstructorDraft =
  | { mode: "simple"; selection: SimpleGiftSelection }
  | { mode: "advanced"; selection: AdvancedGiftSelection };
