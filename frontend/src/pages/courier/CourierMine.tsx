import { useCallback, useMemo } from "react";
import { Box, Button, Skeleton, Typography } from "@mui/material";
import RouteOutlinedIcon from "@mui/icons-material/RouteOutlined";
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
import { useNavigate, useSearchParams } from "react-router-dom";
import { CourierEmptyState } from "../../components/courier/CourierEmptyState";
import { DeliveryCard } from "../../components/courier/DeliveryCard";
import {
  DeliveryFilters,
  type DeliveryKindFilter,
} from "../../components/courier/DeliveryFilters";
import { selectMineGroups } from "../../courier/selectors";
import { useCourier } from "../../courier/useCourier";
import { useCourierPolling } from "../../courier/useCourierPolling";
import { usePageVisibility } from "../../courier/usePageVisibility";
import type { CourierDelivery, CourierDeliveryFilters } from "../../courier/types";
import styles from "../../scss/pages/CourierShared.module.scss";

const sections: Array<{
  key: "shipped" | "ready_for_delivery" | "awaiting_receipt";
  title: string;
}> = [
  { key: "shipped", title: "В пути" },
  { key: "ready_for_delivery", title: "Можно забирать" },
  { key: "awaiting_receipt", title: "Ожидают приёмки" },
];

export default function CourierMine() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { state, mine, refreshList, loadNextPage } = useCourier();
  const visible = usePageVisibility();
  const kind = (searchParams.get("kind") as DeliveryKindFilter | null) ?? "all";
  const filters = useMemo<CourierDeliveryFilters>(
    () => ({
      mine: true,
      delivery_kind: kind === "all" ? undefined : kind,
      per_page: 50,
    }),
    [kind],
  );
  const refresh = useCallback(
    () => refreshList("mine", filters),
    [filters, refreshList],
  );
  useCourierPolling({ enabled: state.online && visible, intervalMs: 30_000, refresh });

  const groups = selectMineGroups(mine);
  const listState = state.lists.mine;
  const firstLoad = listState.phase === "loading" && mine.length === 0;
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
          <Typography component="h2" className={styles.pageTitle}>Мои доставки</Typography>
          <Typography className={styles.pageSubtitle}>Активные задания обновляются каждые 30 секунд</Typography>
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
      {/* Двух заглушек достаточно обозначить будущий список и не перегрузить PWA. */}
      {firstLoad && [1, 2].map((key) => (
        <Skeleton key={key} height={210} variant="rounded" animation="wave" />
      ))}
      {!firstLoad && mine.length === 0 && (
        <CourierEmptyState
          icon={<RouteOutlinedIcon fontSize="inherit" />}
          title="Активных доставок нет"
          description="Возьмите свободное задание из очереди."
        />
      )}
      {!firstLoad && mine.length > 0 && sections.map((section) => (
        <MineSection
          key={section.key}
          title={section.title}
          deliveries={groups[section.key]}
          onOpen={(id) => navigate(`/courier/deliveries/${id}`)}
        />
      ))}
      {hasNext && (
        <Button variant="outlined" onClick={() => void loadNextPage("mine", filters)}>
          Показать ещё
        </Button>
      )}
    </Box>
  );
}

function MineSection({
  title,
  deliveries,
  onOpen,
}: {
  title: string;
  deliveries: CourierDelivery[];
  onOpen: (id: number) => void;
}) {
  if (deliveries.length === 0) return null;
  return (
    <Box className={styles.section}>
      <Typography component="h3" className={styles.sectionTitle}>
        {title} · {deliveries.length}
      </Typography>
      <Box className={styles.list}>
        {deliveries.map((delivery) => (
          <DeliveryCard
            key={delivery.id}
            delivery={delivery}
            onOpen={() => onOpen(delivery.id)}
          />
        ))}
      </Box>
    </Box>
  );
}
