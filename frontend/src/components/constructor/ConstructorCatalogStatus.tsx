import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

interface Props {
  box: GiftSizeProfile;
  mode: "simple" | "advanced";
  availableCount: number | null;
  onRetry: () => void;
  retryDisabled: boolean;
}

/** Пустой успешный GET — не ошибка сети и не повод подставлять моки. */
export default function ConstructorCatalogStatus({ box, mode, availableCount, onRetry, retryDisabled }: Props) {
  return <aside className={styles.notice} aria-label="Диагностика каталога конструктора">
    <p role="status">Для этой коробки пока нет подходящих товаров.</p>
    <details>
      <summary>Почему список пуст?</summary>
      <p>Коробка «{box.name}» (ID {box.id}).</p>
    {availableCount === 0 ? <p>В общем каталоге этого режима тоже нет доступных форматов. Товар в магазине и формат конструктора — разные записи: нужен активный ProductSize, связанный с доступным товаром и активным профилем размера типа item.</p>
      : availableCount !== null ? <p>В общем каталоге этого режима доступно {availableCount} форматов, но для этой коробки не подошёл ни один. Проверьте габариты с учётом поворота, предел веса и соответствие product_quantity шагу продажи sale_step.</p>
        : <p>Общий каталог ещё не получен. Повторите загрузку, чтобы проверить, есть ли доступные форматы.</p>}
      <p><code>GET /api/gift-constructor/{mode}/options</code> — {availableCount ?? "не загружено"} форматов.</p>
      <p><code>GET /api/gift-constructor/boxes/{box.id}/products</code> — 0 форматов.</p>
      <p>Проверьте активные записи product_sizes, связи с товарами, количество и роль tea, sweet или general. Только первые две роли участвуют в простом режиме.</p>
      <p>40 — лимит позиций в раскладке, а не число загруженных товаров или остаток.</p>
    </details>
    <button type="button" aria-label="Обновить каталог конструктора" onClick={onRetry} disabled={retryDisabled}>Обновить</button>
  </aside>;
}
