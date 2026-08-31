import { useEffect } from "react";
import { Link, Outlet, useLocation } from "react-router-dom";
import {
  Alert,
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
import QueueIcon from "@mui/icons-material/Queue";
import WorkIcon from "@mui/icons-material/Work";
import SwapHorizIcon from "@mui/icons-material/SwapHoriz";
import StorefrontIcon from "@mui/icons-material/Storefront";
import WifiIcon from "@mui/icons-material/Wifi";
import WifiOffIcon from "@mui/icons-material/WifiOff";
import { ThemeProvider } from "@mui/material/styles";
import { usePicker } from "../picker/PickerContext";
import { sellerTheme } from "../theme/sellerTheme";
import styles from "../scss/pages/PickerLayout.module.scss";

// Пункты меню. `end: true` — пункт активен только если путь совпадает
// точно (иначе «Очередь» светилась бы и на других страницах).
const NAV = [
  { path: "/picker", label: "Очередь", icon: <QueueIcon />, end: true },
  { path: "/picker/mine", label: "Моя работа", icon: <WorkIcon />, end: true },
  // { path: "/picker/transfers", label: "Трансферы", icon: <SwapHorizIcon />, end: true },
];

/** Каркас приложения сборщика (app-shell).
 *  Оборачивает ВСЕ страницы /picker. Тут:
 *  - при старте вызывается init() (загрузка складов),
 *  - пока грузимся или не выбран склад — показываем свои экраны вместо страниц,
 *  - когда всё готово — шапка + боковое меню + содержимое страницы. */
export default function LayoutPicker() {
  const { pathname } = useLocation();
  const {
    init,
    ready,
    loading,
    warehouseId,
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
      <ThemeProvider theme={sellerTheme}>
        <Box className={styles.centerWrap}>
          <CircularProgress />
          <Typography className={styles.loadingText}>Загрузка очереди...</Typography>
        </Box>
      </ThemeProvider>
    );
  }

  // 2) Склад ещё не выбран — экран выбора склада.
  //    Собирать можно только на «своих» складах из work_locations.
  if (!ready) {
    return (
      <ThemeProvider theme={sellerTheme}>
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
      </ThemeProvider>
    );
  }

  // Боковое меню в стиле личного кабинета: слева, sticky, белая карточка.
  const sidebar = (
    <Box component="aside" className={styles.sidebar}>
      <Typography component="h2" className={styles.sidebarTitle}>
        Сборка
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
            <ListItemIcon className={styles.menuIcon}>{item.icon}</ListItemIcon>
            <ListItemText
              primary={item.label}
              primaryTypographyProps={{ className: styles.menuLabel }}
            />
          </ListItemButton>
        ))}
      </List>

      <Divider className={styles.sidebarDivider} />
    </Box>
  );

  // 3) Всё готово — основной экран: шапка + меню + страница.
  return (
    <ThemeProvider theme={sellerTheme}>
      <Box className={styles.app}>
      {/* Шапка: название приложения + статус сети */}
      <Box className={styles.header}>
        <Box className={styles.headerBrand}>
          <Typography className={styles.brand}>Сборка</Typography>
        </Box>
        <Box className={styles.headerRight}>
          <Select
            size="small"
            value={warehouseId ?? ""}
            displayEmpty
            onChange={(event) => {
              const next = event.target.value ? Number(event.target.value) : null;
              if (next !== null) selectWarehouse(next);
            }}
            className={styles.warehouseSelect}
            aria-label="Склад"
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

      {!online && (
        <Alert severity="warning" className={styles.offlineAlert}>
          Показан последний сохранённый снимок. Изменение статусов доступно только онлайн.
        </Alert>
      )}

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
