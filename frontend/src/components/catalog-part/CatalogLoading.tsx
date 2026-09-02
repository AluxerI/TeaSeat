import { useEffect, useState } from "react";

function DelayedSpinner({ active, delay = 420 }: { active: boolean; delay?: number }) {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    if (!active) {
      setVisible(false);
      return;
    }
    const timer = window.setTimeout(() => setVisible(true), delay);
    return () => window.clearTimeout(timer);
  }, [active, delay]);

  if (!active || !visible) return null;
  return (
    <span className="catalog-loading__progress" role="status" aria-live="polite">
      <span className="content-spinner" aria-hidden="true" />
      <span>Загрузка занимает чуть больше времени…</span>
    </span>
  );
}

export default function CatalogLoading({ count = 8, showSpinner = false }: { count?: number; showSpinner?: boolean }) {
  return (
    <section className="catalog-loading" role="status" aria-live="polite" aria-label="Загружаем каталог" aria-busy="true">
      {Array.from({ length: count }, (_, index) => (
        <article className="catalog-loading__card" aria-hidden="true" key={index}>
          <div className="catalog-loading__image" />
          <div className="catalog-loading__body">
            <span className="catalog-loading__line" />
            <span className="catalog-loading__line" />
            <span className="catalog-loading__line" />
            <span className="catalog-loading__button" />
          </div>
        </article>
      ))}
      <DelayedSpinner active={showSpinner} />
      <span className="catalog-loading__sr-only">Загружаем товары…</span>
    </section>
  );
}
