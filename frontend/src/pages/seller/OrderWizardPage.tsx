import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { AnimatePresence, motion } from "framer-motion";
import {
  Box,
  Button,
  CircularProgress,
  Paper,
  TextField,
  Typography,
} from "@mui/material";
import SearchIcon from "@mui/icons-material/Search";
import AddIcon from "@mui/icons-material/Add";
import RemoveIcon from "@mui/icons-material/Remove";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import DeleteSweepIcon from "@mui/icons-material/DeleteSweep";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import LocalAtmIcon from "@mui/icons-material/LocalAtm";
import CreditCardIcon from "@mui/icons-material/CreditCard";
import InventoryIcon from "@mui/icons-material/Inventory";
import { useSeller } from "../../contexts/SellerContext";
import {
  formatMoney,
  formatQuantity,
  previewLineTotal,
  previewOrderTotal,
  unitLabel,
} from "../../seller/quantity";
import type { LocalProduct, PaymentMethod } from "../../seller/types";
import styles from "../../scss/pages/SellerWizard.module.scss";

type Stage = "catalog" | "items" | "payment" | "review";

const STAGES: { id: Stage; label: string }[] = [
  { id: "catalog", label: "Товары" },
  { id: "items", label: "Состав" },
  { id: "payment", label: "Оплата" },
  { id: "review", label: "Оформление" },
];

export default function OrderWizardPage() {
  const navigate = useNavigate();
  const {
    ready,
    loading,
    products,
    draft,
    addItem,
    updateItemQuantity,
    removeItem,
    clearItems,
    updateDraft,
    commitDraft,
    startDraft,
  } = useSeller();

  const [stage, setStage] = useState<Stage>("catalog");
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (ready && !draft) {
      startDraft().catch(() => undefined);
    }
  }, [ready, draft, startDraft]);

  if (loading) {
    return (
      <Box className={styles.centerWrap}>
        <CircularProgress />
      </Box>
    );
  }

  if (!ready) {
    return (
      <Box className={styles.centerWrap}>
        <Typography component="h1" className={styles.pageTitle}>
          Выберите рабочую точку
        </Typography>
        <Button variant="contained" onClick={() => navigate("/seller/dashboard")}>
          К выбору точки
        </Button>
      </Box>
    );
  }

  const total = previewOrderTotal(draft?.items ?? []);
  const stageIndex = STAGES.findIndex((s) => s.id === stage);
  const itemsEmpty = (draft?.items.length ?? 0) === 0;

  const canNext =
    stage === "catalog"
      ? !itemsEmpty
      : stage === "items"
        ? !itemsEmpty
        : stage === "payment"
          ? draft?.payment_method !== null
          : true;

  const goNext = async () => {
    if (!canNext) return;
    if (stage === "review") {
      if (submitting) return;
      setSubmitting(true);
      try {
        await commitDraft();
        navigate("/seller/orders");
      } finally {
        setSubmitting(false);
      }
      return;
    }
    setStage(STAGES[stageIndex + 1].id);
  };

  const goBack = () => {
    if (stage === "catalog") {
      navigate("/seller/dashboard");
      return;
    }
    setStage(STAGES[stageIndex - 1].id);
  };

  return (
    <Box className={styles.page}>
      <Box className={styles.stagesHeader}>
        <Stepper current={stage} />
      </Box>

      <Box className={styles.stageBody}>
        <AnimatePresence mode="wait">
          <motion.div
            key={stage}
            initial={{ opacity: 0, x: 24 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -24 }}
            transition={{ duration: 0.18 }}
          >
            {stage === "catalog" && <CatalogStage />}
            {stage === "items" && <ItemsStage />}
            {stage === "payment" && <PaymentStage />}
            {stage === "review" && <ReviewStage />}
          </motion.div>
        </AnimatePresence>
      </Box>

      <Box className={styles.footer}>
        <Button variant="outlined" onClick={goBack} startIcon={<ArrowBackIcon />}>
          {stage === "catalog" ? "Выйти" : "Назад"}
        </Button>
        <Box className={styles.footerTotal}>
          {!itemsEmpty && (
            <>
              <Typography className={styles.totalLabel}>Итого</Typography>
              <Typography className={styles.totalValue}>
                {formatMoney(total)}
              </Typography>
            </>
          )}
        </Box>
        <Button
          variant="contained"
          className={styles.nextBtn}
          onClick={goNext}
          disabled={!canNext || submitting}
          endIcon={stage === "review" ? <CheckCircleIcon /> : <ArrowForwardIcon />}
        >
          {stage === "review"
            ? submitting
              ? "Оформление..."
              : "Оформить продажу"
            : "Далее"}
        </Button>
      </Box>
    </Box>
  );
}

