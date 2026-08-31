import { Tab, Tabs } from "@mui/material";
import type { DeliveryKind } from "../../courier/types";
import styles from "../../scss/pages/CourierShared.module.scss";

export type DeliveryKindFilter = DeliveryKind | "all";

const KIND_TABS: Array<{ value: DeliveryKindFilter; label: string }> = [
  { value: "all", label: "Все" },
  { value: "customer", label: "Клиентам" },
  // { value: "transfer", label: "Трансферы" },
];

/**
 * Tabs подходят здесь лучше Select: вариантов всего три, они всегда видны и
 * переключаются одним касанием. MUI также уже даёт управление с клавиатуры и
 * aria-роли. Если появятся дата, склад и сортировка, их лучше вынести в Drawer,
 * а эти три вкладки оставить быстрым фильтром типа доставки.
 */
export function DeliveryFilters({
  value,
  onChange,
}: {
  value: DeliveryKindFilter;
  onChange: (value: DeliveryKindFilter) => void;
}) {
  return (
    <Tabs
      value={value}
      onChange={(_, next) => onChange(next as DeliveryKindFilter)}
      className={styles.filters}
      variant="fullWidth"
      aria-label="Тип доставки"
    >
      {KIND_TABS.map((tab) => (
        <Tab key={tab.value} value={tab.value} label={tab.label} />
      ))}
    </Tabs>
  );
}
