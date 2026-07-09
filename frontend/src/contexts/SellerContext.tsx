import { createContext, useContext, type ReactNode } from "react";

// ── Types ────────────────────────────────────────────────────────────────────

export interface OfflineProduct {
  id: number;
  name: string;
  price: number;
  image: string | null;
  weight_grams: number;
  in_stock: number;
}

export interface OfflineCartItem {
  product_id: number;
  quantity: number;
  unit_price: number;
}

export interface OfflineOrder {
  id: string;
  items: OfflineCartItem[];
  total: number;
  customer_name?: string;
  customer_phone?: string;
  created_at: string;
  synced: boolean;
}

// ── Context shape ────────────────────────────────────────────────────────────

export interface SellerContextValue {
  // Каталог
  products: OfflineProduct[];
  loadProducts: () => Promise<void>;
  searchProducts: (query: string) => Promise<void>;

  // Оффлайн-корзина
  cartItems: OfflineCartItem[];
  addToCart: (product: OfflineProduct, quantity?: number) => void;
  updateQty: (productId: number, qty: number) => void;
  removeFromCart: (productId: number) => void;
  clearCart: () => void;

  // Оффлайн-заказы
  unsyncedOrders: OfflineOrder[];
  createOfflineOrder: (customerName?: string, customerPhone?: string) => OfflineOrder;
  syncOrder: (orderId: string) => Promise<void>;
  syncAll: () => Promise<void>;
}

export const SellerContext = createContext<SellerContextValue | null>(null);

export function SellerProvider({ children }: { children: ReactNode }) {
  // ⚠️ Реализация будет добавлена позже
  return <SellerContext.Provider value={undefined as any}>{children}</SellerContext.Provider>;
}

export function useSeller(): SellerContextValue {
  const ctx = useContext(SellerContext);
  if (!ctx) throw new Error("useSeller must be used within SellerProvider");
  return ctx;
}
