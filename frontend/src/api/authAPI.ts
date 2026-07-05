import { api } from "./api";

const AUTH_PATH = "/api/auth";

/** Запрос на вход */
export interface LoginRequest {
  email: string;
  password: string;
}

/** Запрос на регистрацию */
export interface RegisterRequest {
  name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
}

/** Ответ сервера аутентификации */
export interface AuthResponse {
  token?: string;
  message?: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
}

/** Запрос на восстановление пароля */
export interface ForgotPasswordRequest {
  email: string;
}

/** Запрос на сброс пароля (с токеном из письма) */
export interface ResetPasswordRequest {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

/** Данные для обновления профиля */
export interface UpdateProfileData {
  name?: string;
  email?: string;
  phone?: string | null;
}

/** Данные для смены пароля */
export interface ChangePasswordData {
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
}

export const authApi = {
  /** Получить CSRF-cookie (обязательно перед POST/PUT-запросами Sanctum) */
  async getCsrfCookie(): Promise<void> {
    await api.get("/sanctum/csrf-cookie");
  },

  /** Регистрация нового пользователя */
  async register(data: RegisterRequest): Promise<AuthResponse> {
    await this.getCsrfCookie();
    const response = await api.post<AuthResponse>(
      `${AUTH_PATH}/register`,
      data
    );
    return response.data;
  },

  /** Вход в аккаунт */
  async login(data: LoginRequest): Promise<AuthResponse> {
    await this.getCsrfCookie();
    const response = await api.post<AuthResponse>(`${AUTH_PATH}/login`, data);
    return response.data;
  },

  /** Выход (текущая сессия) */
  async logout(): Promise<void> {
    await api.post(`${AUTH_PATH}/logout`);
  },

  /** Выход из всех устройств */
  async logoutAll(): Promise<void> {
    await api.post(`${AUTH_PATH}/logout-all`);
  },

  /** Удаление аккаунта */
  async deleteAccount(): Promise<void> {
    await api.delete(`${AUTH_PATH}/account-delete`);
  },

  /** Отправить ссылку для восстановления пароля */
  async forgotPassword(data: ForgotPasswordRequest): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>(
      `${AUTH_PATH}/forgot-password`,
      data
    );
    return response.data;
  },

  /** Сбросить пароль по токену из письма */
  async resetPassword(data: ResetPasswordRequest): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>(
      `${AUTH_PATH}/reset-password`,
      data
    );
    return response.data;
  },

  /** Отправить SMS-код подтверждения на телефон */
  async sendVerificationCode(phone: string): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>(
      `${AUTH_PATH}/send-verification-code`,
      { phone }
    );
    return response.data;
  },

  /** Подтвердить телефон через SMS-код */
  async verifyPhone(code: string): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>(
      `${AUTH_PATH}/verify-phone`,
      { code }
    );
    return response.data;
  },

  /** Обновить профиль (имя, email, телефон) */
  async updateProfile(data: UpdateProfileData): Promise<AuthResponse> {
    const response = await api.put<AuthResponse>("/api/user", data);
    return response.data;
  },

  /** Сменить пароль (требуется текущий пароль) */
  async changePassword(
    data: ChangePasswordData
  ): Promise<{ message: string }> {
    const response = await api.put<{ message: string }>(
      "/api/user/password",
      data
    );
    return response.data;
  },
};

/** Сохранить токен в localStorage */
export function setAuthToken(token: string) {
  localStorage.setItem("auth_token", token);
}

/** Получить токен из localStorage */
export function getAuthToken(): string | null {
  return localStorage.getItem("auth_token");
}

/** Удалить токен из localStorage */
export function clearAuthToken() {
  localStorage.removeItem("auth_token");
}

/** Проверить, есть ли токен (не проверяет валидность) */
export function isAuthenticated(): boolean {
  return !!getAuthToken();
}
