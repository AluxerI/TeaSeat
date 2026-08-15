import { Alert, Chip } from "@mui/material";
import type { CourierDelivery } from "../../courier/types";

const money = new Intl.NumberFormat("ru-RU", {
  style: "currency",
  currency: "RUB",
  maximumFractionDigits: 0,
});

export function DeliveryPayment({ delivery }: { delivery: CourierDelivery }) {
  if (!delivery.payment) return null;
  if (delivery.payment.amount_to_collect > 0) {
    return (
      <Alert severity="warning" variant="outlined">
        Получить наличными: {money.format(delivery.payment.amount_to_collect)}
      </Alert>
    );
  }
  return <Chip size="small" color="success" variant="outlined" label="Наличные получать не нужно" />;
}
