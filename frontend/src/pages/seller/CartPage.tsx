import { useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Box, Typography, Button, Paper, TextField, Divider, IconButton,
} from "@mui/material";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import AddShoppingCartIcon from "@mui/icons-material/AddShoppingCart";
import { useSeller } from "../../contexts/SellerContext";
import styles from "../../scss/pages/SellerCart.module.scss";

export default function SellerCartPage() {
  const navigate = useNavigate();
  const { cartItems, products, updateQty, removeFromCart, clearCart, createOfflineOrder } = useSeller();
  const [customerName, setCustomerName] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");
  const [created, setCreated] = useState(false);

  const total = cartItems.reduce((s, i) => s + i.quantity * i.unit_price, 0);

  const handleCreateOrder = () => {
    createOfflineOrder(customerName || undefined, customerPhone || undefined);
    setCreated(true);
    setCustomerName("");
    setCustomerPhone("");
  };

  const productName = (id: number) => products.find((p) => p.id === id)?.name ?? `Товар #${id}`;

  if (created) {
    return (
      <Box className={styles.page}>
        <Typography component="h1" className={styles.pageTitle}>
          Заказ создан
        </Typography>
        <Typography className={styles.successText}>
          Офлайн-заказ сохранён. Не забудьте синхронизировать его при подключении к сети.
        </Typography>
        <Box className={styles.actions}>
          <Button variant="contained" className={styles.actionBtn} onClick={() => { setCreated(false); navigate("/seller/orders"); }}>
            Перейти к заказам
          </Button>
          <Button variant="outlined" className={styles.secondaryBtn} onClick={() => { setCreated(false); }}>
            Продолжить
          </Button>
        </Box>
      </Box>
    );
  }

  if (cartItems.length === 0) {
    return (
      <Box className={styles.page}>
        <Typography component="h1" className={styles.pageTitle}>
          Корзина пуста
        </Typography>
        <Typography className={styles.emptyText}>
          Добавьте товары из каталога продавца.
        </Typography>
        <Button variant="contained" className={styles.actionBtn} onClick={() => navigate("/seller/catalog")}>
          В каталог
        </Button>
      </Box>
    );
  }

  return (
    <Box>
      <Typography component="h1" className={styles.pageTitle}>
        Корзина продавца
      </Typography>

      <Box className={styles.itemList}>
        {cartItems.map((item) => (
          <Paper key={item.product_id} className={styles.itemCard} elevation={0}>
            <Box className={styles.itemInfo}>
              <Typography className={styles.itemName}>{productName(item.product_id)}</Typography>
              <Typography className={styles.itemPrice}>{item.unit_price} ₽ × {item.quantity}</Typography>
            </Box>
            <Box className={styles.qtyControls}>
              <Button className={styles.qtyBtn} onClick={() => updateQty(item.product_id, item.quantity - 1)} disabled={item.quantity <= 1}>−</Button>
              <Typography className={styles.qtyValue}>{item.quantity}</Typography>
              <Button className={styles.qtyBtn} onClick={() => updateQty(item.product_id, item.quantity + 1)}>+</Button>
            </Box>
            <Typography className={styles.itemTotal}>{item.quantity * item.unit_price} ₽</Typography>
            <IconButton className={styles.removeBtn} onClick={() => removeFromCart(item.product_id)} size="small">
              <DeleteOutlineIcon fontSize="small" />
            </IconButton>
          </Paper>
        ))}
      </Box>

      <Divider className={styles.divider} />

      <Box className={styles.totalRow}>
        <Typography className={styles.totalLabel}>Итого:</Typography>
        <Typography className={styles.totalValue}>{total} ₽</Typography>
      </Box>

      <Box className={styles.customerForm}>
        <TextField
          label="Имя клиента (необязательно)"
          value={customerName}
          onChange={(e) => setCustomerName(e.target.value)}
          fullWidth
          variant="outlined"
          size="small"
        />
        <TextField
          label="Телефон клиента (необязательно)"
          value={customerPhone}
          onChange={(e) => setCustomerPhone(e.target.value)}
          fullWidth
          variant="outlined"
          size="small"
        />
      </Box>

      <Box className={styles.actions}>
        <Button variant="contained" className={styles.actionBtn} onClick={handleCreateOrder}>
          Создать офлайн-заказ
        </Button>
        <Button variant="outlined" className={styles.secondaryBtn} onClick={clearCart}>
          Очистить корзину
        </Button>
      </Box>
    </Box>
  );
}
