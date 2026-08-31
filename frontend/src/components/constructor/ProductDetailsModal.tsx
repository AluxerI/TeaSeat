import { useEffect, useRef } from "react";
import AddShoppingCartRounded from "@mui/icons-material/AddShoppingCartRounded";
import IconButton from "@mui/material/IconButton";
import type { ConstructorProductSize, GiftSizeProfile } from "../../interfaces/giftConstructor";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import { constructorItemPrice, constructorItemQuantity } from "../../utils/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";
import FootprintDiagram from "./FootprintDiagram";

interface Props {
  box: GiftSizeProfile;
  size: ConstructorProductSize;
  cellSizeMm?: number | null;
  onClose: () => void;
  onAdd: (size: ConstructorProductSize) => void;
  atLimit: boolean;
}

export default function ProductDetailsModal({ box, size, cellSizeMm, onClose, onAdd, atLimit }: Props) {
  const dialog = useRef<HTMLDialogElement>(null);
  const product = size.product;

  useEffect(() => {
    const node = dialog.current;
    if (!node) return;
    const previous = document.activeElement as HTMLElement | null;
    if (typeof node.showModal === "function" && !node.open) node.showModal();
    else if (!node.open) node.setAttribute("open", "");
    return () => { if (node.open) node.close?.(); if (previous?.isConnected) previous.focus(); };
  }, []);

  return <dialog ref={dialog} className={styles.productDetailsModal} onCancel={(event) => { event.preventDefault(); onClose(); }}
    onClick={(event) => { if (event.target === dialog.current) onClose(); }}>
    <div className={styles.productDetailsBody}>
      <button type="button" className={styles.productDetailsClose} aria-label="Закрыть подробности" onClick={onClose}>×</button>
      <img src={normalizeAssetUrl(product.image) || "/pages/catalog/details/tea.svg"} alt="" />
      <header>
        <h3>{product.name}</h3>
        {product.brand && <p className={styles.productDetailsBrand}>{product.brand}</p>}
      </header>
      <dl className={styles.productDetailsGrid}>
        <div><dt>Формат</dt><dd>{size.label}</dd></div>
        <div><dt>Кол-во в позиции</dt><dd>{constructorItemQuantity(size)}</dd></div>
        <div className={styles.productDetailsFootprint}><dt>Размер в коробке</dt><dd><FootprintDiagram box={box} width={size.size.width_cells} height={size.size.height_cells} canRotate={size.size.can_rotate} cellSizeMm={cellSizeMm} /></dd></div>
        <div><dt>Цена</dt><dd>{constructorItemPrice(size).toLocaleString("ru-RU")} ₽ <small>до скидок</small></dd></div>
      </dl>
      <div className={styles.productDetailsActions}>
        <button type="button" onClick={onClose}>Закрыть</button>
        <IconButton className={styles.addButton} title="Добавить в коробку" aria-label="Добавить формат в коробку" disabled={atLimit} onClick={() => { onAdd(size); onClose(); }}><AddShoppingCartRounded /></IconButton>
      </div>
    </div>
  </dialog>;
}
