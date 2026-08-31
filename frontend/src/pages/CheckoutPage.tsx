import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Divider,
  FormControl,
  FormControlLabel,
  FormLabel,
  MenuItem,
  Paper,
  Radio,
  RadioGroup,
  Select,
  TextField,
  Typography,
} from "@mui/material";
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
import LocalShippingOutlinedIcon from "@mui/icons-material/LocalShippingOutlined";
import PaymentsOutlinedIcon from "@mui/icons-material/PaymentsOutlined";

import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { addressApi } from "../api/addressAPI";
import { cartApi } from "../api/cartAPI";
import { useAuth } from "../hooks/useAuth";
import { extractError, translateError } from "../utils/translateError";
import { formatMoney } from "../seller/quantity";
import type {
  Address,
  CartQuote,
  CartSelection,
  DeliveryDate,
  DeliveryMethodOption,
  DeliverySlotsResponse,
  PaymentMethod,
} from "../interfaces/checkout";
import styles from "../scss/pages/CheckoutPage.module.scss";

const SELECTION_KEY = "customer_checkout_selection";

// Табы соответствуют типам способов доставки с backend. Курьер и экспресс
// объединены в один таб, так как у обоих один формат адреса (без индекса).
type DeliveryTab = "pickup" | "external" | "courier";

const TAB_LABELS: Record<DeliveryTab, string> = {
  pickup: "Самовывоз",
  external: "Почта России",
  courier: "Курьер",
};

function tabOfType(type: string | undefined | null): DeliveryTab | null {
  if (type === "pickup") return "pickup";
  if (type === "external") return "external";
  if (type === "courier" || type === "express") return "courier";
  return null;
}

// Корзина передаёт выбранные позиции через state роутера и дублирует их в
// sessionStorage. Второй источник нужен, чтобы обновление checkout не потеряло выбор.
function readSelection(state: unknown): CartSelection | null {
  const routeSelection = (state as { selection?: CartSelection } | null)?.selection;
  const raw = routeSelection ?? (() => {
    try {
      return JSON.parse(sessionStorage.getItem(SELECTION_KEY) ?? "null") as CartSelection | null;
    } catch {
      return null;
    }
  })();
  if (!raw || !Array.isArray(raw.cart_item_ids) || !Array.isArray(raw.cart_gift_ids)) return null;
  return raw.cart_item_ids.length + raw.cart_gift_ids.length > 0 ? raw : null;
}

