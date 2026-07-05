import type { ReactNode } from "react";

export interface StatusInfo {
  name: string;
  color: string;
}

export interface OrderTotals {
  products_total: number;
  promotion_discount: number;
  personal_discount: number;
  cart_discount: number;
  shipping_cost: number;
  final_total: number;
}

export interface ItemPrices {
  unit_price: number;
  promotion_discount_percent: number;
  personal_discount_percent: number;
  final_unit_price: number;
  total_price: number;
}

export interface ItemDiscounts {
  promotion_discount_amount: number;
  personal_discount_amount: number;
}

export interface OrderProduct {
  id: number;
  name: string;
  price: number;
  image: string | null;
  weight_grams: number;
  category_path: any[];
}

export interface OrderItem {
  id: number;
  product: OrderProduct | null;
  quantity: number;
  prices: ItemPrices;
  discounts: ItemDiscounts;
}

export interface DeliveryMethod {
  id: number;
  name: string;
  description: string;
  cost: number;
  estimated_days: string;
  details?: { min_days: number; max_days: number };
}

export interface OrderAddress {
  id: number;
  user_id: number;
  street: string;
  city: string;
  postal_code: string;
  full_address: string;
}

export interface OrderDelivery {
  method: DeliveryMethod;
  address: OrderAddress;
  tracking_number: string | null;
  warehouse?: { id: number; name: string; city: string } | null;
}

export interface OrderTimestamps {
  created_at: string;
  confirmed_at: string | null;
  paid_at: string | null;
  shipped_at: string | null;
  delivered_at: string | null;
  cancelled_at: string | null;
}

export interface SupplierInfo {
  status: string;
  message: string;
  estimated_processing: string;
}

export interface DeliveryInfo {
  estimated_days: string;
  has_multiple_warehouses: boolean;
  warehouse_count: number;
}

export interface Order {
  id: number;
  status: string;
  payment_method: string;
  order_number: string;
  is_partial: boolean;
  parent_order_id: number | null;
  order_type: string;
  order_type_name: string;
  supplier_info: SupplierInfo | null;
  totals: OrderTotals;
  delivery_info: DeliveryInfo;
  delivery: OrderDelivery;
  items: OrderItem[];
  customer_notes: string | null;
  timestamps: OrderTimestamps;
  status_info: StatusInfo;
}

export type TabKey = "items" | "details";

export interface TabItem {
  key: TabKey;
  label: string;
  icon: ReactNode;
}
