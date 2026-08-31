import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { giftConstructorApi } from "../../api/giftConstructorAPI";
import type { ConstructorDraft, ConstructorProductSize, Gift, GiftSizeProfile, SimpleGiftQuote } from "../../interfaces/giftConstructor";
import { useAuth } from "../../hooks/useAuth";
import { useCustomerCart } from "../../hooks/useCustomerCart";
import { useOnlineStatus } from "../../hooks/useOnlineStatus";
import { constructorError, createGiftInstanceId } from "../../utils/constructorErrors";
import { constructorItemQuantity } from "../../utils/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

const money = (amount: number) => new Intl.NumberFormat("ru-RU", { style: "currency", currency: "RUB" }).format(amount);

interface Props {
  draft: ConstructorDraft;
  box: GiftSizeProfile;
  contents: ConstructorProductSize[];
  onBack: () => void;
  onReset: () => void;
  onBusy: (busy: boolean) => void;
}

export default function GiftConfirmation({ draft, box, contents, onBack, onReset, onBusy }: Props) {
  const { user } = useAuth();
  const { addGift } = useCustomerCart();
  const online = useOnlineStatus();
  const [name, setName] = useState(`Подарок «${box.name}»`.slice(0, 255));
  const [quote, setQuote] = useState<SimpleGiftQuote | null>(null);
  const [quoteLoading, setQuoteLoading] = useState(true);
  const [error, setError] = useState("");
  const [attempt, setAttempt] = useState(0);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [unknownCreation, setUnknownCreation] = useState(false);
  const inFlight = useRef(false);
  const gift = useRef<Gift | null>(null);
  const instanceId = useRef<string | null>(null);
  const mounted = useRef(true);
  const currentUserId = useRef(user?.id);
  const currentlyOnline = useRef(online);
  currentUserId.current = user?.id;
  currentlyOnline.current = online;
  useEffect(() => { mounted.current = true; return () => { mounted.current = false; }; }, []);

  useEffect(() => {
    if (!online || !user || done) { setQuote(null); setQuoteLoading(false); return; }
    const controller = new AbortController();
    setQuote(null);
    setQuoteLoading(true);
    setError("");
    void (async () => {
      try {
        if (draft.mode === "advanced") await giftConstructorApi.validateAdvanced(draft.selection, controller.signal);
        if (controller.signal.aborted) return;
        const response = draft.mode === "simple"
          ? await giftConstructorApi.quoteSimple({ ...draft.selection, quantity: 1 }, controller.signal)
          : await giftConstructorApi.quoteAdvanced(draft.selection, controller.signal);
        if (controller.signal.aborted) return;
        if (!response?.valid || !Number.isFinite(response.totals?.final_total) || response.totals.final_total < 0) {
          throw new Error("Некорректный ответ API: отсутствует итоговая цена подарка.");
        }
        setQuote(response);
      } catch (reason) {
        if (!controller.signal.aborted) setError(constructorError(reason));
      } finally {
        if (!controller.signal.aborted) setQuoteLoading(false);
      }
    })();
    return () => controller.abort();
  }, [draft, online, user?.id, attempt, done]);

  const submit = async () => {
    if (inFlight.current || done || unknownCreation || !online || !user) return;
    if (!gift.current && (!quote || quoteLoading)) return;
    if (!name.trim()) { setError("Введите название подарка."); return; }
    const owner = user.id;
    const current = () => mounted.current && currentUserId.current === owner;
    inFlight.current = true;
    setBusy(true);
    onBusy(true);
    setError("");
    try {
      instanceId.current ??= createGiftInstanceId();
      if (!gift.current) {
        const response = draft.mode === "simple"
          ? await giftConstructorApi.createSimpleGift({ ...draft.selection, name: name.trim() })
          : await giftConstructorApi.createAdvancedGift({ ...draft.selection, name: name.trim() });
        if (!current()) return;
        if (!Number.isInteger(response?.id) || !Number.isInteger(response.version) || response.version < 1) {
          throw new Error("Некорректный ответ API: отсутствует идентификатор созданного подарка.");
        }
        gift.current = response;
      }
      if (!current()) return;
      if (!currentlyOnline.current || !navigator.onLine) throw new Error("Соединение потеряно перед добавлением в корзину");
      await addGift({ gift_id: gift.current.id, gift_version: gift.current.version, quantity: 1, client_instance_id: instanceId.current });
      if (current()) setDone(true);
    } catch (reason) {
      if (current()) {
        const status = (reason as { response?: { status?: number } })?.response?.status;
        const uncertain = !gift.current && (status === undefined || status >= 500);
        setUnknownCreation(uncertain);
        setError(`${constructorError(reason)}${uncertain ? " Сервер мог сохранить подарок: повтор создания заблокирован, чтобы не создать дубль." : ""}`);
      }
    } finally {
      inFlight.current = false;
      if (current()) { setBusy(false); onBusy(false); }
    }
  };

  return <section className={styles.confirmation}>
    <h2>Проверьте подарок</h2>
    <p>{box.name} · {contents.length} позиций</p>
    <ul>{contents.map((size, index) => <li key={`${size.id}-${index}`}>{size.product.name} — {constructorItemQuantity(size)}</li>)}</ul>
    <label className={styles.field}>Название подарка<input value={name} maxLength={255} disabled={busy || done || Boolean(gift.current) || unknownCreation} onChange={(event) => setName(event.target.value)} /></label>
    {quoteLoading && <p role="status">Проверяем раскладку, наличие и цену…</p>}
    {quote && <div className={styles.totals}>
      <span>Товары до скидок</span><span>{money(quote.totals.products_total)}</span>
      <span>Коробка и упаковка</span><span>{money(quote.totals.gift_markup_total)}</span>
      <strong>Итого по расчёту сервера</strong><strong>{money(quote.totals.final_total)}</strong>
    </div>}
    {!online && <p role="alert">Нет соединения. Подарки не отправляются в офлайн-очередь.</p>}
    {error && <p role="alert" className={styles.error}>{error}</p>}
    {done ? <>
      <p role="status">Подарок добавлен в корзину.</p>
      <div className={styles.actions}><Link to="/cart" className={styles.primary}>Перейти в корзину</Link><button onClick={onReset}>Создать ещё</button></div>
    </> : <div className={styles.actions}>
      {!quote && !quoteLoading && !gift.current && !unknownCreation && <button onClick={() => setAttempt((value) => value + 1)} disabled={!online}>Повторить расчёт</button>}
      <button className={styles.primary} onClick={() => void submit()} disabled={busy || !online || unknownCreation || (!gift.current && (!quote || quoteLoading))}>
        {busy ? "Сохраняем подарок…" : gift.current ? "Повторить добавление в корзину" : "Добавить подарок в корзину"}
      </button>
      <button onClick={onBack} disabled={busy || Boolean(gift.current) || unknownCreation}>К составу</button>
      {(gift.current || unknownCreation) && <Link to="/cart">Проверить корзину</Link>}
    </div>}
    <p className={styles.hint}>Расчёт не резервирует товары. При добавлении и оформлении заказа сервер проверит наличие повторно.</p>
  </section>;
}
