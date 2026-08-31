import { Box, Button, Divider, Paper, Skeleton, Typography } from "@mui/material";
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
import PhoneOutlinedIcon from "@mui/icons-material/PhoneOutlined";
import Inventory2OutlinedIcon from "@mui/icons-material/Inventory2Outlined";
import { useNavigate, useParams } from "react-router-dom";
import { DeliveryActions } from "../../components/courier/DeliveryActions";
import { DeliveryPayment } from "../../components/courier/DeliveryPayment";
import { DeliveryRoute } from "../../components/courier/DeliveryRoute";
import { DeliveryStatusChip } from "../../components/courier/DeliveryStatusChip";
import { deliveryWindowLabel } from "../../courier/selectors";
import { useCourier, useCourierDelivery } from "../../courier/useCourier";
import styles from "../../scss/pages/CourierShared.module.scss";

export default function CourierDeliveryPage() {
  const navigate = useNavigate();
  const { deliveryId } = useParams();
  const id = Number(deliveryId);
  const { state, release, start, deliver } = useCourier();
  const { delivery, loading, error } = useCourierDelivery(Number.isFinite(id) ? id : null);

  if (loading) return <Box className={styles.page}><Skeleton height={420} variant="rounded" /></Box>;
  if (error || !delivery) {
    return (
      <Box className={styles.page}>
        <Button startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate(-1)}>Назад</Button>
        <Typography className={styles.errorText}>{error?.message ?? "Доставка не найдена"}</Typography>
      </Box>
    );
  }

  return (
    <Box className={styles.page}>
      <Box className={styles.detailHeader}>
        <Button startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate(-1)}>Назад</Button>
        <DeliveryStatusChip status={delivery.status} label={delivery.status_name} />
      </Box>
      <Box>
        <Typography className={styles.pageSubtitle}>{deliveryWindowLabel(delivery)}</Typography>
        <Typography component="h2" className={styles.detailTitle}>
          {delivery.order_number ?? `Доставка #${delivery.id}`}
        </Typography>
      </Box>

      {/* Paper здесь не «бумажный эффект», а визуальная группа связанных данных.
          elevation=0 убирает тяжёлую тень: разделы отделяются рамкой и воздухом. */}
      <Paper className={styles.detailPaper} elevation={0}>
        <Typography component="h3" className={styles.blockTitle}>Маршрут</Typography>
        <DeliveryRoute delivery={delivery} navigation />
      </Paper>

      {delivery.customer && (
        <Paper className={styles.detailPaper} elevation={0}>
          <Typography component="h3" className={styles.blockTitle}>Получатель</Typography>
          <Typography className={styles.customerName}>{delivery.customer.name ?? "Имя не указано"}</Typography>
          {delivery.customer.phone && (
            <Button
              component="a"
              href={`tel:${delivery.customer.phone}`}
              variant="outlined"
              startIcon={<PhoneOutlinedIcon />}
            >
              {delivery.customer.phone}
            </Button>
          )}
        </Paper>
      )}

      <DeliveryPayment delivery={delivery} />

      <Paper className={styles.detailPaper} elevation={0}>
        <Box className={styles.blockTitleRow}>
          <Inventory2OutlinedIcon />
          <Typography component="h3" className={styles.blockTitle}>Состав</Typography>
        </Box>
        {delivery.items.map((item, index) => (
          <Box key={`${item.product_id}-${index}`}>
            {index > 0 && <Divider />}
            <Box className={styles.itemRow}>
              <Typography>{item.product_name ?? `Товар #${item.product_id}`}</Typography>
              <Typography className={styles.itemQuantity}>{item.quantity} {item.stock_unit}</Typography>
            </Box>
          </Box>
        ))}
      </Paper>

      <DeliveryActions
        delivery={delivery}
        command={state.commands[delivery.id]}
        online={state.online}
        onRelease={(reason) => release(delivery.id, reason)}
        onStart={() => start(delivery.id)}
        onDeliver={() => deliver(delivery.id)}
      />
    </Box>
  );
}
