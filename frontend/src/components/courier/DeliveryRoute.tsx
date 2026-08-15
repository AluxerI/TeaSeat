import { Box, Button, Typography } from "@mui/material";
import LocationOnOutlinedIcon from "@mui/icons-material/LocationOnOutlined";
import StorefrontOutlinedIcon from "@mui/icons-material/StorefrontOutlined";
import NavigationOutlinedIcon from "@mui/icons-material/NavigationOutlined";
import type { CourierDelivery } from "../../courier/types";
import { buildYandexMapsUrl, deliveryDestination } from "../../courier/selectors";
import styles from "../../scss/pages/CourierShared.module.scss";

export function DeliveryRoute({
  delivery,
  navigation = false,
}: {
  delivery: CourierDelivery;
  navigation?: boolean;
}) {
  const destination = deliveryDestination(delivery);
  return (
    <Box className={styles.route}>
      <Box className={styles.routePoint}>
        <StorefrontOutlinedIcon className={styles.routeIcon} />
        <Box>
          <Typography className={styles.routeLabel}>Забрать</Typography>
          <Typography className={styles.routeValue}>
            {delivery.pickup?.name ?? "Точка не указана"}
          </Typography>
          {delivery.pickup?.address && (
            <Typography className={styles.routeSecondary}>
              {delivery.pickup.address}
            </Typography>
          )}
        </Box>
      </Box>
      <Box className={styles.routeLine} aria-hidden="true" />
      <Box className={styles.routePoint}>
        <LocationOnOutlinedIcon className={styles.routeIcon} />
        <Box>
          <Typography className={styles.routeLabel}>Доставить</Typography>
          <Typography className={styles.routeValue}>{destination}</Typography>
        </Box>
      </Box>
      {navigation && destination !== "Адрес не указан" && (
        <Button
          component="a"
          href={buildYandexMapsUrl(destination)}
          target="_blank"
          rel="noreferrer"
          variant="outlined"
          startIcon={<NavigationOutlinedIcon />}
          className={styles.navigationButton}
        >
          Открыть в Яндекс Картах
        </Button>
      )}
    </Box>
  );
}
