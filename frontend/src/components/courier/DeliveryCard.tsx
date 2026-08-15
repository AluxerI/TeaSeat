import { Box, Button, Chip, Paper, Typography } from "@mui/material";
import ArrowForwardIosRoundedIcon from "@mui/icons-material/ArrowForwardIosRounded";
import LocalShippingOutlinedIcon from "@mui/icons-material/LocalShippingOutlined";
import SwapHorizRoundedIcon from "@mui/icons-material/SwapHorizRounded";
import type { CourierDelivery, DeliveryCommandState } from "../../courier/types";
import {
  deliveryDestination,
  deliveryItemsLabel,
  deliveryWindowLabel,
} from "../../courier/selectors";
import { DeliveryStatusChip } from "./DeliveryStatusChip";
import styles from "../../scss/pages/CourierShared.module.scss";

export function DeliveryCard({
  delivery,
  command,
  onOpen,
  onClaim,
}: {
  delivery: CourierDelivery;
  command?: DeliveryCommandState;
  onOpen: () => void;
  onClaim?: () => Promise<unknown>;
}) {
  const isTransfer = delivery.delivery_kind === "transfer";
  return (
    <Paper className={styles.deliveryCard} elevation={0}>
      <Box
        className={styles.cardBody}
        role="button"
        tabIndex={0}
        onClick={onOpen}
        onKeyDown={(event) => {
          if (event.key === "Enter" || event.key === " ") onOpen();
        }}
      >
        <Box className={styles.cardTopline}>
          <Typography className={styles.windowLabel}>
            {deliveryWindowLabel(delivery)}
          </Typography>
          <Chip
            size="small"
            variant="outlined"
            icon={isTransfer ? <SwapHorizRoundedIcon /> : <LocalShippingOutlinedIcon />}
            label={isTransfer ? "Трансфер" : "Клиенту"}
          />
        </Box>
        <Box className={styles.cardTitleRow}>
          <Typography component="h2" className={styles.orderNumber}>
            {delivery.order_number ?? `Доставка #${delivery.id}`}
          </Typography>
          <ArrowForwardIosRoundedIcon className={styles.openIcon} />
        </Box>
        <Typography className={styles.destination}>{deliveryDestination(delivery)}</Typography>
        <Box className={styles.cardMeta}>
          <DeliveryStatusChip status={delivery.status} label={delivery.status_name} />
          <Typography className={styles.metaText}>{deliveryItemsLabel(delivery)}</Typography>
          {delivery.payment && delivery.payment.amount_to_collect > 0 && (
            <Chip size="small" color="warning" label={`${delivery.payment.amount_to_collect} ₽`} />
          )}
        </Box>
      </Box>
      {onClaim && delivery.actions.can_claim && (
        <Box className={styles.cardActions}>
          <Button
            variant="contained"
            fullWidth
            disabled={command?.phase === "pending"}
            onClick={() => void onClaim().catch(() => undefined)}
          >
            {command?.phase === "pending" ? "Назначаем..." : "Взять доставку"}
          </Button>
        </Box>
      )}
    </Paper>
  );
}
