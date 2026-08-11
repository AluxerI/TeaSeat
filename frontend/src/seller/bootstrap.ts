import { api } from "../api/api";
import { db, ensureDeviceUuid, readSession, writeSession } from "./db";
import { fetchBootstrap, registerDevice } from "./api";
import type { LocalProduct, WorkLocation } from "./types";

/** Картинки товаров живут только в публичном `/api/catalog`, а bootstrap
 *  отдаёт их без изображений. Мерджим по `id`, чтобы карточка товара не была
 *  серой, пока каталог не догрузился. Офлайн — картинки остаются в кэше. */
function stripOrigin(url: string): string {
  return url.replace(/^https?:\/\/[^/]+/, "");
}

async function catalogImages(): Promise<Map<number, string | null>> {
  try {
    const res = await api.get<{
      data: { products: { id: number; main_image: string }[] };
    }>("/api/catalog");
    const map = new Map<number, string | null>();
    for (const p of res.data.data.products ?? []) {
      map.set(p.id, p.main_image ? stripOrigin(p.main_image) : null);
    }
    return map;
  } catch {
    return new Map();
  }
}

/** Рабочие точки продавца из `GET /api/user`. */
export async function fetchWorkLocations(): Promise<WorkLocation[]> {
  const res = await api.get<{ data: { work_locations?: WorkLocation[] } }>(
    "/api/user"
  );
  return res.data.data.work_locations ?? [];
}

/** Регистрация устройства, bootstrap точки и кэш подписанных цен/остатков.
 *  Регистрация идемпотентна: сервер обновляет `last_warehouse_id`. */
export async function bootstrapWarehouse(
  warehouseId: number,
  deviceName = "Касса"
): Promise<LocalProduct[]> {
  await ensureDeviceUuid();
  await registerDevice(deviceName, warehouseId);
  const data = await fetchBootstrap(warehouseId);

  const images = await catalogImages();
  const products: LocalProduct[] = data.products.map((p) => ({
    ...p,
    warehouse_id: warehouseId,
    image: images.get(p.id) ?? null,
  }));
  await db.products.bulkPut(products);

  const serverMs = Date.parse(data.server_time);
  const ttlHours = data.price_snapshot_ttl_hours ?? 48;
  await writeSession({
    warehouse_id: warehouseId,
    warehouse_name: data.warehouse.name,
    device_name: deviceName,
    snapshot_expires_at: Number.isFinite(serverMs)
      ? new Date(serverMs + ttlHours * 60 * 60 * 1000).toISOString()
      : null,
    last_bootstrap_at: new Date().toISOString(),
  });

  return products;
}

/** Кэш товаров точки для офлайна. */
export async function cachedProducts(
  warehouseId: number
): Promise<LocalProduct[]> {
  return db.products.where("warehouse_id").equals(warehouseId).toArray();
}

export async function currentSession() {
  return readSession();
}
