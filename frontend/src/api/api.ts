import axios from "axios";

const API_URL = import.meta.env.VITE_API_URL || "";

/** Настроенный Axios-инстанс для всех API-запросов.
 *  - baseURL из VITE_API_URL (по умолчанию пусто — тот же origin)
 *  - withCredentials: true для Sanctum SPA-аутентификации
 *  - CSRF через XSRF-TOKEN cookie
 *  - Bearer-токен автоматически проставляется в interceptor */
export const api = axios.create({
  baseURL: API_URL,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
  xsrfCookieName: "XSRF-TOKEN",
  xsrfHeaderName: "X-XSRF-TOKEN",
  withCredentials: true,
});

/** Перехватчик запросов — добавляет Bearer-токен из localStorage */
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("auth_token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
