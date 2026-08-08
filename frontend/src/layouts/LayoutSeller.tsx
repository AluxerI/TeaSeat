import { useEffect } from "react";
import { Link, Outlet, useLocation } from "react-router-dom";
import {
  Badge,
  BottomNavigation,
  BottomNavigationAction,
  Box,
  Button,
  Chip,
  CircularProgress,
  Paper,
  Typography,
} from "@mui/material";
import DashboardIcon from "@mui/icons-material/Dashboard";
import ReceiptIcon from "@mui/icons-material/Receipt";
import AddCircleIcon from "@mui/icons-material/AddCircle";
import SyncIcon from "@mui/icons-material/Sync";
import StorefrontIcon from "@mui/icons-material/Storefront";
import WifiIcon from "@mui/icons-material/Wifi";
import WifiOffIcon from "@mui/icons-material/WifiOff";
import { useSeller } from "../contexts/SellerContext";
import styles from "../scss/pages/SellerLayout.module.scss";

const NAV = [
  { path: "/seller/dashboard", label: "Дашборд", icon: <DashboardIcon /> },
  { path: "/seller/order/new", label: "Новый заказ", icon: <AddCircleIcon />, center: true },
  { path: "/seller/orders", label: "Заказы", icon: <ReceiptIcon /> },
];

export default function LayoutSeller() {
  const { pathname } = useLocation();
  const {
    init,
    ready,
    loading,
    session,
    workLocations,
    selectWarehouse,
    online,
    pendingCount,
    syncAll,
    error,
  } = useSeller();

  useEffect(() => {
    init();
  }, [init]);

  const navValue = NAV.find((n) => pathname.startsWith(n.path))?.path ?? "/seller/dashboard";

  if (loading) {
    return (
      <Box className={styles.centerWrap}>
        <CircularProgress />
        <Typography className={styles.loadingText}>Загрузка рабочей точки...</Typography>
      </Box>
    );
  }

  if (!ready) {
    return (
      <Box className={styles.centerWrap}>
        <Paper className={styles.pickerCard} elevation={0}>
          <StorefrontIcon className={styles.pickerIcon} />
          <Typography component="h1" className={styles.pickerTitle}>
            Выберите рабочую точку
          </Typography>
          {error && <Typography className={styles.pickerError}>{error}</Typography>}
          <Box className={styles.pickerList}>
            {workLocations.map((loc) => (
              <Button
                key={loc.id}
                variant="outlined"
                className={styles.pickerItem}
                onClick={() => selectWarehouse(loc.id)}
              >
                <StorefrontIcon />
                <Box>
                  <Typography className={styles.pickerName}>{loc.name}</Typography>
                  <Typography className={styles.pickerHint}>
                    {loc.city ?? ""} · {loc.type === "store" ? "магазин" : "склад"}
                  </Typography>
                </Box>
              </Button>
            ))}
          </Box>
        </Paper>
      </Box>
    );
  }

  return (
    <Box className={styles.app}>
      <Box className={styles.header}>
        <Box className={styles.headerBrand}>
          <Typography className={styles.brand}>Продажи</Typography>
          <Chip
            size="small"
            icon={<StorefrontIcon />}
            label={session?.warehouse_name ?? "—"}
            className={styles.warehouseChip}
            variant="outlined"
          />
        </Box>
        <Box className={styles.headerRight}>
          <Chip
            size="small"
            icon={online ? <WifiIcon /> : <WifiOffIcon />}
            label={online ? "Онлайн" : "Офлайн"}
            color={online ? "success" : "error"}
            variant="outlined"
            className={styles.onlineChip}
          />
          {pendingCount > 0 && (
            <Button
              size="small"
              variant="contained"
              className={styles.syncBtn}
              startIcon={<SyncIcon />}
              onClick={() => syncAll()}
            >
              {pendingCount}
            </Button>
          )}
        </Box>
      </Box>

      <Box component="main" className={styles.outlet}>
        <Outlet />
      </Box>

      <Paper className={styles.bottomNavWrap} elevation={0}>
        <BottomNavigation value={navValue} className={styles.bottomNav} showLabels>
          {NAV.map((n) => (
            <BottomNavigationAction
              key={n.path}
              value={n.path}
              label={n.label}
              icon={
                n.path === "/seller/orders" ? (
                  <Badge badgeContent={pendingCount} color="error">
                    {n.icon}
                  </Badge>
                ) : (
                  n.icon
                )
              }
              component={Link}
              to={n.path}
            />
          ))}
        </BottomNavigation>
      </Paper>
    </Box>
  );
}
