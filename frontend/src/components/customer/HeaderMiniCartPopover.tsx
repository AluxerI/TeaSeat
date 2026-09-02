import { useEffect, useState } from "react";
import { Button, CircularProgress, Popover } from "@mui/material";
import { useNavigate } from "react-router-dom";
import { cartApi } from "../../api/cartAPI";
import type { Cart } from "../../interfaces/cart";

interface Props {
  open: boolean;
  anchorEl: HTMLElement | null;
  onClose: () => void;
}

const money = new Intl.NumberFormat("ru-RU", { style: "currency", currency: "RUB", maximumFractionDigits: 2 });

function itemQuantity(quantity: number, unit: string): string {
  return unit === "gram" ? `${quantity} г` : `${quantity} шт.`;
}

export default function HeaderMiniCartPopover({ open, anchorEl, onClose }: Props) {
  const navigate = useNavigate();
  const [cart, setCart] = useState<Cart | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const load = async () => {
    setLoading(true);
    setError("");
    try {
      setCart(await cartApi.getCart());
    } catch {
      setError("Не удалось загрузить корзину");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (open) void load();
  }, [open]);

  const goToCart = () => {
    onClose();
    navigate("/cart");
  };

  const itemCount = (cart?.items.length ?? 0) + (cart?.gifts?.length ?? 0);

  return (
    <Popover
      className="mini-cart-popover"
      open={open}
      anchorEl={anchorEl}
      onClose={onClose}
      anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
      transformOrigin={{ vertical: "top", horizontal: "right" }}
      transitionDuration={180}
      disableScrollLock
      slotProps={{
        paper: {
          sx: {
            width: { xs: "calc(100vw - 16px)", sm: 420 },
            maxWidth: "calc(100vw - 16px)",
            maxHeight: { xs: "78vh", sm: "72vh" },
            mt: 1,
            overflow: "hidden",
            border: "1px solid #eadfce",
            borderRadius: { xs: "16px", sm: "22px" },
            bgcolor: "#fffdf9",
            boxShadow: "0 18px 60px rgba(58, 38, 24, .18)",
          },
        },
      }}
    >
      <section className="mini-cart-popup" aria-label="Мини-корзина">
        <header className="mini-cart-popup__header">
          <div><span>Корзина</span><strong>{itemCount ? `${itemCount} поз.` : "Пусто"}</strong></div>
          <button type="button" className="mini-cart-popup__close" aria-label="Закрыть корзину" onClick={onClose}>×</button>
        </header>

        <div className="mini-cart-popup__body">
          {loading && <div className="mini-cart-popup__state"><CircularProgress size={28} /><span>Загружаем…</span></div>}
          {!loading && error && <div className="mini-cart-popup__state" role="alert"><span>{error}</span><Button size="small" onClick={() => void load()}>Повторить</Button></div>}
          {!loading && !error && cart && itemCount === 0 && <div className="mini-cart-popup__state"><strong>Корзина пока пуста</strong><span>Добавьте товар в каталоге или соберите подарок.</span></div>}

          {!loading && !error && cart && itemCount > 0 && <div className="mini-cart-popup__items">
            {cart.items.slice(0, 4).map((item) => <article key={`item-${item.id}`} className="mini-cart-popup__item">
              <div className="mini-cart-popup__thumb">{item.product.image ? <img src={item.product.image} alt="" /> : <span aria-hidden="true">🍵</span>}</div>
              <div className="mini-cart-popup__copy"><strong>{item.product.name}</strong><span>{itemQuantity(item.quantity, item.stock_unit)}</span></div>
              <strong className="mini-cart-popup__price">{money.format(item.total_price)}</strong>
            </article>)}
            {(cart.gifts ?? []).slice(0, 2).map((gift) => <article key={`gift-${gift.id}`} className="mini-cart-popup__item">
              <div className="mini-cart-popup__thumb"><span aria-hidden="true">🎁</span></div>
              <div className="mini-cart-popup__copy"><strong>{gift.name}</strong><span>{gift.quantity} шт.</span></div>
              <strong className="mini-cart-popup__price">{money.format(gift.prices.total_price)}</strong>
            </article>)}
            {itemCount > 6 && <p className="mini-cart-popup__more">Ещё позиций: {itemCount - 6}</p>}
          </div>}
        </div>

        {!loading && !error && cart && itemCount > 0 && <footer className="mini-cart-popup__footer">
          <div><span>Итого</span><strong>{money.format(cart.final_total)}</strong></div>
          <Button variant="contained" onClick={goToCart}>Открыть корзину</Button>
        </footer>}
      </section>
    </Popover>
  );
}