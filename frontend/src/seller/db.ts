import Dexie, { type Table } from "dexie";
import type {
  LocalOrder,
  LocalProduct,
  OutboxEvent,
  SellerSession,
} from "./types";

/** Запись таблицы `meta`. Одна строка на ключ — так проще, чем один толстый
 *  объект: сессию и оффсет часов пишут разные места и в разное время. */
export interface MetaRow {
  key: string;
  value: unknown;
}

export class SellerDatabase extends Dexie {
  meta!: Table<MetaRow, string>;
  products!: Table<LocalProduct, [number, number]>;
  orders!: Table<LocalOrder, string>;
  outbox!: Table<OutboxEvent, string>;

  constructor(name = "seller-pwa") {
    super(name);
    // v1 — прежнее самописное хранилище (products/cart/orders в базе SellerDB).
    // Здесь новая база с другим именем, поэтому миграция не нужна: старая
    // корзина не переносится, её содержимое не было заказом.
    //
    // v2/v3 — составной ключ товара по реальному полю `id`: в типе LocalProduct
    // (наследник ApiBootstrapProduct) поле называется `id`, а не `product_id`,
    // поэтому прежний keyPath `[warehouse_id+product_id]` ломал bulkPut с
    // DataError. Dexie не умеет менять первичный ключ в апгрейде (UpgradeError),
    // поэтому сломанную таблицу сначала удаляем (v2), а в v3 создаём заново.
    // В v1 записей в products всё равно не было — bulkPut падал, терять нечего.
    this.version(1).stores({
      meta: "&key",
      products: "[warehouse_id+product_id], warehouse_id, name",
      orders: "&client_order_id, status, server_id, warehouse_id, created_at",
      outbox: "&event_id, client_order_id, state, created_at",
    });
    this.version(2).stores({
      products: null,
    });
    this.version(3).stores({
      meta: "&key",
      products: "[warehouse_id+id], warehouse_id, name",
      orders: "&client_order_id, status, server_id, warehouse_id, created_at",
      outbox: "&event_id, client_order_id, state, created_at",
    });
  }
}

export const db = new SellerDatabase();

const SESSION_KEY = "session";

export const DEFAULT_SESSION: SellerSession = {
  device_uuid: "",
  device_name: "",
  warehouse_id: null,
  warehouse_name: null,
  clock_offset: 0,
  snapshot_expires_at: null,
  last_bootstrap_at: null,
};

export async function readSession(): Promise<SellerSession> {
  const row = await db.meta.get(SESSION_KEY);
  return { ...DEFAULT_SESSION, ...((row?.value as SellerSession) ?? {}) };
}

export async function writeSession(
  patch: Partial<SellerSession>
): Promise<SellerSession> {
  const next = { ...(await readSession()), ...patch };
  await db.meta.put({ key: SESSION_KEY, value: next });
  return next;
}

/** UUID устройства создаётся один раз и живёт до очистки хранилища браузера.
 *  Регистрацию на сервере делает `registerDevice`, здесь только идентификатор. */
export async function ensureDeviceUuid(): Promise<string> {
  const session = await readSession();
  if (session.device_uuid) return session.device_uuid;
  const uuid = crypto.randomUUID();
  await writeSession({ device_uuid: uuid });
  return uuid;
}

/** Полный сброс — для «сменить точку» и для тестов. */
export async function resetSellerDatabase(): Promise<void> {
  await db.transaction("rw", db.meta, db.products, db.orders, db.outbox, async () => {
    await db.meta.clear();
    await db.products.clear();
    await db.orders.clear();
    await db.outbox.clear();
  });
}

/** Человекочитаемые русские сообщения для ошибок IndexedDB/Dexie.
 *  Возвращает `null`, если ошибка не из хранилища — такой случай обрабатывает
 *  вызывающий код (своя обёртка API и т.п.). */
const STORAGE_ERROR_RU: Record<string, string> = {
  DataError:
    "Данные не подходят для локального хранилища (DataError). Обновите страницу или очистите данные сайта.",
  UpgradeError:
    "Не удалось обновить локальное хранилище (UpgradeError). Очистите данные сайта и войдите заново.",
  ConstraintError:
    "Конфликт записей в локальном хранилище (ConstraintError).",
  QuotaExceededError:
    "Локальное хранилище переполнено (QuotaExceededError). Освободите место в браузере.",
  AbortError:
    "Операция с локальным хранилищем прервана (AbortError). Повторите попытку.",
  InvalidStateError:
    "Недопустимое состояние локального хранилища (InvalidStateError). Обновите приложение.",
  NotFoundError:
    "Запись не найдена в локальном хранилище (NotFoundError).",
  ReadOnlyError:
    "Локальное хранилище доступно только для чтения (ReadOnlyError).",
  TransactionInactiveError:
    "Транзакция хранилища завершилась (TransactionInactiveError). Повторите попытку.",
  UnknownError:
    "Неизвестная ошибка локального хранилища (UnknownError).",
  VersionError:
    "Версия хранилища устарела (VersionError). Очистите данные сайта и войдите заново.",
};

export function toStorageErrorMessage(err: unknown): string | null {
  if (!(err instanceof Error) || !STORAGE_ERROR_RU[err.name]) return null;
  return STORAGE_ERROR_RU[err.name];
}
