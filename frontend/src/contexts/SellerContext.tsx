import { createContext, useContext, useEffect, useState, useCallback, type ReactNode } from "react";
import { api } from "../api/api";
import type { Product } from "../interfaces/catalog";

function stripOrigin(url: string): string {
  return url.replace(/^https?:\/\/[^\/]+/, "");
}

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
  products: OfflineProduct[];
  loadProducts: () => Promise<void>;
  searchProducts: (query: string) => OfflineProduct[];

  cartItems: OfflineCartItem[];
  addToCart: (product: OfflineProduct, quantity?: number) => void;
  updateQty: (productId: number, qty: number) => void;
  removeFromCart: (productId: number) => void;
  clearCart: () => void;

  unsyncedOrders: OfflineOrder[];
  createOfflineOrder: (customerName?: string, customerPhone?: string) => OfflineOrder;
  syncOrder: (orderId: string) => Promise<void>;
  syncAll: () => Promise<void>;
}

export const SellerContext = createContext<SellerContextValue | null>(null);

// ── IndexedDB helpers ─────────────────────────────────────────────────────────

const DB_NAME = "SellerDB";
const DB_VERSION = 1;

function openDB(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains("products"))
        db.createObjectStore("products", { keyPath: "id" });
      if (!db.objectStoreNames.contains("cart"))
        db.createObjectStore("cart", { keyPath: "product_id" });
      if (!db.objectStoreNames.contains("orders"))
        db.createObjectStore("orders", { keyPath: "id" });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

function getAllFromStore<T>(storeName: string): Promise<T[]> {
  return openDB().then((db) => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, "readonly");
      const store = tx.objectStore(storeName);
      const req = store.getAll();
      req.onsuccess = () => { db.close(); resolve(req.result); };
      req.onerror = () => { db.close(); reject(req.error); };
    });
  });
}

function putInStore(storeName: string, data: any): Promise<void> {
  return openDB().then((db) => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, "readwrite");
      const store = tx.objectStore(storeName);
      store.put(data);
      tx.oncomplete = () => { db.close(); resolve(); };
      tx.onerror = () => { db.close(); reject(tx.error); };
    });
  });
}

function deleteFromStore(storeName: string, key: IDBValidKey): Promise<void> {
  return openDB().then((db) => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, "readwrite");
      const store = tx.objectStore(storeName);
      store.delete(key);
      tx.oncomplete = () => { db.close(); resolve(); };
      tx.onerror = () => { db.close(); reject(tx.error); };
    });
  });
}

function clearStore(storeName: string): Promise<void> {
  return openDB().then((db) => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, "readwrite");
      const store = tx.objectStore(storeName);
      store.clear();
      tx.oncomplete = () => { db.close(); resolve(); };
      tx.onerror = () => { db.close(); reject(tx.error); };
    });
  });
}

// ── Provider ──────────────────────────────────────────────────────────────────

export function SellerProvider({ children }: { children: ReactNode }) {
  const [products, setProducts] = useState<OfflineProduct[]>([]);
  const [cartItems, setCartItems] = useState<OfflineCartItem[]>([]);
  const [unsyncedOrders, setUnsyncedOrders] = useState<OfflineOrder[]>([]);

  // Загрузить продукты: сначала API, при ошибке — IndexedDB
  const loadProducts = useCallback(async () => {
    try {
      const res = await api.get<{ data: { products: Product[] } }>("/api/catalog");
      const list: OfflineProduct[] = (res.data.data.products ?? []).map((p) => ({
        id: p.id,
        name: p.name,
        price: p.final_price ?? p.original_price,
        image: p.main_image ? stripOrigin(p.main_image) : null,
        weight_grams: p.weight_grams,
        in_stock: p.total_quantity,
      }));
      setProducts(list);
      // сохраняем в IndexedDB на случай офлайна
      for (const p of list) await putInStore("products", p);
    } catch {
      const cached = await getAllFromStore<OfflineProduct>("products");
      setProducts(cached);
    }
  }, []);

  const searchProducts = useCallback((query: string): OfflineProduct[] => {
    const q = query.toLowerCase();
    return products.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        String(p.price).includes(q)
    );
  }, [products]);

  // Корзина — всегда в IndexedDB
  const refreshCart = useCallback(async () => {
    const items = await getAllFromStore<OfflineCartItem>("cart");
    setCartItems(items);
  }, []);

  const addToCart = useCallback(async (product: OfflineProduct, quantity = 1) => {
    const existing = cartItems.find((i) => i.product_id === product.id);
    const newQty = existing ? existing.quantity + quantity : quantity;
    await putInStore("cart", { product_id: product.id, quantity: newQty, unit_price: product.price });
    await refreshCart();
  }, [cartItems, refreshCart]);

  const updateQty = useCallback(async (productId: number, qty: number) => {
    if (qty <= 0) {
      await deleteFromStore("cart", productId);
    } else {
      const item = cartItems.find((i) => i.product_id === productId);
      if (item) await putInStore("cart", { ...item, quantity: qty });
    }
    await refreshCart();
  }, [cartItems, refreshCart]);

  const removeFromCart = useCallback(async (productId: number) => {
    await deleteFromStore("cart", productId);
    await refreshCart();
  }, [refreshCart]);

  const clearCart = useCallback(async () => {
    await clearStore("cart");
    setCartItems([]);
  }, []);

  // Оффлайн-заказы
  const refreshOrders = useCallback(async () => {
    const orders = await getAllFromStore<OfflineOrder>("orders");
    setUnsyncedOrders(orders.filter((o) => !o.synced));
  }, []);

  const createOfflineOrder = useCallback((customerName?: string, customerPhone?: string): OfflineOrder => {
    const order: OfflineOrder = {
      id: crypto.randomUUID(),
      items: [...cartItems],
      total: cartItems.reduce((sum, i) => sum + i.quantity * i.unit_price, 0),
      customer_name: customerName,
      customer_phone: customerPhone,
      created_at: new Date().toISOString(),
      synced: false,
    };
    putInStore("orders", order).then(() => refreshOrders());
    clearCart();
    return order;
  }, [cartItems, clearCart, refreshOrders]);

  const syncOrder = useCallback(async (orderId: string) => {
    const orders = await getAllFromStore<OfflineOrder>("orders");
    const order = orders.find((o) => o.id === orderId);
    if (!order) return;
    try {
      await api.post("/api/checkout", {
        items: order.items,
        total: order.total,
        customer_name: order.customer_name,
        customer_phone: order.customer_phone,
      });
      order.synced = true;
      await putInStore("orders", order);
      await refreshOrders();
    } catch {
      console.error("Sync failed for order", orderId);
    }
  }, [refreshOrders]);

  const syncAll = useCallback(async () => {
    for (const o of unsyncedOrders) await syncOrder(o.id);
  }, [unsyncedOrders, syncOrder]);

  useEffect(() => { loadProducts(); refreshCart(); refreshOrders(); }, [loadProducts, refreshCart, refreshOrders]);

  return (
    <SellerContext.Provider
      value={{
        products, loadProducts, searchProducts,
        cartItems, addToCart, updateQty, removeFromCart, clearCart,
        unsyncedOrders, createOfflineOrder, syncOrder, syncAll,
      }}
    >
      {children}
    </SellerContext.Provider>
  );
}

export function useSeller(): SellerContextValue {
  const ctx = useContext(SellerContext);
  if (!ctx) throw new Error("useSeller must be used within SellerProvider");
  return ctx;
}
