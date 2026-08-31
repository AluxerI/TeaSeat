import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import {
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControl,
  InputLabel,
  MenuItem,
  Paper,
  Select,
  TextField,
  Typography,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import PlayArrowIcon from "@mui/icons-material/PlayArrow";
import ReplayIcon from "@mui/icons-material/Replay";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import SupportAgentIcon from "@mui/icons-material/SupportAgent";
import WarningAmberIcon from "@mui/icons-material/WarningAmber";
import RedeemIcon from "@mui/icons-material/Redeem";
import Inventory2Icon from "@mui/icons-material/Inventory2";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import { usePicker } from "../../picker/PickerContext";
import type { PickerOrder, PickerOrderItem } from "../../picker/types";
import styles from "../../scss/pages/PickerOrder.module.scss";

/** Страница деталей заказа: сюда сборщик приходит из очереди и делает всё,
 *  что нужно с конкретным заказом (взять, завершить, сообщить о недостаче...). */
export default function PickerOrderPage() {
  const navigate = useNavigate();
  const { orderId } = useParams<{ orderId: string }>(); // id из адреса /picker/orders/:orderId
  const { loadOrder, take, release, complete, escalate, reportShortage, receive, online } =
    usePicker();

  // Локальные состояния страницы
  const [order, setOrder] = useState<PickerOrder | null>(null); // текущий заказ
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false); // идёт ли операция (гасим кнопки)
  const [shortageOpen, setShortageOpen] = useState(false); // открыт ли диалог недостачи
  const [escalateOpen, setEscalateOpen] = useState(false); // открыт ли диалог эскалации

  // При открытии страницы (или смене id) грузим заказ из контекста.
  useEffect(() => {
    if (!orderId) return;
    loadOrder(Number(orderId))
      .then(setOrder)
      .catch((err: unknown) =>
        setError(err instanceof Error ? err.message : "Не удалось загрузить заказ")
      )
      .finally(() => setLoading(false));
  }, [orderId, loadOrder]);

  // Ответ POST уже содержит новую серверную версию заказа. Используем её сразу:
  // повторный GET после complete/escalate мог бы закономерно вернуть 404,
  // потому что заказ уже вышел из активного picker-scope.
  const run = async (action: () => Promise<PickerOrder>) => {
    setBusy(true);
    try {
      setOrder(await action());
    } catch (err) {
      setError(err instanceof Error ? err.message : "Операция не выполнена");
    } finally {
      setBusy(false);
    }
  };

  // Пока грузится — показываем заглушку.
  if (loading) {
    return (
      <Box className={styles.centerWrap}>
        <Typography className={styles.emptyText}>Загрузка заказа...</Typography>
      </Box>
    );
  }

  // Ошибка и заказ так и не загрузился — показываем сообщение и выход в очередь.
  if (error && !order) {
    return (
      <Box className={styles.centerWrap}>
        <Typography className={styles.emptyText}>{error}</Typography>
        <Button variant="contained" onClick={() => navigate("/picker")}>
          К очереди
        </Button>
      </Box>
    );
  }

  if (!order) return null;

  const from = order.warehouse?.name ?? "—";
  const to = order.destination_warehouse?.name;

  return (
    <Box className={styles.page}>
      {/* Шапка: назад, номер задания, статус */}
      <Box className={styles.header}>
        <Button
          className={styles.backBtn}
          startIcon={<ArrowBackIcon />}
          onClick={() => navigate("/picker")}
        >
          Очередь
        </Button>
        <Typography component="h1" className={styles.pageTitle}>
          {order.order_number}
        </Typography>
        <Chip
          className={styles.statusChip}
          label={order.status_name}
          color={statusColor(order.status)}
        />
      </Box>

      {/* Краткая информация: клиентский заказ, маршрут, кто собирает */}
      <Paper className={styles.infoCard} elevation={0}>
        <Typography className={styles.customerOrder}>
          Клиентский заказ № {order.customer_order_number}
        </Typography>
        <Box className={styles.route}>
          <Inventory2Icon fontSize="small" />
          <Typography className={styles.routeText}>
            {from}
            {to ? (
              <>
                {" "}
                <ArrowForwardIcon fontSize="inherit" /> {to}
              </>
            ) : null}
          </Typography>
        </Box>
        {order.picker && (
          <Typography className={styles.metaText}>
            Собирает: {order.picker.name}
          </Typography>
        )}
      </Paper>

      {/* Комментарий клиента — важная штука для сборщика */}
      {order.customer_notes && (
        <Paper className={styles.noteCard} elevation={0}>
          <Typography className={styles.sectionTitle}>Комментарий клиента</Typography>
          <Typography className={styles.noteText}>{order.customer_notes}</Typography>
        </Paper>
      )}

      {error && <Typography className={styles.errorText}>{error}</Typography>}

      {/* Подарочные наборы: рецептура + схема раскладки */}
      {order.gifts.length > 0 && (
        <Box className={styles.section}>
          <Typography className={styles.sectionTitle}>Подарочные наборы</Typography>
          {order.gifts.map((gift) => (
            <GiftRecipe key={gift.order_gift_id} gift={gift} />
          ))}
        </Box>
      )}

      {/* Обычный состав заказа */}
      <Box className={styles.section}>
        <Typography className={styles.sectionTitle}>Состав</Typography>
        <Paper className={styles.itemsCard} elevation={0}>
          {order.items.map((item) => (
            <ItemLine key={item.order_product_id} item={item} />
          ))}
        </Paper>
      </Box>

      {/* Кнопки действий. Каждая показывается только если сервер разрешил
          (флаг в order.actions), иначе её просто нет. */}
      <Box className={styles.actions}>
        {order.actions.can_take && (
          <Button
            variant="contained"
            startIcon={<PlayArrowIcon />}
            onClick={() => run(() => take(order.id))}
            disabled={busy || !online}
          >
            Взять в сборку
          </Button>
        )}
        {order.actions.can_receive && (
          <Button
            variant="contained"
            startIcon={<CheckCircleIcon />}
            onClick={() => run(() => receive(order.id))}
            disabled={busy || !online}
          >
            Подтвердить получение
          </Button>
        )}
        {order.actions.can_release && (
          <Button
            variant="outlined"
            startIcon={<ReplayIcon />}
            onClick={() => run(() => release(order.id))}
            disabled={busy || !online}
          >
            Вернуть в очередь
          </Button>
        )}
        {order.actions.can_complete && (
          <Button
            variant="contained"
            color="success"
            startIcon={<CheckCircleIcon />}
            onClick={() => run(() => complete(order.id))}
            disabled={busy || !online}
          >
            Завершить сборку
          </Button>
        )}
        {order.actions.can_report_shortage && (
          <Button
            variant="contained"
            color="warning"
            startIcon={<WarningAmberIcon />}
            onClick={() => setShortageOpen(true)}
            disabled={busy || !online}
          >
            Недостача
          </Button>
        )}
        {order.actions.can_escalate && (
          <Button
            variant="outlined"
            color="error"
            startIcon={<SupportAgentIcon />}
            onClick={() => setEscalateOpen(true)}
            disabled={busy || !online}
          >
            Передать менеджеру
          </Button>
        )}
      </Box>

      {/* Диалог недостачи: после успешной отправки закрываем его */}
      <ShortageDialog
        open={shortageOpen}
        order={order}
        onClose={() => setShortageOpen(false)}
        onSubmit={async (payload) => {
          await run(() =>
            reportShortage(order.id, payload).then((result) => result.order)
          );
          setShortageOpen(false);
        }}
      />

      {/* Диалог передачи менеджеру */}
      <EscalateDialog
        open={escalateOpen}
        onClose={() => setEscalateOpen(false)}
        onSubmit={async (comment) => {
          await run(() => escalate(order.id, comment));
          setEscalateOpen(false);
        }}
      />
    </Box>
  );
}

