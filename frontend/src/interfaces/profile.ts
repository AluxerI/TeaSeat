import type { ReactNode } from "react";

/** Разделы личного кабинета */
export type MenuKey =
  | "profile"
  | "orders"
  | "addresses"
  | "favorites"
  | "discounts"
  | "reviews";

/** Пункт бокового меню */
export interface MenuItem {
  key: MenuKey;
  label: string;
  icon: ReactNode;
}

/** Форма редактирования профиля */
export interface ProfileFormState {
  fullName: string;
  email: string;
  phone: string;
  birthDate: string;
}

/** Форма смены пароля */
export interface PasswordFormState {
  currentPassword: string;
  newPassword: string;
}