function Stepper({ current }: { current: Stage }) {
  return (
    <Box className={styles.stepper}>
      {STAGES.map((s, i) => {
        const active = s.id === current;
        const done = i < STAGES.findIndex((x) => x.id === current);
        return (
          <Box key={s.id} className={styles.step}>
            <Box className={[styles.stepDot, active && styles.stepDotActive, done && styles.stepDotDone].filter(Boolean).join(" ")}>
              {done ? <CheckCircleIcon className={styles.stepCheck} /> : i + 1}
            </Box>
            <Typography className={styles.stepLabel}>{s.label}</Typography>
          </Box>
        );
      })}
    </Box>
  );
}

function CatalogStage() {
  const { products, addItem, draft } = useSeller();
  const [query, setQuery] = useState("");
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return products;
    return products.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        formatMoney(p.pricing.unit_price).includes(q)
    );
  }, [products, query]);

  return (
    <Box>
      <TextField
        className={styles.searchField}
        placeholder="Поиск по названию или цене..."
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        fullWidth
        InputProps={{
          startAdornment: <SearchIcon className={styles.searchIcon} />,
        }}
      />
      <Box className={styles.productGrid}>
        {filtered.length === 0 && (
          <Typography className={styles.emptyText}>
            {query ? "Ничего не найдено" : "Каталог пуст"}
          </Typography>
        )}
        {filtered.map((p) => (
          <ProductCard
            key={p.id}
            product={p}
            onAdd={() => addItem(p)}
            qtyInOrder={draft?.items.find((i) => i.product_id === p.id)?.quantity ?? 0}
          />
        ))}
      </Box>
    </Box>
  );
}

function ProductCard({
  product,
  onAdd,
  qtyInOrder,
}: {
  product: LocalProduct;
  onAdd: () => void;
  qtyInOrder: number;
}) {
  const outOfStock =
    qtyInOrder + product.sale_step > product.stock.available_quantity;
  const stepLabel = `${product.sale_step} ${unitLabel(product.stock_unit)}`;
  return (
    <Paper className={styles.productCard} elevation={0}>
      {product.image && (
        <Box className={styles.productImage} sx={{ backgroundImage: `url(${product.image})` }} />
      )}
      <Box className={styles.productBody}>
        <Typography className={styles.productName}>{product.name}</Typography>
        <Typography className={styles.productPrice}>
          {formatMoney(product.pricing.unit_price)}
          {product.price_unit_quantity > 1 && ` / ${product.price_unit_quantity} ${unitLabel(product.stock_unit)}`}
        </Typography>
        <Typography className={styles.productStock}>
          В наличии: {product.stock.available_quantity} {unitLabel(product.stock_unit)}
        </Typography>
        <Box className={styles.productActions}>
          <Button
            className={styles.addBtn}
            startIcon={<AddIcon />}
            onClick={onAdd}
            disabled={outOfStock}
            size="small"
          >
            +{stepLabel}
          </Button>
          {qtyInOrder > 0 && (
            <Typography className={styles.inOrderChip}>
              в заказе: {formatQuantity(qtyInOrder, product.stock_unit)}
            </Typography>
          )}
        </Box>
      </Box>
    </Paper>
  );
}

