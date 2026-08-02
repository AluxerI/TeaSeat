import { useCallback, useEffect, useRef, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import type { GiftType } from "../../pages/ConstructorPage";
import type { ConstructorItem } from "../../data/constructorMockData";
import { cartApi } from "../../api/cartAPI";
import { useAuth } from "../../hooks/useAuth";
import styles from "../../scss/pages/ConstructorPage.module.scss";

/**
 * Этап 3 — подтверждение состава и отправка в корзину.
 *
 * Полёт подарка живёт в 3D-сцене, а не здесь: прежняя версия клонировала
 * DOM-«призрак» и гнала его requestAnimationFrame'ом поверх страницы, из-за
 * чего коробка на канвасе и летящий эмодзи были двумя разными объектами.
 * Теперь компонент только запускает анимацию (`onSeal`) и ждёт сигнала о её
 * завершении (`sealed`), чтобы дёрнуть API.
 */

interface StageThreeProps {
  giftType: GiftType;
  teas: ConstructorItem[];
  sweet: ConstructorItem;
  totalPrice: number;
  /** Запустить запечатку и полёт в корзину */
  onSeal: () => void;
  /** Полёт идёт прямо сейчас */
  sealing: boolean;
  /** Полёт завершён — пора обращаться к API */
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

export default function StageThree({
  giftType,
  teas,
  sweet,
  totalPrice,
  onSeal,
  sealing,
  sealed,
  onReset,
}: StageThreeProps) {
  const { user } = useAuth();
  const [status, setStatus] = useState<"idle" | "saving" | "done">("idle");
  const [error, setError] = useState("");
  const submitted = useRef(false);

  const giftTypeName = giftType === "simplified" ? "Упрощённая" : "Усложнённая";

  const handleConfirm = useCallback(() => {
    if (!user) {
      setError("Войдите в аккаунт, чтобы добавить подарок в корзину");
      return;
    }
    setError("");
    onSeal();
  }, [user, onSeal]);

  // Полёт закончился — отправляем состав в корзину. Ref-страж нужен потому,
  // что `sealed` остаётся true до сброса конструктора, а ререндеров может
  // быть много: без него запрос ушёл бы повторно.
  useEffect(() => {
    if (!sealed || submitted.current) return;
    submitted.current = true;

    let cancelled = false;

    (async () => {
      setStatus("saving");
      try {
        for (const tea of teas) {
          await cartApi.addItem({ product_id: tea.id, quantity: 1 });
        }
        await cartApi.addItem({ product_id: sweet.id, quantity: 1 });
        if (!cancelled) setStatus("done");
      } catch (err: any) {
        if (cancelled) return;
        setStatus("idle");
        setError(err?.response?.data?.message ?? "Не удалось добавить в корзину");
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [sealed, teas, sweet]);

  // Иконка корзины в шапке подмигивает, когда подарок «долетел».
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

  return (
    <div className={styles.confirmStage}>
      <div className={styles.confirmCard}>
        <div className={styles.confirmSection}>
          <div className={styles.confirmSectionTitle}>Тип подарка</div>
          <div className={styles.confirmItem}>
            <span className={styles.confirmItemName}>Коробка «{giftTypeName}»</span>
          </div>
        </div>

        <div className={styles.confirmSection}>
          <div className={styles.confirmSectionTitle}>Чаи</div>
          {teas.map((tea) => (
            <div key={tea.id} className={styles.confirmItem}>
              <span className={styles.confirmItemName}>{tea.name}</span>
              <span className={styles.confirmItemPrice}>{formatCurrency(tea.price)}</span>
            </div>
          ))}
        </div>

        <div className={styles.confirmSection}>
          <div className={styles.confirmSectionTitle}>Десерт</div>
          <div className={styles.confirmItem}>
            <span className={styles.confirmItemName}>{sweet.name}</span>
            <span className={styles.confirmItemPrice}>{formatCurrency(sweet.price)}</span>
          </div>
        </div>

        <div className={styles.confirmTotal}>
          <span>Итого</span>
          <span className={styles.confirmTotalValue}>{formatCurrency(totalPrice)}</span>
        </div>
      </div>

      {error && <p className={styles.confirmError}>{error}</p>}

      <AnimatePresence mode="wait">
        {!sealing && !sealed && (
          <motion.div
            key="confirm-btn"
            className={styles.actions}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
          >
            <button className={styles.btnPrimary} onClick={handleConfirm}>
              Упаковать и добавить в корзину 🎁
            </button>
          </motion.div>
        )}

        {sealing && (
          <motion.p
            key="sealing"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className={styles.confirmStatus}
          >
            Завязываем ленту и отправляем в корзину…
          </motion.p>
        )}

        {sealed && status === "saving" && (
          <motion.p
            key="saving"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className={styles.confirmStatus}
          >
            Сохраняем состав…
          </motion.p>
        )}

        {sealed && status === "done" && (
          <motion.div
            key="done"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className={styles.successToast}
          >
            ✓ Подарок добавлен в корзину!
          </motion.div>
        )}
      </AnimatePresence>

      {sealed && status !== "saving" && (
        <motion.div
          className={styles.actions}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <a
            href="/cart"
            className={styles.btnPrimary}
            style={{ textDecoration: "none", textAlign: "center" }}
          >
            Перейти в корзину
          </a>
          <button className={styles.btnSecondary} onClick={onReset}>
            Создать ещё
          </button>
        </motion.div>
      )}
    </div>
  );
}