/** Строка «Состав»: название товара, количество + единица, шаг отгрузки.
 *  Если товар входит в подарочный набор — подпись «в подарке ...». */
function ItemLine({ item }: { item: PickerOrderItem }) {
  return (
    <Box className={styles.itemLine}>
      <Box>
        <Typography className={styles.itemName}>{item.product_name}</Typography>
        <Typography className={styles.itemMeta}>
          {item.quantity} {item.stock_unit}
          {item.order_gift_id !== null && item.gift_name
            ? ` · в подарке «${item.gift_name}»`
            : ""}
        </Typography>
      </Box>
      <Chip size="small" label={`${item.sale_step} ${item.stock_unit}/шаг`} variant="outlined" />
    </Box>
  );
}

/** Карточка подарочного набора: что внутри (рецептура) + как раскладывать.
 *  Схема раскладки строится из `gift.layout`: у каждого элемента есть координаты
 *  (position_x, position_y). Находим максимальные координаты → получаем размер
 *  сетки (maxX+1 колонок × maxY+1 строк). Каждая занятая клетка закрашивается,
 *  а повёрнутые позиции помечаются символом «↻». */
function GiftRecipe({ gift }: { gift: PickerOrder["gifts"][number] }) {
  const layout = gift.layout ?? [];
  const bounds = useMemo(() => {
    let maxX = 0;
    let maxY = 0;
    for (const cell of layout) {
      maxX = Math.max(maxX, cell.position_x);
      maxY = Math.max(maxY, cell.position_y);
    }
    return { cols: maxX + 1, rows: maxY + 1 };
  }, [layout]);

  return (
    <Paper className={styles.giftCard} elevation={0}>
      {/* Название набора + сколько таких заказано */}
      <Box className={styles.giftHeader}>
        <RedeemIcon className={styles.giftIcon} />
        <Typography className={styles.giftName}>{gift.name ?? "Подарочный набор"}</Typography>
        <Chip size="small" label={`× ${gift.quantity}`} variant="outlined" />
      </Box>

      <Box className={styles.giftBody}>
        {/* Из чего состоит набор */}
        <Box className={styles.recipe}>
          {gift.components.map((c) => (
            <Box key={c.order_product_id} className={styles.recipeLine}>
              <Typography className={styles.recipeName}>
                {c.product_name ?? `Товар ${c.product_id}`}
              </Typography>
              <Typography className={styles.recipeQty}>
                {c.quantity} {c.stock_unit}
              </Typography>
            </Box>
          ))}
        </Box>

        {/* Схема раскладки: сетка по координатам layout */}
        {layout.length > 0 && (
          <Box className={styles.layoutWrap}>
            <Typography className={styles.layoutHint}>Схема раскладки</Typography>
            <Box
              className={styles.layoutGrid}
              style={{
                gridTemplateColumns: `repeat(${bounds.cols}, 1fr)`,
                gridTemplateRows: `repeat(${bounds.rows}, 1fr)`,
              }}
            >
              {Array.from({ length: bounds.cols * bounds.rows }).map((_, i) => {
                // Клетка i → координаты (x = i % cols, y = floor(i / cols)),
                // ищем в layout позицию с такими координатами.
                const cell = layout.find(
                  (c) =>
                    c.position_x === Math.floor(i % bounds.cols) &&
                    c.position_y === Math.floor(i / bounds.cols)
                );
                return (
                  <Box
                    key={i}
                    className={[
                      styles.layoutCell,
                      cell && styles.layoutCellFilled,
                    ]
                      .filter(Boolean)
                      .join(" ")}
                  >
                    {cell?.is_rotated ? "↻" : ""}
                  </Box>
                );
              })}
            </Box>
          </Box>
        )}
      </Box>
    </Paper>
  );
}

