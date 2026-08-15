import { createContext, useState, useEffect, useCallback, type ReactNode } from "react";
import axios from "axios";
import { api } from "../api/api";
import { getAuthToken, setAuthToken, clearAuthToken } from "../api/authAPI";
import { resetSellerDatabase } from "../seller/db";

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  phone: string | null;
  phone_verified_at: string | null;
  provider: string | null;
  provider_id: string | null;
  is_active: boolean;
  roles: string[];
  permissions: string[];
  stats: {
    orders_count: number;
    reviews_count: number;
  };
  created_at: string;
  updated_at: string;
}

interface AuthState {
  user: User | null;
  loading: boolean;
  isAuthenticated: boolean;
  isAdmin: boolean;
  isSeller: boolean;
  isManager: boolean;
  isCourier: boolean;
}

export interface AuthContextValue extends AuthState {
  login: (token: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | null>(null);

const AUTH_USER_CACHE_KEY = "auth_user_cache";
const PICKER_WAREHOUSE_KEY = "picker_warehouse_id";

function readCachedUser(): User | null {
  try {
    const raw = localStorage.getItem(AUTH_USER_CACHE_KEY);
    return raw ? (JSON.parse(raw) as User) : null;
  } catch {
    return null;
  }
}

async function clearUserScopedState(): Promise<void> {
  await resetSellerDatabase();
  localStorage.removeItem(PICKER_WAREHOUSE_KEY);
  localStorage.removeItem(AUTH_USER_CACHE_KEY);
  if ("caches" in window) {
    const names = await window.caches.keys();
    await Promise.all(
      names
        .filter((name) => name === "teaseat-api" || name.startsWith("teaseat-api-"))
        .map((name) => window.caches.delete(name))
    );
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const acceptUser = useCallback(async (nextUser: User, clearUnknown = false) => {
    const cached = readCachedUser();
    if ((cached && cached.id !== nextUser.id) || (!cached && clearUnknown)) {
      await clearUserScopedState();
    }
    localStorage.setItem(AUTH_USER_CACHE_KEY, JSON.stringify(nextUser));
    setUser(nextUser);
  }, []);

  const fetchUser = useCallback(async () => {
    const token = getAuthToken();
    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }

    try {
      const res = await api.get<{ data: User }>("/api/user");
      await acceptUser(res.data.data ?? res.data);
    } catch (error) {
      const status = axios.isAxiosError(error) ? error.response?.status : undefined;
      if (status === 401) {
        clearAuthToken();
        setUser(null);
      } else {
        // Seller PWA должна открываться после перезагрузки без сети. Права
        // берём из последней успешной авторизации, сервер всё равно проверит
        // токен при следующей синхронизации.
        setUser(readCachedUser());
      }
    } finally {
      setLoading(false);
    }
  }, [acceptUser]);

  useEffect(() => {
    fetchUser();
  }, [fetchUser]);

  const login = async (token: string) => {
    const clearUnknown = !getAuthToken() && !readCachedUser();
    setAuthToken(token);
    setLoading(true);
    try {
      const res = await api.get<{ data: User }>("/api/user");
      await acceptUser(res.data.data ?? res.data, clearUnknown);
    } catch {
      clearAuthToken();
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    try {
      await api.post("/api/auth/logout");
    } catch {
      // ignore
    }
    try {
      await clearUserScopedState();
    } finally {
      clearAuthToken();
      setUser(null);
    }
  };

  const refreshUser = fetchUser;

  const isAuthenticated = !!user;
  const isAdmin = user?.roles?.includes("admin") ?? false;

  const isSeller = user?.roles?.includes("seller") ?? false;
  const isManager = user?.roles?.includes("manager") ?? false;
  const isCourier = user?.roles?.includes("courier") ?? false;

  return (
    <AuthContext.Provider value={{ user, loading, isAuthenticated, isAdmin, isSeller, isManager, isCourier, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export { useAuth } from "../hooks/useAuth";
