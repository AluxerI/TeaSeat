import { api } from "./api";

export interface WishlistProduct {
  id: number;
  name: string;
  image?: string | null;
  image_url?: string | null;
  background_image?: string | null;
  brand?: string | { name?: string | null } | null;
  price?: number | string | null;
  final_price?: number | string | null;
  original_price?: number | string | null;
  discount_percent?: number | null;
  rating_average?: number | null;
  reviews_count?: number | null;
}

export interface WishlistEntry {
  id: number;
  product_id?: number;
  product: WishlistProduct;
  created_at?: string | null;
}

interface WishlistListEnvelope {
  data: WishlistEntry[];
  count?: number;
}

interface WishlistItemEnvelope {
  data: WishlistEntry;
  message?: string;
}

interface WishlistCheckResponse {
  in_wishlist: boolean;
}

const WISHLIST_PATH = "/api/wishlist";

function unwrapList(payload: WishlistEntry[] | WishlistListEnvelope): WishlistEntry[] {
  return Array.isArray(payload) ? payload : Array.isArray(payload.data) ? payload.data : [];
}

export const wishlistApi = {
  async list(): Promise<WishlistEntry[]> {
    const response = await api.get<WishlistEntry[] | WishlistListEnvelope>(WISHLIST_PATH);
    return unwrapList(response.data);
  },

  async add(productId: number): Promise<WishlistEntry | null> {
    const response = await api.post<WishlistEntry | WishlistItemEnvelope>(WISHLIST_PATH, {
      product_id: productId,
    });
    const payload = response.data;
    if ("data" in payload && payload.data) return payload.data;
    return "product" in payload ? payload : null;
  },

  async remove(productId: number): Promise<void> {
    await api.delete(`${WISHLIST_PATH}/${productId}`);
  },

  async check(productId: number): Promise<boolean> {
    const response = await api.get<WishlistCheckResponse | { data: WishlistCheckResponse }>(
      `${WISHLIST_PATH}/check/${productId}`,
    );
    const payload = response.data;
    return "data" in payload ? Boolean(payload.data?.in_wishlist) : Boolean(payload.in_wishlist);
  },
};
