import { act, renderHook, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { testBox, testQuote, testSize, testSizes } from "../components/constructor/testFixtures";

const mocks = vi.hoisted(() => ({ quoteSimple: vi.fn() }));
vi.mock("../api/giftConstructorAPI", () => ({ giftConstructorApi: mocks }));
import { useSimpleGiftQuote } from "./useSimpleGiftQuote";

const teas = [11, 11, 11, 11, 11], sweets = [21, 21], empty: number[] = [];
const offline = (value: boolean) => act(() => {
  Object.defineProperty(navigator, "onLine", { configurable: true, value: !value });
  window.dispatchEvent(new Event(value ? "offline" : "online"));
});
beforeEach(() => {
  Object.defineProperty(navigator, "onLine", { configurable: true, value: true });
  mocks.quoteSimple.mockReset().mockResolvedValue(testQuote);
});

describe("early simple quote lifecycle", () => {
  it("запрашивает расчёт после заполнения, не ожидая анимацию или кнопку оформления", async () => {
    const { result, rerender } = renderHook(({ selected }) => useSimpleGiftQuote(testBox, testSizes, selected, sweets, true), { initialProps: { selected: empty } });
    expect(mocks.quoteSimple).not.toHaveBeenCalled();
    rerender({ selected: teas });
    expect(result.current.approved).toBeNull();
    expect(result.current.loading).toBe(true);
    await waitFor(() => expect(result.current.approved?.layout).toHaveLength(7));
    expect(mocks.quoteSimple).toHaveBeenCalledWith({ box_profile_id: 4, tea_product_size_ids: teas, sweet_product_size_ids: sweets, quantity: 1 }, expect.any(AbortSignal));
    expect(mocks.quoteSimple).toHaveBeenCalledOnce();
  });

  it("не отправляет заведомо слишком крупный состав", () => {
    const sizes = [testSize(11, "tea", 2, 2), testSizes[1]];
    const { result } = renderHook(() => useSimpleGiftQuote(testBox, sizes, teas, sweets, true));
    expect(result.current.localError).toMatch(/мало места/);
    expect(result.current.approved).toBeNull();
    expect(mocks.quoteSimple).not.toHaveBeenCalled();
  });

  it.each(["Выбранные товары невозможно разместить в этой коробке", "Вес подарка превышает ограничение коробки на 200 г", "Недостаточно товара на складе"])("показывает ответ сервера: %s", async (message) => {
    mocks.quoteSimple.mockRejectedValue({ response: { status: 422, data: { message } } });
    const { result } = renderHook(() => useSimpleGiftQuote(testBox, testSizes, teas, sweets, true));
    await waitFor(() => expect(result.current.error).toBe(message));
    expect(result.current.approved).toBeNull();
    expect(result.current.loading).toBe(false);
  });

  it("отменяет старый запрос сразу при изменении выбора и игнорирует поздний ответ", async () => {
    let finish!: (value: typeof testQuote) => void;
    mocks.quoteSimple.mockReturnValue(new Promise((resolve) => { finish = resolve; }));
    const { result, rerender } = renderHook(({ selected }) => useSimpleGiftQuote(testBox, testSizes, selected, sweets, true), { initialProps: { selected: teas } });
    const signal = mocks.quoteSimple.mock.calls[0][1] as AbortSignal;
    rerender({ selected: empty });
    expect(signal.aborted).toBe(true);
    await act(async () => finish(testQuote));
    expect(result.current.approved).toBeNull();
    expect(result.current.complete).toBe(false);
  });

  it("после изменения сторон профиля не доверяет прежней цене даже при тех же id", async () => {
    const { result, rerender } = renderHook(({ sizes }) => useSimpleGiftQuote(testBox, sizes, teas, sweets, true), { initialProps: { sizes: testSizes } });
    await waitFor(() => expect(result.current.approved).not.toBeNull());
    rerender({ sizes: [testSize(11, "tea", 2, 2), testSizes[1]] });
    expect(result.current.approved).toBeNull();
    expect(result.current.error).toMatch(/мало места/);
  });

  it("offline сразу отзывает допуск; reconnect ждёт свежий расчёт и не скрывает ошибку старой ценой", async () => {
    const { result } = renderHook(() => useSimpleGiftQuote(testBox, testSizes, teas, sweets, true));
    await waitFor(() => expect(result.current.approved).not.toBeNull());
    offline(true);
    expect(result.current.approved).toBeNull();
    expect(result.current.error).toMatch(/Нет соединения/);
    mocks.quoteSimple.mockRejectedValue({ response: { status: 422, data: { message: "Товар закончился" } } });
    offline(false);
    expect(result.current.approved).toBeNull();
    await waitFor(() => expect(result.current.error).toBe("Товар закончился"));
    expect(result.current.approved).toBeNull();
    expect(mocks.quoteSimple).toHaveBeenCalledTimes(2);
  });

  it("повторяет проверку после ошибки только по явному запросу", async () => {
    mocks.quoteSimple.mockRejectedValueOnce({ response: { status: 500 } });
    const { result } = renderHook(() => useSimpleGiftQuote(testBox, testSizes, teas, sweets, true));
    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(result.current.approved).toBeNull();
    act(() => result.current.retry());
    await waitFor(() => expect(result.current.approved).not.toBeNull());
    expect(mocks.quoteSimple).toHaveBeenCalledTimes(2);
  });

  it("не принимает valid=true с повреждённым layout", async () => {
    mocks.quoteSimple.mockResolvedValue({ ...testQuote, layout: [] });
    const { result } = renderHook(() => useSimpleGiftQuote(testBox, testSizes, teas, sweets, true));
    await waitFor(() => expect(result.current.error).toMatch(/Некорректный ответ API/));
    expect(result.current.approved).toBeNull();
  });

  it("не работает в ручном/скрытом режиме; уход отменяет запрос", () => {
    mocks.quoteSimple.mockReturnValue(new Promise(() => {}));
    const { result, rerender, unmount } = renderHook(({ enabled }) => useSimpleGiftQuote(testBox, testSizes, teas, sweets, enabled), { initialProps: { enabled: false } });
    expect(mocks.quoteSimple).not.toHaveBeenCalled();
    expect(result.current.error).toBe("");
    rerender({ enabled: true });
    const first = mocks.quoteSimple.mock.calls[0][1] as AbortSignal;
    rerender({ enabled: false });
    expect(first.aborted).toBe(true);
    rerender({ enabled: true });
    const second = mocks.quoteSimple.mock.calls[1][1] as AbortSignal;
    unmount();
    expect(second.aborted).toBe(true);
  });
});
