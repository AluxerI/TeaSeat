import { useCallback, useMemo } from "react";
import { Box, Button, Skeleton, Typography } from "@mui/material";
import LocalShippingOutlinedIcon from "@mui/icons-material/LocalShippingOutlined";
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
import { useNavigate, useSearchParams } from "react-router-dom";
import { CourierEmptyState } from "../../components/courier/CourierEmptyState";
import { DeliveryCard } from "../../components/courier/DeliveryCard";
import {
  DeliveryFilters,
  type DeliveryKindFilter,
} from "../../components/courier/DeliveryFilters";
import { selectAvailableDeliveries } from "../../courier/selectors";
import { useCourier } from "../../courier/useCourier";
import { useCourierPolling } from "../../courier/useCourierPolling";
import { usePageVisibility } from "../../courier/usePageVisibility";
import type { CourierDeliveryFilters } from "../../courier/types";
import styles from "../../scss/pages/CourierShared.module.scss";

export default function CourierPWA() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { state, queue, refreshList, loadNextPage, claim } = useCourier();
  const visible = usePageVisibility();
  const kind = (searchParams.get("kind") as DeliveryKindFilter | null) ?? "all";
  const filters = useMemo<CourierDeliveryFilters>(
    () => ({
      status: "ready_for_delivery",
      delivery_kind: kind === "all" ? undefined : kind,
      per_page: 50,
    }),
    [kind],
  );
  const refresh = useCallback(
    () => refreshList("queue", filters),
    [filters, refreshList],
  );

  // Только открытая Queue-страница опрашивает Queue endpoint.
  useCourierPolling({
    enabled: state.online && visible,
    intervalMs: 45_000,
    refresh,
  });

  const listState = state.lists.queue;
  const deliveries = selectAvailableDeliveries(queue);
  const firstLoad = listState.phase === "loading" && deliveries.length === 0;
  const hasNext = Boolean(
    listState.meta && listState.meta.current_page < listState.meta.last_page,
  );

  const changeKind = (next: DeliveryKindFilter) => {
    setSearchParams(next === "all" ? {} : { kind: next });
  };

  return (
    <Box className={styles.page}>
      <Box className={styles.pageHeader}>
        <Box>
          <Typography component="h2" className={styles.pageTitle}>Свободные доставки</Typography>
          <Typography className={styles.pageSubtitle}>Обновляются каждые 45 секунд</Typography>
        </Box>
        <Button
          variant="outlined"
          size="small"
          startIcon={<RefreshRoundedIcon />}
          onClick={() => void refresh()}
          disabled={listState.phase === "loading" || !state.online}
        >
          Обновить
        </Button>
      </Box>

      <DeliveryFilters value={kind} onChange={changeKind} />
      {listState.error && <Typography className={styles.errorText}>{listState.error.message}</Typography>}

      <Box className={styles.list}>
        {/* Skeleton виден только при первом входе. Во время polling старые
            карточки остаются на экране, поэтому интерфейс не мигает. */}
        {firstLoad && [1, 2, 3].map((key) => (
          <Skeleton key={key} height={210} variant="rounded" animation="wave" />
        ))}
        {!firstLoad && deliveries.length === 0 && (
          <CourierEmptyState
            icon={<LocalShippingOutlinedIcon fontSize="inherit" />}
            title="Свободных доставок нет"
            description="Можно оставить PWA открытой — очередь обновится автоматически."
          />
        )}
        {deliveries.map((delivery) => (
          <DeliveryCard
            key={delivery.id}
            delivery={delivery}
            command={state.commands[delivery.id]}
            onOpen={() => navigate(`/courier/deliveries/${delivery.id}`)}
            onClaim={() => claim(delivery.id)}
          />
        ))}
        {hasNext && (
          <Button variant="outlined" onClick={() => void loadNextPage("queue", filters)}>
            Показать ещё
          </Button>
        )}
      </Box>
    </Box>
  );
}
