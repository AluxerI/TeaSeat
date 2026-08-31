import { useCallback, useState } from "react";
import { Link } from "react-router-dom";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { useAuth } from "../hooks/useAuth";
import { useConstructorResource } from "../hooks/useConstructorResource";
import { giftConstructorApi } from "../api/giftConstructorAPI";
import ConstructorEditor from "../components/constructor/ConstructorEditor";
import ConstructorCatalogStatus from "../components/constructor/ConstructorCatalogStatus";
import styles from "../scss/pages/ConstructorWorkspace.module.scss";

// Сохранён type export для старых компонентов сцены; страница их больше не монтирует.
export type { GiftType } from "../components/constructor/three/sceneConfig";
export type ConstructorStage = 0 | 1 | 2 | 3;

function Workspace({ userId }: { userId: number }) {
  const [mode, setMode] = useState<"simple" | "advanced">("simple");
  const [boxId, setBoxId] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const loadOptions = useCallback((signal: AbortSignal) => giftConstructorApi.loadOptions(mode, signal), [mode]);
  const options = useConstructorResource(`${userId}:${mode}`, true, loadOptions);
  const boxes = (options.data?.boxes ?? []).filter((box) => mode === "advanced" || (
    box.simple_constructor_enabled && box.simple_requirements
      && box.simple_requirements.tea_count > 0 && box.simple_requirements.sweet_count > 0
  ));
  const box = boxes.find((candidate) => candidate.id === boxId) ?? boxes[0];
  const loadProducts = useCallback((signal: AbortSignal) => giftConstructorApi.getBoxProducts(box!.id, signal), [box?.id]);
  const products = useConstructorResource(`${userId}:${mode}:${box?.id}`, Boolean(box), loadProducts);

  return <>
    <div className={styles.modeSwitch} aria-label="Режим конструктора">
      <button type="button" aria-pressed={mode === "simple"} disabled={busy} onClick={() => { setMode("simple"); setBoxId(null); }}>Простой — список</button>
      <button type="button" aria-pressed={mode === "advanced"} disabled={busy} onClick={() => { setMode("advanced"); setBoxId(null); }}>Сложный — сетка 2.5D</button>
    </div>
    {options.loading && <p role="status">Загружаем коробки…</p>}
    {options.error && <div role="alert" className={styles.notice}><p>{options.error}</p><button type="button" disabled={!options.online} onClick={options.retry}>Повторить загрузку коробок</button><Link to="/login">Войти заново</Link></div>}
    {!options.loading && options.data && !boxes.length && <p role="status">Для этого режима нет активных коробок. Проверьте профили коробок на backend.</p>}
    {box && <label className={styles.boxSelect}>Коробка<select aria-label="Коробка" value={box.id} disabled={busy} onChange={(event) => setBoxId(Number(event.target.value))}>
      {boxes.map((option) => <option key={option.id} value={option.id}>{option.name} · {option.width_cells} × {option.height_cells}{mode === "simple" ? ` · ${option.simple_requirements!.tea_count} чая + ${option.simple_requirements!.sweet_count} сладостей` : ""}</option>)}
    </select></label>}
    {box && products.loading && <p role="status">Загружаем форматы для коробки…</p>}
    {box && products.error && <div role="alert" className={styles.notice}><p>{products.error}</p><button type="button" disabled={!products.online} onClick={products.retry}>Повторить загрузку товаров</button></div>}
    {box && products.data?.product_sizes.length === 0 && <ConstructorCatalogStatus box={box} mode={mode}
      availableCount={options.error || options.loading ? null : options.data?.product_sizes.length ?? null}
      onRetry={() => { options.retry(); products.retry(); }}
      retryDisabled={busy || !products.online || products.loading || options.loading} />}
    {box && <p className={styles.hint}>Назад по шагам можно вернуться без потери состава. Смена коробки или режима очистит выбор.</p>}
    {box && products.data && <ConstructorEditor key={`${mode}:${box.id}`} mode={mode} box={products.data.box} sizes={products.data.product_sizes} onBusy={setBusy} cellSizeMm={options.data?.cell_size_mm} />}
  </>;
}

export default function ConstructorPage() {
  const { user, loading } = useAuth();
  return <div className={styles.page}>
    <Header />
    <main className={styles.content}>
      <h1>Конструктор подарков</h1>
      {loading ? <p role="status">Проверяем вход…</p> : user
        ? <Workspace key={user.id} userId={user.id} />
        : <div className={styles.notice}><p>Войдите в аккаунт: каталог конструктора и сохранение подарков требуют авторизации.</p><Link className={styles.primary} to="/login">Войти</Link></div>}
    </main>
    <Footer />
  </div>;
}