function newIdempotencyKey(): string {
  // Один ключ соответствует одной версии заказа. Backend использует его,
  // чтобы двойной клик или повтор запрос не создали два одинаковых заказа.
  return globalThis.crypto?.randomUUID?.() ?? `checkout-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export default function CheckoutPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, loading: authLoading } = useAuth();
  const selection = useMemo(() => readSelection(location.state), [location.state]);

  // Справочники с backend зависят друг от друга:
  // адрес -> способы доставки -> итоговый расчёт.
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [addressId, setAddressId] = useState<number | "">("");
  const [methods, setMethods] = useState<DeliveryMethodOption[]>([]);
  const [methodId, setMethodId] = useState<number | "">("");
  const [payment, setPayment] = useState<PaymentMethod>("card");
  const [notes, setNotes] = useState("");
  const [quote, setQuote] = useState<CartQuote | null>(null);

  // Раздельные флаги блокируют только участок с выполняющимся запросом.
  const [loading, setLoading] = useState(true);
  const [methodsLoading, setMethodsLoading] = useState(false);
  const [quoteLoading, setQuoteLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [showAddressForm, setShowAddressForm] = useState(false);
  const [newAddress, setNewAddress] = useState({ city: "", street: "", postal_code: "" });
  const [slotsData, setSlotsData] = useState<DeliverySlotsResponse | null>(null);
  const [slotsLoading, setSlotsLoading] = useState(false);
  const [selectedDate, setSelectedDate] = useState("");
  const [selectedSlotId, setSelectedSlotId] = useState<number | "">("");

  // Ключ повторно используется, пока payload не изменился. Изменённая форма —
  // уже новая операция с новым ключом.
  const operation = useRef<{ signature: string; key: string } | null>(null);

  const loadAddresses = useCallback(async () => {
    setLoading(true);
    try {
      const response = await addressApi.getAddresses();
      setAddresses(response.addresses);
      setAddressId((current) => current || response.addresses[0]?.id || "");
      setShowAddressForm(response.addresses.length === 0);
    } catch (err) {
      setError(translateError(extractError(err)));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // Checkout доступен авторизованному покупателю и только с выбором из корзины.
    if (authLoading) return;
    if (!user) {
      navigate("/login", { replace: true, state: { from: "/checkout" } });
      return;
    }
    if (!selection) {
      navigate("/cart", { replace: true });
      return;
    }
    void loadAddresses();
  }, [authLoading, user, selection, navigate, loadAddresses]);

  useEffect(() => {
    // Backend возвращает методы с учётом адреса и зоны обслуживания.
    if (!addressId) {
      setMethods([]);
      setMethodId("");
      return;
    }
    let active = true;
    setMethodsLoading(true);
    setError("");
    cartApi.getDeliveryMethods(addressId)
      .then((next) => {
        if (!active) return;
        setMethods(next);
        setMethodId((current) => next.some((method) => method.id === current) ? current : (next[0]?.id ?? ""));
      })
      .catch((err) => active && setError(translateError(extractError(err))))
      .finally(() => active && setMethodsLoading(false));
    return () => { active = false; };
  }, [addressId]);

  const selectedMethod = methods.find((method) => method.id === methodId) ?? null;

  // Доступные табы и активный выводятся из методов, которые backend отдал
  // для выбранного адреса: таб не показываем, если способ недоступен в городе.
  const availableTabs = useMemo<DeliveryTab[]>(() => {
    const order: DeliveryTab[] = ["pickup", "external", "courier"];
    const present = new Set<DeliveryTab>();
    methods.forEach((method) => {
      const tab = tabOfType(method.type);
      if (tab) present.add(tab);
    });
    return order.filter((tab) => present.has(tab));
  }, [methods]);

  const activeTab = useMemo<DeliveryTab | null>(() => {
    const type = selectedMethod?.type ?? methods[0]?.type;
    return type ? tabOfType(type) : null;
  }, [methods, selectedMethod]);

  const tabMethods = useMemo(
    () => methods.filter((method) => activeTab === null || tabOfType(method.type) === activeTab),
    [methods, activeTab],
  );

  const selectTab = useCallback((tab: DeliveryTab) => {
    // Самовывоз не спрашивает адрес: для API молча используем первый сохранённый.
    if (tab === "pickup") {
      const first = addresses[0];
      if (first) setAddressId(first.id);
    }
    const method = methods.find((candidate) => tabOfType(candidate.type) === tab);
    if (method) setMethodId(method.id);
  }, [addresses, methods]);

  // Почтовый индекс обязателен только для «Почты России». Для курьера и
  // самовывоза поле необязательно, а при создании адреса backend получает заглушку.
  const postalRequired = activeTab === "external";

  const WEEKDAY_NAMES = ["", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"];
  const MONTH_NAMES = ["янв", "фев", "мар", "апр", "мая", "июн", "июл", "авг", "сен", "окт", "ноя", "дек"];

  const availableSlotsForDate = useMemo(
    () => slotsData?.dates.find((d: DeliveryDate) => d.date === selectedDate)?.slots ?? [],
    [slotsData, selectedDate],
  );

  useEffect(() => {
    // Фронтенд не повторяет скидочные правила и стоимость доставки: итог всегда
    // рассчитывает backend. Задержка объединяет быстрые переключения формы.
    if (!selection || !addressId || !methodId) {
      setQuote(null);
      return;
    }
    let active = true;
    const timer = window.setTimeout(async () => {
      setQuoteLoading(true);
      try {
        const next = await cartApi.quote(selection, { addressId, methodId });
        if (active) setQuote(next);
      } catch (err) {
        if (active) setError(translateError(extractError(err)));
      } finally {
        if (active) setQuoteLoading(false);
      }
    }, 250);
    return () => {
      active = false;
      window.clearTimeout(timer);
    };
  }, [selection, addressId, methodId]);

  useEffect(() => {
    // Интервалы доставки показываем только для курьерских методов.
    if (!addressId || !methodId || !selectedMethod?.requires_scheduling) {
      setSlotsData(null);
      setSelectedDate("");
      setSelectedSlotId("");
      return;
    }
    let active = true;
    setSlotsLoading(true);
    cartApi.getDeliverySlots(addressId, methodId)
      .then((data) => {
        if (!active) return;
        setSlotsData(data);
        setSelectedDate((current) => current || (data.dates[0]?.date ?? ""));
        setSelectedSlotId("");
      })
      .catch((err) => active && setError(translateError(extractError(err))))
      .finally(() => active && setSlotsLoading(false));
    return () => { active = false; };
  }, [addressId, methodId, selectedMethod?.requires_scheduling]);

  const saveAddress = async () => {
    // Клиентская проверка даёт быстрый ответ, окончательную валидацию выполняет backend.
    const city = newAddress.city.trim();
    const street = newAddress.street.trim();
    const postal = newAddress.postal_code.trim();
    if (!city || !street || (postalRequired && !postal)) {
      setError(postalRequired ? "Заполните город, улицу и индекс" : "Заполните город и улицу");
      return;
    }
    setSubmitting(true);
    setError("");
    try {
      const response = await addressApi.createAddress({
        city,
        street,
        postal_code: postal || "000000",
      });
      setAddresses((current) => [...current, response.address]);
      setAddressId(response.address.id);
      setShowAddressForm(false);
    } catch (err) {
      setError(translateError(extractError(err)));
    } finally {
      setSubmitting(false);
    }
  };

  const submit = async () => {
    if (!selection || !addressId || !methodId || !selectedMethod) return;
    const payload = {
      ...selection,
      shipping_address_id: addressId,
      delivery_method_id: methodId,
      payment_method: payment,
      ...(notes.trim() ? { customer_notes: notes.trim() } : {}),
      ...(selectedMethod.requires_scheduling
        ? { scheduled_delivery_date: selectedDate, delivery_time_slot_id: Number(selectedSlotId) }
        : {}),
    };

    // Сигнатура связывает ключ идемпотентности с точным содержимым запроса.
    const signature = JSON.stringify(payload);
    if (operation.current?.signature !== signature) {
      operation.current = { signature, key: newIdempotencyKey() };
    }
    setSubmitting(true);
    setError("");
    try {
      const order = await cartApi.checkout(payload, operation.current.key);
      sessionStorage.removeItem(SELECTION_KEY);
      navigate(`/order/${order.id}`, { replace: true, state: { justCreated: true } });
    } catch (err) {
      setError(translateError(extractError(err)));
    } finally {
      setSubmitting(false);
    }
  };

  if (loading || authLoading) {
    return <Box className={styles.page}><Header /><Box className={styles.center}><CircularProgress /></Box><Footer /></Box>;
  }

  return (
    <Box className={styles.page}>
      <Header />
      <Box className={styles.checkoutPage}>
        <Button className={styles.backButton} startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate("/cart")}>Вернуться в корзину</Button>
        <Typography component="h1" className={styles.pageTitle}>Оформление заказа</Typography>
        {error && <Alert severity="error" className={styles.alert} onClose={() => setError("")}>{error}</Alert>}

        <Box className={styles.layout}>
          {/* Основная колонка — последовательность зависимых шагов. */}
          <Box component="main" className={styles.steps}>
            <Paper className={styles.stepCard} elevation={0}>
              <Box className={styles.stepHeading}><LocalShippingOutlinedIcon /><Box><Typography component="h2">Доставка</Typography><Typography>Выберите способ получения заказа</Typography></Box></Box>
              {/* Самовывоз: адрес не спрашиваем, для API молча берём первый сохранённый. */}
              {activeTab === "pickup" && addresses.length > 0 && <Alert severity="info" className={styles.pickupHint}>Самовывоз из пункта выдачи. Ближайший адрес уточните при получении.</Alert>}

              {/* Почта и курьер спрашивают адрес; без сохранённых адресов форма
                  нужна и для самовывоза, иначе оформление невозможно. */}
              {(activeTab === "external" || activeTab === "courier" || addresses.length === 0) && <>
                {addresses.length > 0 && !showAddressForm ? <>
                  <Select fullWidth value={addressId} onChange={(event) => setAddressId(Number(event.target.value))} aria-label="Адрес доставки">
                    {addresses.map((address) => <MenuItem key={address.id} value={address.id}>{address.full_address}</MenuItem>)}
                  </Select>
                  <Button className={styles.textButton} onClick={() => setShowAddressForm(true)}>Добавить другой адрес</Button>
                </> : <Box className={styles.addressForm}>
                  <TextField label="Город" value={newAddress.city} onChange={(event) => setNewAddress((current) => ({ ...current, city: event.target.value }))} />
                  <TextField label="Улица, дом, квартира" value={newAddress.street} onChange={(event) => setNewAddress((current) => ({ ...current, street: event.target.value }))} />
                  <TextField label="Почтовый индекс" required={postalRequired} value={newAddress.postal_code} onChange={(event) => setNewAddress((current) => ({ ...current, postal_code: event.target.value }))} helperText={postalRequired ? undefined : "Необязательно"} />
                  <Box className={styles.formActions}>
                    {addresses.length > 0 && <Button onClick={() => setShowAddressForm(false)}>Отмена</Button>}
                    <Button variant="contained" onClick={saveAddress} disabled={submitting}>Сохранить адрес</Button>
                  </Box>
                </Box>}
              </>}

              {methodsLoading ? <CircularProgress size={24} /> : methods.length === 0 ? <Alert severity="info">Для этого адреса нет доступных способов доставки.</Alert> : <>
                {availableTabs.length > 1 && <Box className={styles.tabButtons} role="tablist">
                  {availableTabs.map((tab) => <button key={tab} type="button" role="tab" aria-selected={activeTab === tab} className={activeTab === tab ? `${styles.tabButton} ${styles.tabButtonActive}` : styles.tabButton} onClick={() => selectTab(tab)}>{TAB_LABELS[tab]}</button>)}
                </Box>}

                <RadioGroup value={methodId} onChange={(event) => setMethodId(Number(event.target.value))} className={styles.methodList}>
                  {tabMethods.map((method) => <Paper key={method.id} className={styles.optionCard} elevation={0}>
                    <FormControlLabel value={method.id} control={<Radio />} label={<Box><Typography className={styles.optionTitle}>{method.name} · {method.cost ? formatMoney(method.cost) : "бесплатно"}</Typography><Typography className={styles.optionMeta}>{method.description || method.estimated_days}</Typography></Box>} />
                  </Paper>)}
                </RadioGroup>

                {/* Интервалы: показываем только для курьерских методов, которые требуют
                    выбора даты и слота (backend выдаст 422 без них). */}
                {selectedMethod?.requires_scheduling && <>
                  {slotsLoading ? <Box sx={{ display: "flex", justifyContent: "center", py: 2 }}><CircularProgress size={24} /></Box> : slotsData && slotsData.dates.length > 0 ? <Box className={styles.slotSection}>
                    <Typography className={styles.slotLabel}>Дата доставки</Typography>
                    <Box className={styles.dateButtons}>
                      {slotsData.dates.map((date: DeliveryDate) => {
                        const d = new Date(date.date + "T00:00:00");
                        return <button key={date.date} type="button" className={selectedDate === date.date ? `${styles.dateButton} ${styles.dateButtonActive}` : styles.dateButton} onClick={() => { setSelectedDate(date.date); setSelectedSlotId(""); }}>{d.getDate()} {MONTH_NAMES[d.getMonth()]}, {WEEKDAY_NAMES[date.weekday]}</button>;
                      })}
                    </Box>
                    {availableSlotsForDate.length > 0 && <>
                      <Typography className={styles.slotLabel}>Время доставки</Typography>
                      <RadioGroup value={selectedSlotId} onChange={(event) => setSelectedSlotId(Number(event.target.value))} className={styles.methodList}>
                        {availableSlotsForDate.map((slot) => <Paper key={slot.id} className={styles.optionCard} elevation={0}>
                          <FormControlLabel value={slot.id} control={<Radio />} label={<Typography className={styles.optionTitle}>{slot.time_from.slice(0, 5)} — {slot.time_to.slice(0, 5)}</Typography>} />
                        </Paper>)}
                      </RadioGroup>
                    </>}
                  </Box> : <Alert severity="info">Нет доступных интервалов для этого метода.</Alert>}
                </>}
              </>}
            </Paper>

            <Paper className={styles.stepCard} elevation={0}>
              <Box className={styles.stepHeading}><PaymentsOutlinedIcon /><Box><Typography component="h2">Оплата и комментарий</Typography><Typography>Выберите удобный способ</Typography></Box></Box>
              <FormControl>
                <FormLabel>Способ оплаты</FormLabel>
                <RadioGroup row value={payment} onChange={(event) => setPayment(event.target.value as PaymentMethod)}>
                  <FormControlLabel value="card" control={<Radio />} label="Картой" />
                  <FormControlLabel value="cash" control={<Radio />} label="Наличными" />
                </RadioGroup>
              </FormControl>
              <TextField multiline minRows={3} label="Комментарий к заказу" value={notes} onChange={(event) => setNotes(event.target.value.slice(0, 500))} helperText={`${notes.length}/500`} />
            </Paper>
          </Box>

          {/* На desktop итог закреплён сбоку, на мобильном идёт после формы. */}
          <Box component="aside" className={styles.summary}>
            <Paper className={styles.summaryCard} elevation={0}>
              <Typography component="h2" className={styles.summaryTitle}>Ваш заказ</Typography>
              <Box className={styles.row}><span>Выбрано</span><strong>{(selection?.cart_item_ids.length ?? 0) + (selection?.cart_gift_ids.length ?? 0)} поз.</strong></Box>
              {quote && <>
                <Box className={styles.row}><span>Товары</span><strong>{formatMoney(quote.products_total + (quote.gift_markup_total ?? 0))}</strong></Box>
                <Box className={styles.row}><span>Доставка</span><strong>{quote.shipping_cost ? formatMoney(quote.shipping_cost) : "Бесплатно"}</strong></Box>
                {(quote.promotion_discount + quote.personal_discount + quote.cart_discount) > 0 && <Box className={`${styles.row} ${styles.discount}`}><span>Скидки</span><strong>−{formatMoney(quote.promotion_discount + quote.personal_discount + quote.cart_discount)}</strong></Box>}
              </>}
              <Divider />
              <Box className={styles.total}><span>Итого</span><strong>{quoteLoading ? "…" : quote ? formatMoney(quote.final_total) : "—"}</strong></Box>
              <Button className={styles.submitButton} onClick={submit} disabled={submitting || quoteLoading || !quote || !addressId || !methodId || (selectedMethod?.requires_scheduling && !selectedSlotId)}>{submitting ? "Оформляем…" : "Подтвердить заказ"}</Button>
              <Typography className={styles.hint}>Повторный клик не создаст второй заказ: запрос защищён Idempotency-Key.</Typography>
            </Paper>
          </Box>
        </Box>
      </Box>
      <Footer />
    </Box>
  );
}
