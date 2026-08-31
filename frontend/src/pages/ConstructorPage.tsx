import { useCallback, useState } from "react";
import { Link } from "react-router-dom";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { useAuth } from "../hooks/useAuth";
import { useConstructorResource } from "../hooks/useConstructorResource";
import { giftConstructorApi } from "../api/giftConstructorAPI";
import ConstructorEditor from "../components/constructor/ConstructorEditor";
import ConstructorCatalogStatus from "../components/constructor/ConstructorCatalogStatus";
import { ConstructorBoxPicker, ConstructorModePicker, type ConstructorMode } from "../components/constructor/ConstructorChoice";
import ConstructorSteps from "../components/constructor/ConstructorSteps";
import styles from "../scss/pages/ConstructorWorkspace.module.scss";

// Совместимость старых компонентов анимации.
export type { GiftType } from "../components/constructor/three/sceneConfig";
export type ConstructorStage = 0 | 1 | 2 | 3;

function Workspace({ userId }: { userId: number }) {
  const [mode, setMode] = useState<ConstructorMode | null>(null);
  const [step, setStep] = useState<"mode" | "box" | "editor">("box");
  const [boxId, setBoxId] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  // Advanced options содержит все активные коробки, включая simple_requirements.
  // Размер выбирается до режима; смена режима не перезагружает тот же каталог.
  const loadOptions = useCallback((signal: AbortSignal) => giftConstructorApi.loadOptions("advanced", signal), []);
  const options = useConstructorResource(`${userId}:boxes`, true, loadOptions);
  const boxes = options.data?.boxes ?? [];
  const loadProducts = useCallback((signal: AbortSignal) => giftConstructorApi.getBoxProducts(boxId!, signal), [boxId]);
  const products = useConstructorResource(`${userId}:${boxId}`, boxId !== null, loadProducts);
  // Реконнект не размонтирует уже открытый редактор/созданный Gift, даже если
  // коробку сняли с публикации. Quote и create всё равно проверяются сервером.
  const box = boxes.find((candidate) => candidate.id === boxId) ?? products.data?.box;

  return <>
    {step !== "editor" && <ConstructorSteps current={step === "box" ? 0 : 1} onBack={() => setStep("box")} />}
    {options.loading && !options.data && <p role="status">Загружаем коробки…</p>}
    {options.error && <div role="alert" className={styles.notice}><p>{options.error}</p><button type="button" disabled={!options.online || busy} onClick={options.retry}>Повторить загрузку коробок</button><Link to="/login">Войти заново</Link></div>}
    {!options.loading && options.data && !boxes.length && step === "box" && <p role="status">Сейчас нет активных коробок.</p>}
    {step === "box" && <>
      {!!boxes.length && <ConstructorBoxPicker boxes={boxes} selectedId={boxId} cellSizeMm={options.data?.cell_size_mm} onSelect={(next) => {
        if (next.id !== boxId) setMode(null);
        setBoxId(next.id); setStep("mode");
      }} />}
    </>}
    {step === "mode" && box && <ConstructorModePicker box={products.data?.box ?? box} onSelect={(next) => { setMode(next); setStep("editor"); }} />}
    <div hidden={step !== "editor"}>
    {step === "editor" && !products.data && <ConstructorSteps current={2} onBack={(index) => setStep(index === 0 ? "box" : "mode")} />}
    {box && products.loading && <p role="status">Загружаем товары…</p>}
    {box && products.error && <div role="alert" className={styles.notice}><p>{products.error}</p><button type="button" disabled={!products.online} onClick={products.retry}>Повторить загрузку товаров</button></div>}
    {mode && box && products.data?.product_sizes.length === 0 && <ConstructorCatalogStatus box={box} mode={mode}
      availableCount={options.error || options.loading ? null : options.data?.product_sizes.length ?? null}
      onRetry={() => { options.retry(); products.retry(); }}
      retryDisabled={busy || !products.online || products.loading || options.loading} />}
    {mode && box && products.data && <ConstructorEditor key={box.id} mode={mode} box={products.data.box} sizes={products.data.product_sizes} onBusy={setBusy}
      active={step === "editor"} startEditing onBackToMode={() => setStep("mode")}
      onBackToBoxes={() => setStep("box")} cellSizeMm={options.data?.cell_size_mm} />}
    </div>
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
