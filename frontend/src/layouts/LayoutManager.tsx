// Роутинг: Outlet — вложенные страницы, useLocation — текущий URL, useNavigate — переход.
import { Outlet, useLocation, useNavigate } from "react-router-dom";
// MUI-компоненты интерфейса кабинета.
import {
  Alert, // баннер (например, офлайн-предупреждение)
  AppBar, // верхняя панель
  Badge, // бейдж с числом на иконке
  BottomNavigation, // нижняя навигация для мобильных
  BottomNavigationAction, // пункт нижней навигации
  Box, // контейнер-блок
  Container, // ограничитель ширины контента
  Drawer, // боковая панель (сайдбар)
  List, // список навигации
  ListItemButton, // кликабельный пункт списка
  ListItemIcon, // иконка пункта
  ListItemText, // текст пункта
  MenuItem, // пункт выпадающего списка
  Select, // выбор рабочей точки
  Snackbar, // всплывающее уведомление
  Toolbar, // панель внутри AppBar
  Typography, // текст
  useMediaQuery, // адаптивность по ширине экрана
} from "@mui/material";
// Тема и хук темы MUI.
import { ThemeProvider, useTheme } from "@mui/material/styles";
// Иконки навигации кабинета.
import AssignmentRoundedIcon from "@mui/icons-material/AssignmentRounded"; // заказы
import FactCheckRoundedIcon from "@mui/icons-material/FactCheckRounded"; // проблемы
import ForumRoundedIcon from "@mui/icons-material/ForumRounded"; // обращения
import RateReviewRoundedIcon from "@mui/icons-material/RateReviewRounded"; // модерация
import StoreRoundedIcon from "@mui/icons-material/StoreRounded"; // логотип/бренд
import WifiOffRoundedIcon from "@mui/icons-material/WifiOffRounded"; // офлайн
// Общий контекст кабинета менеджера.
import { useManager } from "../contexts/ManagerContext";
// Тема интерфейса (переиспользуем тему продавца).
import { sellerTheme } from "../theme/sellerTheme";
// CSS-модуль стилей макета.
import styles from "../scss/pages/ManagerLayout.module.scss";

// Список разделов кабинета: путь, подпись, иконка и (опционально) ключ счётчика.
const navigation = [
  { path: "/manager/orders", label: "Заказы", icon: <AssignmentRoundedIcon /> }, // раздел заказов
  { path: "/manager/issues", label: "Проблемы", icon: <FactCheckRoundedIcon />, badge: "issues" as const }, // проблемы с бейджем
  { path: "/manager/requests", label: "Обращения", icon: <ForumRoundedIcon />, badge: "requests" as const }, // обращения с бейджем
  { path: "/manager/moderation", label: "Модерация", icon: <RateReviewRoundedIcon /> }, // модерация
];

// Внутренний компонент макета (внутри ThemeProvider).
function ManagerLayoutContent() {
  const theme = useTheme(); // текущая тема
  const desktop = useMediaQuery(theme.breakpoints.up("md")); // экран шире md => десктоп
  const navigate = useNavigate(); // переходы между разделами
  const { pathname } = useLocation(); // текущий путь
  // Состояние из общего контекста кабинета.
  const { online, warehouses, warehouseId, setWarehouseId, counters, notice, dismissNotice } = useManager();
  // Активный раздел: первый, чей путь является префиксом текущего (иначе — заказы).
  const active = navigation.find((item) => pathname.startsWith(item.path)) ?? navigation[0];

  // Навигационный список (используется в сайдбаре на десктопе).
  const navItems = (
    <List className={styles.navList}>
      {navigation.map((item) => (
        <ListItemButton key={item.path} selected={active.path === item.path} onClick={() => navigate(item.path)}>
          <ListItemIcon>
            {/* Если у раздела есть счётчик — показываем бейдж с числом открытых. */}
            {item.badge ? <Badge color="error" badgeContent={counters[item.badge]}>{item.icon}</Badge> : item.icon}
          </ListItemIcon>
          <ListItemText primary={item.label} />
        </ListItemButton>
      ))}
    </List>
  );

  return (
    <Box className={styles.app}>
      {/* Верхняя панель с заголовком и выбором рабочей точки. */}
      <AppBar position="fixed" elevation={0} className={styles.appBar}>
        <Toolbar className={styles.toolbar}>
          <Box className={styles.brandIcon}><StoreRoundedIcon /></Box> {/* иконка бренда */}
          <Box className={styles.heading}>
            <Typography className={styles.eyebrow}>Чайные посиделки · Manager</Typography> {/* подзаголовок */}
            <Typography component="h1" className={styles.title}>{active.label}</Typography> {/* название раздела */}
          </Box>
          {/* Выбор рабочей точки: "" = все мои точки. */}
          <Select
            size="small"
            value={warehouseId ?? ""} // пустая строка, если не выбрано
            displayEmpty // показываем плейсхолдер при пустом значении
            onChange={(event) => setWarehouseId(event.target.value ? Number(event.target.value) : null)} // смена точки
            className={styles.warehouseSelect}
            aria-label="Рабочая точка"
          >
            <MenuItem value="">Все мои точки</MenuItem> {/* вариант "все" */}
            {warehouses.map((warehouse) => <MenuItem key={warehouse.id} value={warehouse.id}>{warehouse.name}</MenuItem>)} {/* точки пользователя */}
          </Select>
        </Toolbar>
      </AppBar>

      {/* На десктопе — постоянный сайдбар с навигацией. */}
      {desktop && <Drawer variant="permanent" className={styles.drawer} classes={{ paper: styles.drawerPaper }}>{navItems}</Drawer>}

      {/* Область контента: предупреждение офлайна + вложенная страница. */}
      <Box className={styles.content}>
        {/* Если нет сети — показываем предупреждение о том, что команды отключены. */}
        {!online && <Alert severity="warning" icon={<WifiOffRoundedIcon />} className={styles.offlineAlert}>Нет сети: старые данные остаются на экране, команды отключены.</Alert>}
        <Container component="main" maxWidth="xl" className={styles.main}><Outlet /></Container> {/* вложенный маршрут */}
      </Box>

      {/* На мобильных — нижняя навигация вместо сайдбара. */}
      {!desktop && (
        <BottomNavigation
          showLabels
          value={active.path} // активный раздел
          onChange={(_, value: string) => navigate(value)} // переход по клику
          className={styles.bottomNav}
        >
          {navigation.map((item) => (
            <BottomNavigationAction
              key={item.path}
              value={item.path}
              label={item.label}
              icon={item.badge ? <Badge color="error" badgeContent={counters[item.badge]}>{item.icon}</Badge> : item.icon} // бейдж счётчика
            />
          ))}
        </BottomNavigation>
      )}

      {/* Snackbar для уведомлений (например, об успехе операции).
          Сообщение внутри — Alert с уровнем важности из состояния. */}
      <Snackbar open={Boolean(notice)} autoHideDuration={6000} onClose={dismissNotice} anchorOrigin={{ vertical: "bottom", horizontal: "center" }}>
        <Alert severity={notice?.severity ?? "success"} onClose={dismissNotice}>{notice?.message}</Alert>
      </Snackbar>
    </Box>
  );
}

// Точка входа макета: оборачивает контент в тему.
export default function LayoutManager() {
  return <ThemeProvider theme={sellerTheme}><ManagerLayoutContent /></ThemeProvider>;
}
