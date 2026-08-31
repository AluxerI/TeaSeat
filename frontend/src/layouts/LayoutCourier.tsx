import { Outlet, useLocation, useNavigate } from "react-router-dom";
import {
  Alert,
  AppBar,
  Badge,
  BottomNavigation,
  BottomNavigationAction,
  Box,
  Chip,
  Container,
  MenuItem,
  Select,
  Snackbar,
  Toolbar,
  Typography,
} from "@mui/material";
import { ThemeProvider } from "@mui/material/styles";
import LocalShippingRoundedIcon from "@mui/icons-material/LocalShippingRounded";
import QueueRoundedIcon from "@mui/icons-material/QueueRounded";
import RouteRoundedIcon from "@mui/icons-material/RouteRounded";
import HistoryRoundedIcon from "@mui/icons-material/HistoryRounded";
import WifiRoundedIcon from "@mui/icons-material/WifiRounded";
import WifiOffRoundedIcon from "@mui/icons-material/WifiOffRounded";
import { useCourier } from "../courier/useCourier";
import { useAuth } from "../hooks/useAuth";
import { sellerTheme } from "../theme/sellerTheme";
import styles from "../scss/pages/CourierLayout.module.scss";

const navigation = [
  { value: "/courier", label: "Очередь", icon: <QueueRoundedIcon /> },
  { value: "/courier/mine", label: "Мои", icon: <RouteRoundedIcon /> },
  { value: "/courier/history", label: "История", icon: <HistoryRoundedIcon /> },
];

const noticeClassBySeverity = {
  success: styles.noticeSuccess,
  info: styles.noticeInfo,
  warning: styles.noticeWarning,
  error: styles.noticeError,
} as const;

/**
 * Layout — общая рамка всех курьерских страниц.
 *
 * Когда курьер переходит между «Очередью», «Моими» и «Историей», React Router
 * подставляет нужную страницу вместо <Outlet />. Шапка, нижнее меню, индикатор
 * сети и Snackbar при этом не создаются заново и выглядят одинаково везде.
 */
export default function LayoutCourier() {
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const { state, mine, dismissNotice, warehouseId, setWarehouseId } = useCourier();
  const { user } = useAuth();
  const workLocations = user?.work_locations ?? [];
  const activePath = pathname.startsWith("/courier/history")
    ? "/courier/history"
    : pathname.startsWith("/courier/mine") || pathname.startsWith("/courier/deliveries")
      ? "/courier/mine"
      : "/courier";

  const title = navigation.find((item) => item.value === activePath)?.label ?? "Доставка";
  const isDeliveryPage = pathname.startsWith("/courier/deliveries/");
  const noticeSeverity = state.notice?.severity ?? "info";

  return (
    <ThemeProvider theme={sellerTheme}>
      <Box className={styles.app}>
        {/* Sticky AppBar — постоянная шапка: бренд, текущий раздел и состояние сети. */}
        <AppBar position="sticky" elevation={0} color="transparent" className={styles.appBar}>
          <Toolbar className={styles.toolbar}>
            <Box className={styles.brandIcon}><LocalShippingRoundedIcon /></Box>
            <Box className={styles.heading}>
              <Typography className={styles.eyebrow}>Чайные посиделки</Typography>
              <Typography component="h1" className={styles.title}>{title}</Typography>
            </Box>
            <Select
              size="small"
              value={warehouseId ?? ""}
              displayEmpty
              onChange={(event) => {
                const next = event.target.value ? Number(event.target.value) : null;
                setWarehouseId(next);
              }}
              className={styles.warehouseSelect}
              aria-label="Точка"
            >
              <MenuItem value="">Все точки</MenuItem>
              {workLocations.map((loc) => (
                <MenuItem key={loc.id} value={loc.id}>{loc.name}</MenuItem>
              ))}
            </Select>
            <Chip
              size="small"
              variant="outlined"
              color={state.online ? "success" : "error"}
              icon={state.online ? <WifiRoundedIcon /> : <WifiOffRoundedIcon />}
              label={state.online ? "Онлайн" : "Офлайн"}
              className={styles.networkChip}
            />
          </Toolbar>
        </AppBar>

        {!state.online && (
          <Alert severity="warning" className={styles.offlineAlert}>
            Данные доступны только для просмотра. Команды включатся после восстановления сети.
          </Alert>
        )}

        <Container component="main" maxWidth="md" className={styles.main}>
          <Outlet />
        </Container>

        <Box className={styles.bottomBar}>
          <BottomNavigation
            showLabels
            value={activePath}
            onChange={(_, value: string) => navigate(value)}
            className={styles.bottomNavigation}
          >
            {navigation.map((item) => (
              <BottomNavigationAction
                key={item.value}
                value={item.value}
                label={item.label}
                icon={
                  item.value === "/courier/mine" && mine.length > 0
                    ? <Badge color="warning" badgeContent={mine.length}>{item.icon}</Badge>
                    : item.icon
                }
              />
            ))}
          </BottomNavigation>
        </Box>

        {/*
          Snackbar сообщает результат действия и сам закрывается. Он находится
          в Layout, поэтому сработает на любой курьерской странице. Фоновое
          обновление списков его не вызывает — иначе polling создавал бы шум.
        */}
        <Snackbar
          open={Boolean(state.notice)}
          autoHideDuration={noticeSeverity === "error" ? 7000 : 5000}
          onClose={(_, reason) => {
            if (reason !== "clickaway") dismissNotice();
          }}
          anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
          className={`${styles.snackbar} ${isDeliveryPage ? styles.snackbarWithActions : ""}`}
        >
          <Alert
            variant="standard"
            severity={noticeSeverity}
            onClose={dismissNotice}
            className={`${styles.snackbarAlert} ${noticeClassBySeverity[noticeSeverity]}`}
          >
            {state.notice?.message}
          </Alert>
        </Snackbar>
      </Box>
    </ThemeProvider>
  );
}
