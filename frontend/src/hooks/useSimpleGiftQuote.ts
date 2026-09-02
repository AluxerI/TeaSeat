import { useCallback, useMemo } from "react";
import type { ConstructorProductSize, GiftSizeProfile } from "../interfaces/giftConstructor";
import { giftConstructorApi } from "../api/giftConstructorAPI";
import { simpleSelectionStatus, verifiedSimpleQuote } from "../utils/simpleGiftValidation";
import { useConstructorResource } from "./useConstructorResource";

/** Проверка без создания Gift и без офлайн-очереди. Черновик привязан к пользователю родительским Workspace. */
export function useSimpleGiftQuote(box: GiftSizeProfile, sizes: ConstructorProductSize[], teas: number[], sweets: number[], enabled: boolean) {
  const selection = useMemo(() => ({ box_profile_id: box.id, tea_product_size_ids: teas, sweet_product_size_ids: sweets }), [box.id, teas, sweets]);
  const status = simpleSelectionStatus(box, sizes, selection);
  // Изменение профиля/формата тоже отменяет старое подтверждение, даже если id не поменялся.
  const key = JSON.stringify([selection, box, [...teas, ...sweets].map((id) => sizes.find((size) => size.id === id))]);
  const load = useCallback(async (signal: AbortSignal) => {
    const quote = await giftConstructorApi.quoteSimple({ ...selection, quantity: 1 }, signal);
    return verifiedSimpleQuote(quote, box, sizes, selection);
  }, [box, sizes, selection]);
  const resource = useConstructorResource(key, enabled && status.complete && !status.error, load);
  const error = enabled ? status.error || resource.error : "";
  const approved = enabled && status.complete && !error && !resource.loading && resource.online ? resource.data : null;
  return { ...resource, ...status, error, approved, localError: status.error };
}
