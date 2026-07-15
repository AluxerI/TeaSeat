import { Link, Outlet, useLocation } from "react-router-dom";
import {
  Box, Typography, List, ListItemButton, ListItemIcon, ListItemText, Divider,
} from "@mui/material";
import DashboardIcon from "@mui/icons-material/Dashboard";
import SearchIcon from "@mui/icons-material/Search";
import ShoppingCartIcon from "@mui/icons-material/ShoppingCart";
import ReceiptIcon from "@mui/icons-material/Receipt";
import { useSeller } from "../contexts/SellerContext";
import styles from "../scss/pages/SellerLayout.module.scss";

const NAV_ITEMS = [
  { path: "/seller/dashboard", label: "Dashboard", icon: <DashboardIcon /> },
  { path: "/seller/catalog", label: "Каталог", icon: <SearchIcon /> },
  { path: "/seller/cart", label: "Корзина", icon: <ShoppingCartIcon /> },
  { path: "/seller/orders", label: "Заказы", icon: <ReceiptIcon /> },
];

export default function LayoutSeller() {
  const { pathname } = useLocation();
  const { cartItems, unsyncedOrders } = useSeller();

  return (
    <Box className={styles.layout}>
      <Box component="aside" className={styles.sidebar}>
        <Typography className={styles.brand}>Продажи</Typography>
        <Divider />
        <List disablePadding>
          {NAV_ITEMS.map(({ path, label, icon }) => (
            <ListItemButton
              key={path}
              component={Link}
              to={path}
              selected={pathname.startsWith(path)}
              className={styles.navItem}
              classes={{ selected: styles.navItemActive }}
            >
              <ListItemIcon className={styles.navIcon}>{icon}</ListItemIcon>
              <ListItemText primary={label} />
            </ListItemButton>
          ))}
        </List>
        <Box className={styles.spacer} />
        <Divider />
        <Box className={styles.stats}>
          <Typography className={styles.statLine}>
            В корзине: {cartItems.length}
          </Typography>
          <Typography className={styles.statLine}>
            Не синхр.: {unsyncedOrders.length}
          </Typography>
        </Box>
      </Box>
      <Box component="main" className={styles.outlet}>
        <Outlet />
      </Box>
    </Box>
  );
}
