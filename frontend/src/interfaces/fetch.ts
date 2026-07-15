/** Опции HTTP-запроса с поддержкой таймаута */
export interface RequestOptions extends RequestInit {
  timeout?: number;
}

/** Типизированный ответ API (пустая заглушка) */
export interface ApiResponse<T> {}
