import { useNavigate } from "react-router-dom";
import {
  Box,
  Button,
  Chip,
  Paper,
  Typography,
} from "@mui/material";
import AddIcon from "@mui/icons-material/Add";
import ReceiptIcon from "@mui/icons-material/Receipt";
import CloudUploadIcon from "@mui/icons-material/CloudUpload";
import RefreshIcon from "@mui/icons-material/Refresh";
import StorefrontIcon from "@mui/icons-material/Storefront";
import WifiIcon from "@mui/icons-material/Wifi";
import WifiOffIcon from "@mui/icons-material/WifiOff";
import { useSeller } from "../../contexts/SellerContext";
import styles from "../../scss/pages/SellerDashboard.module.scss";

export default function SellerDashboardPage() {
  const navigate = useNavigate();
  const {
    session,
    orders,
    pendingCount,
    ready,
    loading,
    online,
    snapshotExpired,
    workLocations,
    warehouseId,
    selectWarehouse,
    refreshCatalog,
    resetSession,
    syncAll,
  } = useSeller();

  const drafts = orders.filter((o) => o.status === "draft").length;
  const queued = orders.filter((o) => o.status === "queued").length;
  const synced = orders.filter((o) => o.status === "synced").length;

  return (
    <Box className={styles.page}>
      <Box className={styles.hero}>
        <Box className={styles.heroText}>
          <Typography component="h1" className={styles.heroTitle}>
            Продажи
          </Typography>
          <Typography className={styles.heroSubtitle}>
            {session?.warehouse_name ?? "Рабочая точка не выбрана"}
          </Typography>
        </Box>
        <Chip
          icon={online ? <WifiIcon /> : <WifiOffIcon />}
          label={online ? "Онлайн" : "Офлайн"}
          color={online ? "success" : "error"}
          className={styles.onlineChip}
          variant="outlined"
        />
      </Box>

      <Box className={styles.statsRow}>
        <Stat value={orders.length} label="Заказов" />
        <Stat value={drafts} label="Черновиков" />
        <Stat value={queued} label="В очереди" />
        <Stat value={synced} label="На сервере" />
      </Box>

      <Box className={styles.actions}>
        <Button
          variant="contained"
          className={styles.bigAction}
          startIcon={<AddIcon />}
          onClick={() => navigate("/seller/order/new")}
          disabled={!ready}
        >
          Новый заказ
        </Button>
        <Button
          variant="outlined"
          className={styles.bigAction}
          startIcon={<ReceiptIcon />}
          onClick={() => navigate("/seller/orders")}
        >
          Заказы
        </Button>
      </Box>

      {snapshotExpired && (
        <Paper className={styles.warningCard} elevation={0}>
          <Typography className={styles.warningText}>
            Снимок цен устарел. Новые продажи запрещены, пока каталог не обновлён.
          </Typography>
          <Button
            variant="contained"
            startIcon={<RefreshIcon />}
            onClick={() => refreshCatalog()}
            disabled={loading}
          >
            Обновить каталог
          </Button>
        </Paper>
      )}

      {pendingCount > 0 && (
        <Paper className={styles.warningCard} elevation={0}>
          <Typography className={styles.warningText}>
            Несинхронизированных заказов: {pendingCount}
          </Typography>
          <Button
            variant="contained"
            startIcon={<CloudUploadIcon />}
            onClick={() => syncAll()}
          >
            Синхронизировать
          </Button>
        </Paper>
      )}

      <Box className={styles.settings}>
        <Typography className={styles.settingsTitle}>Рабочая точка</Typography>
        <Box className={styles.warehouseChips}>
          {workLocations.map((loc) => (
            <Chip
              key={loc.id}
              icon={<StorefrontIcon />}
              label={loc.name}
              onClick={() => selectWarehouse(loc.id)}
              color={loc.id === warehouseId ? "primary" : "default"}
              variant={loc.id === warehouseId ? "filled" : "outlined"}
            />
          ))}
        </Box>
        <Box className={styles.settingsActions}>
          <Button
            size="small"
            startIcon={<RefreshIcon />}
            onClick={() => refreshCatalog()}
            disabled={loading}
          >
            Обновить каталог
          </Button>
          <Button
            size="small"
            color="error"
            variant="text"
            onClick={() => resetSession()}
          >
            Сменить точку / сбросить
          </Button>
        </Box>
      </Box>
    </Box>
  );
}

function Stat({ value, label }: { value: number; label: string }) {
  return (
    <Paper className={styles.statCard} elevation={0}>
      <Typography className={styles.statValue}>{value}</Typography>
      <Typography className={styles.statLabel}>{label}</Typography>
    </Paper>
  );
}
