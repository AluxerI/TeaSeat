import { Chip } from "@mui/material";
import type { DeliveryStatus } from "../../courier/types";

const colors: Record<
  DeliveryStatus,
  "default" | "primary" | "success" | "warning" | "info"
> = {
  ready_for_delivery: "primary",
  shipped: "warning",
  awaiting_receipt: "info",
  delivered: "success",
};

export function DeliveryStatusChip({
  status,
  label,
}: {
  status: DeliveryStatus;
  label: string;
}) {
  return <Chip size="small" color={colors[status]} label={label} />;
}