function ItemsStage() {
  const { draft, updateItemQuantity, removeItem, clearItems, products } =
    useSeller();
  const items = draft?.items ?? [];

  const productById = useMemo(
    () => new Map(products.map((p) => [p.id, p])),
    [products]
  );

  if (items.length === 0) {
    return (
      <Box className={styles.centerWrap}>
        <Typography className={styles.emptyText}>
          В заказе нет позиций — добавьте товары на первом шаге.
        </Typography>
      </Box>
    );
  }

  return (
    <Box>
      <Box className={styles.itemList}>
        {items.map((item) => {
          const product = productById.get(item.product_id);
          const maxQty = product?.stock.available_quantity ?? Number.MAX_SAFE_INTEGER;
          return (
            <Paper key={item.product_id} className={styles.itemCard} elevation={0}>
              <Box className={styles.itemInfo}>
                <Typography className={styles.itemName}>{item.name}</Typography>
                <Typography className={styles.itemPrice}>
                  {formatMoney(item.unit_price)}
                  {item.price_unit_quantity > 1 && ` / ${item.price_unit_quantity} ${unitLabel(item.stock_unit)}`}
                </Typography>
              </Box>
              <Box className={styles.qtyControls}>
                <Button
                  className={styles.qtyBtn}
                  onClick={() =>
                    updateItemQuantity(item.product_id, item.quantity - item.sale_step)
                  }
                  disabled={item.quantity - item.sale_step <= 0}
                >
                  <RemoveIcon fontSize="small" />
                </Button>
                <Typography className={styles.qtyValue}>
                  {formatQuantity(item.quantity, item.stock_unit)}
                </Typography>
                <Button
                  className={styles.qtyBtn}
                  onClick={() =>
                    updateItemQuantity(item.product_id, item.quantity + item.sale_step)
                  }
                  disabled={item.quantity + item.sale_step > maxQty}
                >
                  <AddIcon fontSize="small" />
                </Button>
              </Box>
              <Typography className={styles.itemTotal}>
                {formatMoney(previewLineTotal(item))}
              </Typography>
              <Button
                className={styles.removeBtn}
                onClick={() => removeItem(item.product_id)}
                size="small"
              >
                <DeleteOutlineIcon fontSize="small" />
              </Button>
            </Paper>
          );
        })}
      </Box>
      <Button
        className={styles.clearBtn}
        startIcon={<DeleteSweepIcon />}
        onClick={clearItems}
        color="error"
      >
        Очистить состав
      </Button>
    </Box>
  );
}

function PaymentStage() {
  const { draft, updateDraft } = useSeller();
  const methods: { id: PaymentMethod; label: string; icon: React.ReactNode; hint: string }[] = [
    { id: "cash", label: "Наличные", icon: <LocalAtmIcon />, hint: "Оплата на месте" },
    { id: "card", label: "Карта", icon: <CreditCardIcon />, hint: "Терминал" },
  ];

  return (
    <Box className={styles.paymentWrap}>
      <Box className={styles.paymentMethods}>
        {methods.map((m) => {
          const active = draft?.payment_method === m.id;
          return (
            <Paper
              key={m.id}
              className={[styles.paymentCard, active && styles.paymentCardActive].filter(Boolean).join(" ")}
              onClick={() => updateDraft({ payment_method: m.id })}
            >
              <Box className={styles.paymentIcon}>{m.icon}</Box>
              <Typography className={styles.paymentLabel}>{m.label}</Typography>
              <Typography className={styles.paymentHint}>{m.hint}</Typography>
            </Paper>
          );
        })}
      </Box>
      <TextField
        label="Комментарий клиента (необязательно)"
        value={draft?.customer_note ?? ""}
        onChange={(e) => updateDraft({ customer_note: e.target.value })}
        fullWidth
        multiline
        minRows={2}
      />
    </Box>
  );
}

function ReviewStage() {
  const { draft } = useSeller();
  const items = draft?.items ?? [];
  const total = previewOrderTotal(items);
  const occurredAt = draft?.occurred_at
    ? new Date(draft.occurred_at).toLocaleTimeString("ru-RU", {
        hour: "2-digit",
        minute: "2-digit",
      })
    : "—";

  return (
    <Box className={styles.reviewWrap}>
      <Paper className={styles.reviewCard} elevation={0}>
        <Box className={styles.reviewRow}>
          <InventoryIcon className={styles.reviewIcon} />
          <Typography className={styles.reviewTitle}>Состав заказа</Typography>
        </Box>
        {items.map((item) => (
          <Box key={item.product_id} className={styles.reviewLine}>
            <Typography className={styles.reviewName}>
              {item.name} × {formatQuantity(item.quantity, item.stock_unit)}
            </Typography>
            <Typography className={styles.reviewAmount}>
              {formatMoney(previewLineTotal(item))}
            </Typography>
          </Box>
        ))}
        <Box className={styles.reviewDivider} />
        <Box className={styles.reviewRow}>
          <Typography className={styles.totalLabel}>Итого</Typography>
          <Typography className={styles.totalValue}>{formatMoney(total)}</Typography>
        </Box>
        <Box className={styles.reviewMeta}>
          <Typography className={styles.reviewMetaLine}>
            Способ оплаты: {draft?.payment_method === "cash" ? "наличные" : draft?.payment_method === "card" ? "карта" : "не выбран"}
          </Typography>
          <Typography className={styles.reviewMetaLine}>
            Время продажи: {occurredAt}
          </Typography>
          {draft?.customer_note && (
            <Typography className={styles.reviewMetaLine}>
              Комментарий: {draft.customer_note}
            </Typography>
          )}
        </Box>
      </Paper>
    </Box>
  );
}
