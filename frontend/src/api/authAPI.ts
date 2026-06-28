import { api } from "./api";

const AUTH_PATH = '/api/auth';

export interface LoginRequest {
    email: string;
    password: string;
}

export interface RegisterRequest {
    name: string;
    email: string;
    phone?: string;
    password: string;
    password_confirmation: string;
}

export interface AuthResponse {
    token?: string;
    message?: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
}

export interface ForgotPasswordRequest {
    email: string;
}

export interface ResetPasswordRequest {
    email: string;
    token: string;
    password: string;
    password_confirmation: string;
}

export const authApi = {
    async getCsrfCookie(): Promise<void> {
        await api.get('/sanctum/csrf-cookie');
    },

    async register(data: RegisterRequest): Promise<AuthResponse> {
        await this.getCsrfCookie();
        const response = await api.post<AuthResponse>(`${AUTH_PATH}/register`, data);
        return response.data;
    },

    async login(data: LoginRequest): Promise<AuthResponse> {
        await this.getCsrfCookie();
        const response = await api.post<AuthResponse>(`${AUTH_PATH}/login`, data);
        return response.data;
    },

    async logout(): Promise<void> {
        await api.post(`${AUTH_PATH}/logout`);
    },

    async logoutAll(): Promise<void> {
        await api.post(`${AUTH_PATH}/logout-all`);
    },

    async deleteAccount(): Promise<void> {
        await api.delete(`${AUTH_PATH}/account-delete`);
    },

    async forgotPassword(data: ForgotPasswordRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>(`${AUTH_PATH}/forgot-password`, data);
        return response.data;
    },

    async resetPassword(data: ResetPasswordRequest): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>(`${AUTH_PATH}/reset-password`, data);
        return response.data;
    },

    async sendVerificationCode(phone: string): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>(`${AUTH_PATH}/send-verification-code`, { phone });
        return response.data;
    },

    async verifyPhone(code: string): Promise<AuthResponse> {
        const response = await api.post<AuthResponse>(`${AUTH_PATH}/verify-phone`, { code });
        return response.data;
    },
};

export function setAuthToken(token: string) {
    localStorage.setItem('auth_token', token);
}

export function getAuthToken(): string | null {
    return localStorage.getItem('auth_token');
}

export function clearAuthToken() {
    localStorage.removeItem('auth_token');
}

export function isAuthenticated(): boolean {
    return !!getAuthToken();
}
