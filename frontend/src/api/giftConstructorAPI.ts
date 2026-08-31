import { api } from "./api";
import { unwrapData } from "./unwrap";
import type {
  Gift,
  SimpleConstructorOptions,
  SimpleGiftCreateRequest,
  SimpleGiftQuote,
  SimpleGiftQuoteRequest,
} from "../interfaces/giftConstructor";

const SIMPLE_PATH = "/api/gift-constructor/simple";

export const giftConstructorApi = {
  async getSimpleOptions(): Promise<SimpleConstructorOptions> {
    const response = await api.get<
      SimpleConstructorOptions | { data: SimpleConstructorOptions }
    >(`${SIMPLE_PATH}/options`);
    return unwrapData(response.data);
  },

  async quoteSimple(request: SimpleGiftQuoteRequest): Promise<SimpleGiftQuote> {
    const response = await api.post<SimpleGiftQuote | { data: SimpleGiftQuote }>(
      `${SIMPLE_PATH}/quote`,
      request,
    );
    return unwrapData(response.data);
  },

  async createSimpleGift(request: SimpleGiftCreateRequest): Promise<Gift> {
    const response = await api.post<Gift | { data: Gift }>(
      `${SIMPLE_PATH}/gifts`,
      request,
    );
    return unwrapData(response.data);
  },
};
