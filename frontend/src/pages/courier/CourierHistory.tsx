import { useEffect, useMemo } from "react";
import { Box, Button, Skeleton, Typography } from "@mui/material";
import HistoryOutlinedIcon from "@mui/icons-material/HistoryOutlined";
import { useNavigate, useSearchParams } from "react-router-dom";
import { CourierEmptyState } from "../../components/courier/CourierEmptyState";
import { DeliveryCard } from "../../components/courier/DeliveryCard";
import {
  DeliveryFilters,
  type DeliveryKindFilter,
} from "../../components/courier/DeliveryFilters";
import { useCourier } from "../../courier/useCourier";
import type { CourierDeliveryFilters } from "../../courier/types";
import styles from "../../scss/pages/CourierShared.module.scss";

export default function CourierHistory() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { state, history, refreshList, loadNextPage } = useCourier();
  const kind = (searchParams.get("kind") as DeliveryKindFilter | null) ?? "all";
  const filters = useMemo<CourierDeliveryFilters>(
    () => ({
      mine: true,
      status: "delivered",
      delivery_kind: kind === "all" ? undefined : kind,
      per_page: 20,
    }),
    [kind],
  );

  // History статична относительно активной работы: загружаем при входе/смене
  // фильтра, но не держим для неё отдельный polling-таймер.
  useEffect(() => {
    if (state.online) void refreshList("history", filters);
  }, [filters, refreshList, state.online]);

  const listState = state.lists.history;
  const firstLoad = listState.phase === "loading" && history.length === 0;
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
          <Typography component="h2" className={styles.pageTitle}>История</Typography>
          <Typography className={styles.pageSubtitle}>Завершённые доставки</Typography>
        </Box>
      </Box>
      <DeliveryFilters value={kind} onChange={changeKind} />
      {listState.error && <Typography className={styles.errorText}>{listState.error.message}</Typography>}
      <Box className={styles.list}>
        {firstLoad && [1, 2].map((key) => (
          <Skeleton key={key} height={190} variant="rounded" animation="wave" />
        ))}
        {!firstLoad && history.length === 0 && (
          <CourierEmptyState
            icon={<HistoryOutlinedIcon fontSize="inherit" />}
            title="История пока пуста"
            description="Здесь появятся завершённые клиентские доставки и трансферы."
          />
        )}
        {history.map((delivery) => (
          <DeliveryCard
            key={delivery.id}
            delivery={delivery}
            onOpen={() => navigate(`/courier/deliveries/${delivery.id}`)}
          />
        ))}
        {hasNext && (
          <Button variant="outlined" onClick={() => void loadNextPage("history", filters)}>
            Показать ещё
          </Button>
        )}
      </Box>
    </Box>
  );
}
