import { useNavigate } from "react-router-dom";
import {
  Box, Typography, Button, Paper, Grid,
} from "@mui/material";
import ShoppingCartIcon from "@mui/icons-material/ShoppingCart";
import CloudUploadIcon from "@mui/icons-material/CloudUpload";
import SearchIcon from "@mui/icons-material/Search";
import { useSeller } from "../../contexts/SellerContext";
import styles from "../../scss/pages/SellerDashboard.module.scss";

export default function SellerDashboardPage() {
  const navigate = useNavigate();
  const { cartItems, unsyncedOrders, products } = useSeller();

  return (
    <Box>
      <Typography component="h1" className={styles.pageTitle}>
        Seller Dashboard
      </Typography>

      <Grid container spacing={3}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Paper className={styles.card} elevation={0}>
            <Typography className={styles.cardValue}>{products.length}</Typography>
            <Typography className={styles.cardLabel}>Товаров в каталоге</Typography>
          </Paper>
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Paper className={styles.card} elevation={0}>
            <Typography className={styles.cardValue}>{cartItems.length}</Typography>
            <Typography className={styles.cardLabel}>В корзине</Typography>
          </Paper>
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Paper className={styles.card} elevation={0}>
            <Typography className={styles.cardValue}>{unsyncedOrders.length}</Typography>
            <Typography className={styles.cardLabel}>Не синхронизировано</Typography>
          </Paper>
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Paper className={styles.card} elevation={0}>
            <Typography className={styles.cardValue}>
              {cartItems.reduce((s, i) => s + i.quantity * i.unit_price, 0)} ₽
            </Typography>
            <Typography className={styles.cardLabel}>Сумма корзины</Typography>
          </Paper>
        </Grid>
      </Grid>

      <Box className={styles.actions}>
        <Button
          variant="contained"
          startIcon={<SearchIcon />}
          className={styles.actionBtn}
          onClick={() => navigate("/seller/catalog")}
        >
          Поиск товаров
        </Button>
        <Button
          variant="contained"
          startIcon={<ShoppingCartIcon />}
          className={styles.actionBtn}
          onClick={() => navigate("/seller/cart")}
        >
          Корзина
        </Button>
        {unsyncedOrders.length > 0 && (
          <Button
            variant="contained"
            startIcon={<CloudUploadIcon />}
            className={styles.actionBtn}
            onClick={() => navigate("/seller/orders")}
          >
            Синхронизировать ({unsyncedOrders.length})
          </Button>
        )}
      </Box>
    </Box>
  );
}
