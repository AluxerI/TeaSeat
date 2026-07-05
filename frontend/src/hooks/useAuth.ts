import { useContext } from "react";
import { AuthContext, AuthContextValue } from "../contexts/AuthContext";

/** Хук доступа к контексту аутентификации.
 *  Должен использоваться внутри <AuthProvider>.
 *  Возвращает { user, loading, isAuthenticated, isAdmin, login, logout, refreshUser }. */
export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
