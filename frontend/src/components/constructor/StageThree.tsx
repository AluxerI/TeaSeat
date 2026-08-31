import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { Link } from "react-router-dom";
import type { GiftType } from "../../pages/ConstructorPage";
import type {
  ConstructorProductSize,
  Gift,
  GiftSizeProfile,
  SimpleGiftQuote,
  SimpleGiftSelection,
} from "../../interfaces/giftConstructor";
import { giftConstructorApi } from "../../api/giftConstructorAPI";
import { useAuth } from "../../hooks/useAuth";
import { useCustomerCart } from "../../hooks/useCustomerCart";
import { useOnlineStatus } from "../../hooks/useOnlineStatus";
import { constructorItemPrice } from "../../utils/giftConstructor";
import { extractError, translateError } from "../../utils/translateError";
import styles from "../../scss/pages/ConstructorPage.module.scss";

interface StageThreeProps {
  giftType: GiftType;
  box: GiftSizeProfile;
  teas: ConstructorProductSize[];
  sweets: ConstructorProductSize[];
  onSeal: () => void;
  sealing: boolean;
  sealed: boolean;
  onReset: () => void;
}
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    minimumFractionDigits: 0,
  }).format(amount);
}

function createClientInstanceId(): string {
  if (typeof globalThis.crypto?.randomUUID === "function") {
    return globalThis.crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  globalThis.crypto.getRandomValues(bytes);
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0"));
  return `${hex.slice(0, 4).join("")}-${hex.slice(4, 6).join("")}-${hex.slice(6, 8).join("")}-${hex.slice(8, 10).join("")}-${hex.slice(10).join("")}`;
}

export default function StageThree({
  giftType,
  box,
  teas,
  sweets,
  onSeal,
  sealing,
  sealed,
  onReset,
}: StageThreeProps) {
  const { user } = useAuth();
  const { addGift } = useCustomerCart();
  const online = useOnlineStatus();
  const [giftName, setGiftName] = useState(`Чайный подарок «${box.name}»`);
  const [quote, setQuote] = useState<SimpleGiftQuote | null>(null);
  const [quoteLoading, setQuoteLoading] = useState(true);
  const [status, setStatus] = useState<"idle" | "saving" | "done" | "error">("idle");
  const [error, setError] = useState("");
  const submitted = useRef(false);
  const createdGift = useRef<Gift | null>(null);
  const clientInstanceId = useRef<string | null>(null);

  const giftTypeName = giftType === "simplified" ? "Упрощённая" : "Усложнённая";
  const selection = useMemo<SimpleGiftSelection>(() => ({
    box_profile_id: box.id,
    tea_product_size_ids: teas.map((item) => item.id),
    sweet_product_size_ids: sweets.map((item) => item.id),
  }), [box.id, sweets, teas]);

  useEffect(() => {
    let active = true;
    if (!user || !online) {
      setQuote(null);
      setQuoteLoading(false);
      setError(!user
        ? "Войдите в аккаунт, чтобы рассчитать подарок"
        : "Расчёт подарка недоступен без подключения к сети");
      return () => { active = false; };
    }

    setQuoteLoading(true);
    setError("");
    giftConstructorApi.quoteSimple({ ...selection, quantity: 1 })
      .then((nextQuote) => {
        if (active) setQuote(nextQuote);
      })
      .catch((reason) => {
        if (active) {
          setQuote(null);
          setError(translateError(extractError(reason)));
        }
      })
      .finally(() => {
        if (active) setQuoteLoading(false);
      });
    return () => { active = false; };
  }, [online, selection, user?.id]);

  const handleConfirm = useCallback(() => {
    if (!user) {
      setError("Войдите в аккаунт, чтобы добавить подарок в корзину");
      return;
    }
    if (!online) {
      setError("Добавление подарка недоступно без подключения к сети");
      return;
    }
    if (!giftName.trim()) {
      setError("Введите название подарка");
      return;
    }
    if (!quote || quoteLoading) {
      setError("Дождитесь актуального расчёта стоимости");
      return;
    }
    setError("");
    onSeal();
  }, [giftName, online, onSeal, quote, quoteLoading, user]);

  const saveGift = useCallback(async () => {
    if (submitted.current || !user || !online) return;
    submitted.current = true;
    setStatus("saving");
    setError("");
    try {
      const gift = createdGift.current ?? await giftConstructorApi.createSimpleGift({
        ...selection,
        name: giftName.trim(),
      });
      createdGift.current = gift;
      clientInstanceId.current ??= createClientInstanceId();
      await addGift({
        gift_id: gift.id,
        gift_version: gift.version,
        quantity: 1,
        client_instance_id: clientInstanceId.current,
      });
      setStatus("done");
    } catch (reason) {
      setStatus("error");
      const message = translateError(extractError(reason)) || "Не удалось добавить подарок в корзину";
      setError(createdGift.current
        ? message
        : `${message}. Повторное создание отключено, чтобы не получить дубликат при сетевом таймауте.`);
    } finally {
      submitted.current = false;
    }
  }, [addGift, giftName, online, selection, user]);

  useEffect(() => {
    if (sealed && status === "idle") void saveGift();
  }, [saveGift, sealed, status]);

  useEffect(() => {
    if (!sealed) return;
    const target = document.getElementById("constructor-cart-target");
    if (!target) return;
    target.style.transition = "transform 0.18s ease";
    target.style.transform = "scale(1.3)";
    const id = window.setTimeout(() => {
      target.style.transform = "scale(1)";
    }, 200);
    return () => window.clearTimeout(id);
  }, [sealed]);

  const preliminaryTotal = [...teas, ...sweets]
    .reduce((sum, item) => sum + constructorItemPrice(item), box.default_markup_amount);
  const total = quote?.totals.final_total ?? preliminaryTotal;

  return (
    <div className={styles.confirmStage}>
      <div className={styles.confirmCard}>
        <label className={styles.giftNameField}>
          <span>Название подарка</span>
          <input
            value={giftName}
            onChange={(event) => setGiftName(event.target.value)}
            maxLength={255}
            disabled={sealing || sealed}
          />
        </label>

        <div className={styles.confirmSection}>
          <div className={styles.confirmSectionTitle}>Тип подарка</div>
          <div className={styles.confirmItem}>
            <span className={styles.confirmItemName}>{giftTypeName}: {box.name}</span>
            <span className={styles.confirmItemPrice}>{formatCurrency(box.default_markup_amount)}</span>
          </div>
        </div>

        <div className={styles.confirmSection}>
          <div className={styles.confirmSectionTitle}>Чаи</div>
          {teas.map((tea, index) => (
            <div key={`${tea.id}-${index}`} className={styles.confirmItem}>
              <span className={styles.confirmItemName}>{tea.product.name}</span>
              <span className={styles.confirmItemPrice}>{formatCurrency(constructorItemPrice(tea))}</span>
            </div>
          ))}
        </div>

        <div className={styles.confirmSection}>
          <div className={styles.confirmSectionTitle}>Десерты</div>
          {sweets.map((sweet, index) => (
            <div key={`${sweet.id}-${index}`} className={styles.confirmItem}>
              <span className={styles.confirmItemName}>{sweet.product.name}</span>
              <span className={styles.confirmItemPrice}>{formatCurrency(constructorItemPrice(sweet))}</span>
            </div>
          ))}
        </div>

        <div className={styles.confirmTotal}>
          <span>{quoteLoading ? "Рассчитываем…" : "Итого"}</span>
          <span className={styles.confirmTotalValue}>{formatCurrency(total)}</span>
        </div>
        <p className={styles.quoteHint}>
          {quote ? "Цена подтверждена backend" : "Показана предварительная стоимость"}
        </p>
      </div>

      {error && <p role="alert" className={styles.confirmError}>{error}</p>}

      <AnimatePresence mode="wait">
        {!sealing && !sealed && (
          <motion.div key="confirm-btn" className={styles.actions} initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <button
              className={styles.btnPrimary}
              onClick={handleConfirm}
              disabled={quoteLoading || !quote || !online}
            >
              Упаковать и добавить в корзину 🎁
            </button>
          </motion.div>
        )}

        {sealing && (
          <motion.p key="sealing" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className={styles.confirmStatus}>
            Завязываем ленту и отправляем в корзину…
          </motion.p>
        )}

        {sealed && status === "saving" && (
          <motion.p key="saving" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className={styles.confirmStatus}>
            Создаём подарок и обновляем корзину…
          </motion.p>
        )}

        {sealed && status === "done" && (
          <motion.div key="done" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} className={styles.successToast}>
            ✓ Подарок добавлен в корзину!
          </motion.div>
        )}
      </AnimatePresence>

      {sealed && status === "error" && (
        <div className={styles.actions}>
          {createdGift.current && (
            <button className={styles.btnPrimary} onClick={() => void saveGift()} disabled={!online}>
              Повторить добавление
            </button>
          )}
          <button className={styles.btnSecondary} onClick={onReset}>Создать заново</button>
        </div>
      )}

      {sealed && status === "done" && (
        <motion.div className={styles.actions} initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }}>
          <Link to="/cart" className={styles.btnPrimary}>Перейти в корзину</Link>
          <button className={styles.btnSecondary} onClick={onReset}>Создать ещё</button>
        </motion.div>
      )}
    </div>
  );
}
