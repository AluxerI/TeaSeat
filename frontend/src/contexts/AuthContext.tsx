import { createContext, useState, useEffect, useCallback, type ReactNode } from "react";
import { api } from "../api/api";
import { getAuthToken, setAuthToken, clearAuthToken } from "../api/authAPI";

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

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchUser = useCallback(async () => {
    const token = getAuthToken();
    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }

    try {
      const res = await api.get<{ data: User }>("/api/user");
      setUser(res.data.data ?? res.data);
    } catch {
      clearAuthToken();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchUser();
  }, [fetchUser]);

  const login = async (token: string) => {
    setAuthToken(token);
    setLoading(true);
    try {
      const res = await api.get<{ data: User }>("/api/user");
      setUser(res.data.data ?? res.data);
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
    clearAuthToken();
    setUser(null);
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
