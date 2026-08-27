import {
  createContext,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { cartApi } from "../api/cartAPI";
import { useAuth } from "../hooks/useAuth";
import type { AddToCartRequest, Cart } from "../interfaces/cart";
import { extractError, translateError } from "../utils/translateError";

export interface CustomerCartContextValue {
  cart: Cart | null;
  loading: boolean;
  initialized: boolean;
  error: string;
  pendingActionKey: string | null;
  miniCartOpen: boolean;
  lastAddedProductId: number | null;
  itemCount: number;
  refreshCart: () => Promise<Cart | null>;
  replaceCart: (cart: Cart) => void;
  addProduct: (request: AddToCartRequest) => Promise<Cart>;
  updateItemQuantity: (itemId: number, quantity: number) => Promise<Cart>;
  removeItem: (itemId: number) => Promise<Cart>;
  openMiniCart: () => void;
  closeMiniCart: () => void;
  clearError: () => void;
}

export const CustomerCartContext = createContext<CustomerCartContextValue | null>(null);

/**
 * Общий источник корзины покупателя.
 *
 * Provider не изменяет объекты Cart вручную. После каждого действия он
 * принимает новый снимок, рассчитанный backend, поэтому цены и скидки не
 * расходятся между карточкой, шапкой, мини-корзиной и страницей корзины.
 */
export function CustomerCartProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const [cart, setCart] = useState<Cart | null>(null);
  const [loading, setLoading] = useState(false);
  const [initialized, setInitialized] = useState(false);
  const [error, setError] = useState("");
  const [pendingActionKey, setPendingActionKey] = useState<string | null>(null);
  const [miniCartOpen, setMiniCartOpen] = useState(false);
  const [lastAddedProductId, setLastAddedProductId] = useState<number | null>(null);
  const requestVersion = useRef(0);

  // Корзина относится к конкретному пользователю. При выходе или смене
  // аккаунта старый снимок нельзя показывать даже на долю секунды.
  useEffect(() => {
    requestVersion.current += 1;
    setCart(null);
    setLoading(false);
    setInitialized(false);
    setError("");
    setPendingActionKey(null);
    setMiniCartOpen(false);
    setLastAddedProductId(null);
  }, [user?.id]);

  const acceptError = useCallback((reason: unknown) => {
    const message = translateError(extractError(reason));
    setError(message);
    return message;
  }, []);

  const refreshCart = useCallback(async (): Promise<Cart | null> => {
    if (!user) return null;

    const version = ++requestVersion.current;
    setLoading(true);
    setError("");
    try {
      const nextCart = await cartApi.getCart();
      if (version === requestVersion.current) setCart(nextCart);
      return nextCart;
    } catch (reason) {
      if (version === requestVersion.current) acceptError(reason);
      throw reason;
    } finally {
      if (version === requestVersion.current) {
        setLoading(false);
        setInitialized(true);
      }
    }
  }, [acceptError, user]);

  const replaceCart = useCallback((nextCart: Cart) => {
    // Прерываем принятие более старого GET /cart, который мог стартовать из
    // шапки одновременно с открытием полной страницы.
    requestVersion.current += 1;
    setLoading(false);
    setInitialized(true);
    setCart(nextCart);
    setError("");
  }, []);

  const runAction = useCallback(async (
    actionKey: string,
    operation: () => Promise<Cart>,
  ): Promise<Cart> => {
    // Результат действия новее фоновой загрузки. Инвалидируем её версию,
    // иначе медленный GET мог бы затереть только что добавленную позицию.
    requestVersion.current += 1;
    setLoading(false);
    setInitialized(true);
    setPendingActionKey(actionKey);
    setError("");
    try {
      const nextCart = await operation();
      setCart(nextCart);
      return nextCart;
    } catch (reason) {
      acceptError(reason);
      throw reason;
    } finally {
      setPendingActionKey((current) => current === actionKey ? null : current);
    }
  }, [acceptError]);

  const addProduct = useCallback(async (request: AddToCartRequest) => {
    const nextCart = await runAction(
      `add:${request.product_id}`,
      () => cartApi.addItem(request),
    );
    setLastAddedProductId(request.product_id);
    setMiniCartOpen(true);
    return nextCart;
  }, [runAction]);

  const updateItemQuantity = useCallback((itemId: number, quantity: number) => (
    runAction(`update:${itemId}`, () => cartApi.updateItemQuantity(itemId, quantity))
  ), [runAction]);

  const removeItem = useCallback((itemId: number) => (
    runAction(`remove:${itemId}`, () => cartApi.removeItem(itemId))
  ), [runAction]);

  const itemCount = useMemo(() => {
    if (!cart) return 0;
    // Для развесного товара нельзя выводить граммы в Badge. Показываем число
    // самостоятельных строк: обычных товаров и собранных подарков.
    return cart.items.length + (cart.gifts?.length ?? 0);
  }, [cart]);

  const value = useMemo<CustomerCartContextValue>(() => ({
    cart,
    loading,
    initialized,
    error,
    pendingActionKey,
    miniCartOpen,
    lastAddedProductId,
    itemCount,
    refreshCart,
    replaceCart,
    addProduct,
    updateItemQuantity,
    removeItem,
    openMiniCart: () => setMiniCartOpen(true),
    closeMiniCart: () => setMiniCartOpen(false),
    clearError: () => setError(""),
  }), [
    addProduct,
    cart,
    error,
    initialized,
    itemCount,
    lastAddedProductId,
    loading,
    miniCartOpen,
    pendingActionKey,
    refreshCart,
    removeItem,
    replaceCart,
    updateItemQuantity,
  ]);

  return (
    <CustomerCartContext.Provider value={value}>
      {children}
    </CustomerCartContext.Provider>
  );
}
