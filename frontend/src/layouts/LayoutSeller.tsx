import { useEffect } from "react";
import { Link, Outlet, useLocation } from "react-router-dom";
import {
  Badge,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  MenuItem,
  Paper,
  Select,
  Typography,
} from "@mui/material";
import DashboardIcon from "@mui/icons-material/Dashboard";
import ReceiptIcon from "@mui/icons-material/Receipt";
import AddCircleIcon from "@mui/icons-material/AddCircle";
import SyncIcon from "@mui/icons-material/Sync";
import StorefrontIcon from "@mui/icons-material/Storefront";
import WifiIcon from "@mui/icons-material/Wifi";
import WifiOffIcon from "@mui/icons-material/WifiOff";
import { ThemeProvider } from "@mui/material/styles";
import { useSeller } from "../contexts/SellerContext";
import { sellerTheme } from "../theme/sellerTheme";
import styles from "../scss/pages/SellerLayout.module.scss";

const NAV = [
  { path: "/seller/dashboard", label: "Дашборд", icon: <DashboardIcon /> },
  { path: "/seller/order/new", label: "Новый заказ", icon: <AddCircleIcon /> },
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
      <ThemeProvider theme={sellerTheme}>
        <Box className={styles.centerWrap}>
          <CircularProgress />
          <Typography className={styles.loadingText}>Загрузка рабочей точки...</Typography>
        </Box>
      </ThemeProvider>
    );
  }

  if (!ready) {
    return (
      <ThemeProvider theme={sellerTheme}>
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
      </ThemeProvider>
    );
  }

  // Боковое меню в стиле личного кабинета: слева, sticky, белая карточка.
  const sidebar = (
    <Box component="aside" className={styles.sidebar}>
      <Typography component="h2" className={styles.sidebarTitle}>
        Продажи
      </Typography>

      <List className={styles.menuList} disablePadding>
        {NAV.map((item) => (
          <ListItemButton
            key={item.path}
            component={Link}
            to={item.path}
            selected={navValue === item.path}
            className={styles.menuItem}
            classes={{ selected: styles.menuItemActive }}
            disableRipple
          >
            <ListItemIcon className={styles.menuIcon}>
              {item.path === "/seller/orders" && pendingCount > 0 ? (
                <Badge badgeContent={pendingCount} color="error">
                  {item.icon}
                </Badge>
              ) : (
                item.icon
              )}
            </ListItemIcon>
            <ListItemText
              primary={item.label}
              primaryTypographyProps={{ className: styles.menuLabel }}
            />
          </ListItemButton>
        ))}
      </List>

      <Divider className={styles.sidebarDivider} />

      <Box className={styles.sidebarMeta}>
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
  );

  return (
    <ThemeProvider theme={sellerTheme}>
      <Box className={styles.app}>
      {/* Шапка: название приложения + статус сети */}
      <Box className={styles.header}>
        <Box className={styles.headerBrand}>
          <Typography className={styles.brand}>Продажи</Typography>
        </Box>
        <Box className={styles.headerRight}>
          <Select
            size="small"
            value={session?.warehouse_id ?? ""}
            displayEmpty
            onChange={(event) => {
              const next = event.target.value ? Number(event.target.value) : null;
              if (next !== null) selectWarehouse(next);
            }}
            className={styles.warehouseSelect}
            aria-label="Рабочая точка"
          >
            {workLocations.map((loc) => (
              <MenuItem key={loc.id} value={loc.id}>{loc.name}</MenuItem>
            ))}
          </Select>
          <Chip
            size="small"
            icon={online ? <WifiIcon /> : <WifiOffIcon />}
            label={online ? "Онлайн" : "Офлайн"}
            color={online ? "success" : "error"}
            variant="outlined"
            className={styles.onlineChip}
          />
        </Box>
      </Box>

      {/* Каркас кабинета: меню слева + контент в белой карточке */}
      <Box className={styles.cabinet}>
        {sidebar}

        <Box component="section" className={styles.content}>
          <Outlet />
        </Box>
      </Box>
      </Box>
    </ThemeProvider>
  );
}
