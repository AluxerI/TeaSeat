import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import { constructorItemPrice, constructorItemQuantity } from "../../utils/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

interface Props {
  title: string;
  options: ConstructorProductSize[];
  selected: number[];
  limit: number;
  onAdd: (size: ConstructorProductSize) => void;
  onRemove?: (size: ConstructorProductSize) => void;
}

export default function ConstructorCatalog({ title, options, selected, limit, onAdd, onRemove }: Props) {
  return <section className={styles.catalog}>
    <h2>{title} <small>{selected.length} выбрано · максимум {limit}</small></h2>
    {!options.length && <p role="status">Список форматов для этого раздела пуст.</p>}
    <ul className={styles.productList}>
      {options.map((size) => {
        const count = selected.filter((id) => id === size.id).length;
        return <li key={size.id}>
          <img src={normalizeAssetUrl(size.product.image) || "/pages/catalog/details/tea.svg"} alt="" loading="lazy" />
          <div className={styles.productDescription}>
            <strong>{size.product.name}</strong>
            <span>{size.label} · {constructorItemQuantity(size)}</span>
            <span>{constructorItemPrice(size).toLocaleString("ru-RU")} ₽ <small>до скидок</small></span>
          </div>
          <div className={styles.counter}>
            {onRemove && <button type="button" disabled={!count} aria-label={`Убрать ${size.product.name}, ${size.label}`} onClick={() => onRemove(size)}>−</button>}
            <output aria-label={`Количество ${size.product.name}, ${size.label}`}>{count}</output>
            <button type="button" disabled={selected.length >= limit} aria-label={`Добавить ${size.product.name}, ${size.label}`} onClick={() => onAdd(size)}>+</button>
          </div>
        </li>;
      })}
    </ul>
  </section>;
}
