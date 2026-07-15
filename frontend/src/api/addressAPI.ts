import { api } from "./api";
import type { Address } from "../interfaces/checkout";

const ADDRESSES_PATH = "/api/addresses";

export interface CreateAddressRequest {
  street: string;
  city: string;
  postal_code: string;
}

export interface CreateAddressResponse {
  message: string;
  address: Address;
}

export const addressApi = {
  /** Получить список адресов текущего пользователя */
  async getAddresses(): Promise<{ addresses: Address[] }> {
    const { data } = await api.get<{ addresses: Address[] }>(ADDRESSES_PATH);
    return data;
  },

  /** Создать новый адрес */
  async createAddress(params: CreateAddressRequest): Promise<CreateAddressResponse> {
    const { data } = await api.post<CreateAddressResponse>(`${ADDRESSES_PATH}/store`, params);
    return data;
  },
};
