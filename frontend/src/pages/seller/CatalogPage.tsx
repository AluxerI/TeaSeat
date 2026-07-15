import { useState } from "react";
import {
  Box, Typography, TextField, Button, Paper, InputAdornment,
} from "@mui/material";
import SearchIcon from "@mui/icons-material/Search";
import AddShoppingCartIcon from "@mui/icons-material/AddShoppingCart";
import { useSeller, type OfflineProduct } from "../../contexts/SellerContext";
import styles from "../../scss/pages/SellerCatalog.module.scss";

export default function SellerCatalogPage() {
  const { products, searchProducts, addToCart } = useSeller();
  const [query, setQuery] = useState("");
  const [adding, setAdding] = useState<Set<number>>(new Set());

  const results = query.trim() ? searchProducts(query) : products;

  const handleAdd = async (p: OfflineProduct) => {
    setAdding((prev) => new Set(prev).add(p.id));
    await addToCart(p, 1);
    setAdding((prev) => {
      const next = new Set(prev);
      next.delete(p.id);
      return next;
    });
  };

  return (
    <Box>
      <Typography component="h1" className={styles.pageTitle}>
        Каталог товаров
      </Typography>

      <TextField
        className={styles.searchField}
        placeholder="Поиск по названию или цене..."
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        variant="outlined"
        fullWidth
        InputProps={{
          startAdornment: (
            <InputAdornment position="start">
              <SearchIcon className={styles.searchIcon} />
            </InputAdornment>
          ),
        }}
      />

      <Box className={styles.grid}>
        {results.length === 0 ? (
          <Typography className={styles.empty}>
            {query ? "Ничего не найдено" : "Каталог пуст"}
          </Typography>
        ) : (
          results.map((p) => (
            <Paper key={p.id} className={styles.card} elevation={0}>
              {p.image && (
                <Box
                  className={styles.cardImage}
                  sx={{ backgroundImage: `url(${p.image})` }}
                />
              )}
              <Box className={styles.cardBody}>
                <Typography className={styles.cardName}>{p.name}</Typography>
                <Typography className={styles.cardWeight}>
                  {p.weight_grams} г
                </Typography>
                <Typography className={styles.cardPrice}>
                  {p.price} ₽ / шт.
                </Typography>
                <Typography className={styles.cardStock}>
                  В наличии: {p.in_stock}
                </Typography>
                <Button
                  className={styles.addBtn}
                  startIcon={<AddShoppingCartIcon />}
                  onClick={() => handleAdd(p)}
                  disabled={adding.has(p.id) || p.in_stock <= 0}
                  size="small"
                >
                  {adding.has(p.id) ? "..." : "В корзину"}
                </Button>
              </Box>
            </Paper>
          ))
        )}
      </Box>
    </Box>
  );
}
