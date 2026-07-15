import { createContext, useContext, type ReactNode } from "react";

// ── Types ────────────────────────────────────────────────────────────────────

export interface CourierDelivery {
  id: number;
  order_number: string;
  address: string;
  contact_name: string;
  contact_phone: string;
  items_count: number;
  total: number;
  status: "assigned" | "in_transit" | "delivered" | "issue";
  notes?: string;
}

export type DeliveryStatus = CourierDelivery["status"];

// ── Context shape ────────────────────────────────────────────────────────────

export interface CourierContextValue {
  // Список доставок
  deliveries: CourierDelivery[];
  loadDeliveries: () => Promise<void>;

  // Действия
  startDelivery: (deliveryId: number) => Promise<void>;
  markDelivered: (deliveryId: number) => Promise<void>;
  reportIssue: (deliveryId: number, issue: string) => Promise<void>;

  // Фильтрация
  filterStatus: DeliveryStatus | "all";
  setFilterStatus: (s: DeliveryStatus | "all") => void;
}

export const CourierContext = createContext<CourierContextValue | null>(null);

export function CourierProvider({ children }: { children: ReactNode }) {
  // ⚠️ Реализация будет добавлена позже
  return <CourierContext.Provider value={undefined as any}>{children}</CourierContext.Provider>;
}

export function useCourier(): CourierContextValue {
  const ctx = useContext(CourierContext);
  if (!ctx) throw new Error("useCourier must be used within CourierProvider");
  return ctx;
}