/** Диалог «Недостача». Сборщик выбирает товар, пишет сколько не хватает
 *  и (по желанию) комментарий. Заказ после этого останавливается,
 *  недостачу разбирает менеджер. */
function ShortageDialog({
  open,
  order,
  onClose,
  onSubmit,
}: {
  open: boolean;
  order: PickerOrder;
  onClose: () => void;
  onSubmit: (payload: {
    product_id: number;
    shortage_quantity: number;
    comment?: string;
  }) => Promise<void>;
}) {
  const [productId, setProductId] = useState<number | "">("");
  const [quantity, setQuantity] = useState(1);
  const [comment, setComment] = useState("");
  const [saving, setSaving] = useState(false);

  // При каждом открытии сбрасываем форму к дефолту (первый товар, 1 ед.).
  useEffect(() => {
    if (open) {
      setProductId(order.items[0]?.product_id ?? "");
      setQuantity(1);
      setComment("");
    }
  }, [open, order]);

  // Больше заказанного количества в недостаче заявить нельзя.
  const maxQty =
    order.items.find((i) => i.product_id === productId)?.quantity ?? 1;

  const submit = async () => {
    if (productId === "") return;
    setSaving(true);
    try {
      await onSubmit({
        product_id: Number(productId),
        // clamp количества: минимум 1, максимум — сколько заказано.
        shortage_quantity: Math.max(1, Math.min(Number(quantity) || 1, maxQty)),
        comment: comment.trim() || undefined,
      });
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onClose={onClose}>
      <DialogTitle>Сообщить о недостаче</DialogTitle>
      <DialogContent>
        <DialogContentText>
          Заказ будет остановлен, недостачу разберёт менеджер.
        </DialogContentText>
        <FormControl fullWidth margin="normal" size="small">
          <InputLabel id="shortage-product">Товар</InputLabel>
          <Select
            labelId="shortage-product"
            value={productId}
            onChange={(e) => setProductId(Number(e.target.value))}
            label="Товар"
          >
            {order.items.map((i) => (
              <MenuItem key={i.order_product_id} value={i.product_id}>
                {i.product_name} — {i.quantity} {i.stock_unit}
              </MenuItem>
            ))}
          </Select>
        </FormControl>
        <TextField
          label="Недостача, ед."
          type="number"
          fullWidth
          margin="normal"
          size="small"
          value={quantity}
          inputProps={{ min: 1, max: maxQty }}
          onChange={(e) => setQuantity(Number(e.target.value))}
        />
        <TextField
          label="Комментарий (необязательно)"
          fullWidth
          margin="normal"
          multiline
          minRows={2}
          value={comment}
          onChange={(e) => setComment(e.target.value)}
        />
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>Отмена</Button>
        <Button
          variant="contained"
          color="warning"
          onClick={submit}
          disabled={productId === "" || saving}
        >
          {saving ? "Отправка..." : "Передать менеджеру"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

/** Диалог «Передать менеджеру». Комментарий обязателен — без причины
 *  кнопка отправки недоступна. */
function EscalateDialog({
  open,
  onClose,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  onSubmit: (comment: string) => Promise<void>;
}) {
  const [comment, setComment] = useState("");
  const [saving, setSaving] = useState(false);

  // При открытии диалога чистим поле от прошлого раза.
  useEffect(() => {
    if (open) setComment("");
  }, [open]);

  const submit = async () => {
    if (!comment.trim()) return; // пустой комментарий не отправляем
    setSaving(true);
    try {
      await onSubmit(comment.trim());
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onClose={onClose}>
      <DialogTitle>Передать менеджеру</DialogTitle>
      <DialogContent>
        <DialogContentText>
          Опишите проблему — менеджер получит заказ на проверку.
        </DialogContentText>
        <TextField
          label="Причина"
          fullWidth
          margin="normal"
          multiline
          minRows={3}
          value={comment}
          onChange={(e) => setComment(e.target.value)}
        />
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>Отмена</Button>
        <Button
          variant="contained"
          color="error"
          onClick={submit}
          disabled={!comment.trim() || saving}
        >
          {saving ? "Отправка..." : "Передать"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

// Цвет чипа статуса на странице деталей (тот же маппинг, что и в очереди).
function statusColor(
  status: string
): "default" | "primary" | "success" | "warning" {
  switch (status) {
    case "processing":
      return "warning";
    case "ready_for_delivery":
      return "primary";
    case "shipped":
    case "delivered":
      return "success";
    default:
      return "default";
  }
}
