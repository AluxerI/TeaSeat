import { useEffect } from "react";
import { Link, Outlet, useLocation } from "react-router-dom";
import {
  BottomNavigation,
  BottomNavigationAction,
  Box,
  Button,
  Chip,
  CircularProgress,
  Paper,
  Typography,
} from "@mui/material";
import QueueIcon from "@mui/icons-material/Queue";
import WorkIcon from "@mui/icons-material/Work";
import SwapHorizIcon from "@mui/icons-material/SwapHoriz";
import StorefrontIcon from "@mui/icons-material/Storefront";
import WifiIcon from "@mui/icons-material/Wifi";
import WifiOffIcon from "@mui/icons-material/WifiOff";
import { usePicker } from "../picker/PickerContext";
import styles from "../scss/pages/PickerLayout.module.scss";

// Пункты нижней навигации. `end: true` — пункт активен только если путь
// совпадает точно (иначе «Очередь» светилась бы и на других страницах).
const NAV = [
  { path: "/picker", label: "Очередь", icon: <QueueIcon />, end: true },
  { path: "/picker/mine", label: "Моя работа", icon: <WorkIcon />, end: true },
  { path: "/picker/transfers", label: "Трансферы", icon: <SwapHorizIcon />, end: true },
];

/** Каркас приложения сборщика (app-shell).
 *  Оборачивает ВСЕ страницы /picker. Тут:
 *  - при старте вызывается init() (загрузка складов),
 *  - пока грузимся или не выбран склад — показываем свои экраны вместо страниц,
 *  - когда всё готово — шапка + содержимое страницы + нижнее меню. */
export default function LayoutPicker() {
  const { pathname } = useLocation();
  const {
    init,
    ready,
    loading,
    warehouseName,
    workLocations,
    selectWarehouse,
    online,
    error,
  } = usePicker();

  // При открытии приложения один раз запускаем загрузку данных.
  useEffect(() => {
    init();
  }, [init]);

  // Какой пункт меню подсветить, по текущему URL.
  const navValue = NAV.find((n) =>
    n.end ? pathname === n.path : pathname.startsWith(n.path)
  )?.path ?? "/picker";

  // 1) Идёт загрузка — просто крутилка.
  if (loading) {
    return (
      <Box className={styles.centerWrap}>
        <CircularProgress />
        <Typography className={styles.loadingText}>Загрузка очереди...</Typography>
      </Box>
    );
  }

  // 2) Склад ещё не выбран — экран выбора склада.
  //    Собирать можно только на «своих» складах из work_locations.
  if (!ready) {
    return (
      <Box className={styles.centerWrap}>
        <Paper className={styles.pickerCard} elevation={0}>
          <StorefrontIcon className={styles.pickerIcon} />
          <Typography component="h1" className={styles.pickerTitle}>
            Выберите склад
          </Typography>
          <Typography className={styles.pickerHint}>
            Сборщик работает только с назначенными точками
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

  // 3) Всё готово — основной экран: шапка + страница + нижнее меню.
  return (
    <Box className={styles.app}>
      {/* Шапка: название приложения, текущий склад, статус сети */}
      <Box className={styles.header}>
        <Box className={styles.headerBrand}>
          <Typography className={styles.brand}>Сборка</Typography>
          <Chip
            size="small"
            icon={<StorefrontIcon />}
            label={warehouseName ?? "—"}
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
        </Box>
      </Box>

      {/* Сюда React Router подставляет текущую страницу (/picker, mine, transfers...) */}
      <Box component="main" className={styles.outlet}>
        <Outlet />
      </Box>

      {/* Нижняя навигация: переключение между разделами */}
      <Paper className={styles.bottomNavWrap} elevation={0}>
        <BottomNavigation value={navValue} className={styles.bottomNav} showLabels>
          {NAV.map((n) => (
            <BottomNavigationAction
              key={n.path}
              value={n.path}
              label={n.label}
              icon={n.icon}
              component={Link}
              to={n.path}
            />
          ))}
        </BottomNavigation>
      </Paper>
    </Box>
  );
}
