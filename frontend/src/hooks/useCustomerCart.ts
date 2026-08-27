import { useContext } from "react";
import { CustomerCartContext } from "../contexts/CustomerCartContext";

/** Даёт компонентам единый доступ к серверному снимку корзины. */
export function useCustomerCart() {
  const context = useContext(CustomerCartContext);
  if (!context) {
    throw new Error("useCustomerCart должен использоваться внутри CustomerCartProvider");
  }
  return context;
}
