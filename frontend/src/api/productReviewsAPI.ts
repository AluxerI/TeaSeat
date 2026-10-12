import { api } from "./api";

export interface ProductReview {
  id: number;
  user_name: string;
  rating: number;
  text: string;
  created_at: string;
}

export interface ProductReviewsResponse {
  reviews: ProductReview[];
  average_rating: number;
  reviews_count: number;
}

export const productReviewsApi = {
  /** Получить отзывы на товар */
  async getReviews(productId: number): Promise<ProductReviewsResponse> {
    const response = await api.get<{ data: ProductReviewsResponse }>(`/api/products/${productId}/reviews`);
    return response.data.data;
  },

  /** Оставить отзыв (требует авторизации) */
  async createReview(productId: number, rating: number, text: string): Promise<ProductReview> {
    const response = await api.post<{ data: ProductReview }>(`/api/products/${productId}/reviews`, { rating, text });
    return response.data.data;
  },
};
