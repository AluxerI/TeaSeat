import { createContext, useContext, type ReactNode } from "react";

// ── Types ────────────────────────────────────────────────────────────────────

export interface ConflictingOrder {
  id: number;
  order_number: string;
  problem: string; // "payment_failed" | "out_of_stock" | "dispute"
  customer_name: string;
  total: number;
  created_at: string;
}

export interface CourierInfo {
  id: number;
  name: string;
  phone: string;
  active_deliveries: number;
  total_today: number;
  status: "online" | "offline" | "busy";
}

export interface DashboardStats {
  orders_today: number;
  revenue_today: number;
  conflicts_count: number;
  couriers_online: number;
}

// ── Context shape ────────────────────────────────────────────────────────────

export interface ManagerContextValue {
  // Статистика
  stats: DashboardStats | null;
  loadStats: () => Promise<void>;

  // Конфликтующие заказы
  conflicts: ConflictingOrder[];
  loadConflicts: () => Promise<void>;
  resolveConflict: (orderId: number) => Promise<void>;
  cancelOrder: (orderId: number) => Promise<void>;

  // Курьеры
  couriers: CourierInfo[];
  loadCouriers: () => Promise<void>;
  assignDelivery: (courierId: number, orderId: number) => Promise<void>;
}

export const ManagerContext = createContext<ManagerContextValue | null>(null);

export function ManagerProvider({ children }: { children: ReactNode }) {
  // ⚠️ Реализация будет добавлена позже
  return <ManagerContext.Provider value={undefined as any}>{children}</ManagerContext.Provider>;
}

export function useManager(): ManagerContextValue {
  const ctx = useContext(ManagerContext);
  if (!ctx) throw new Error("useManager must be used within ManagerProvider");
  return ctx;
}
