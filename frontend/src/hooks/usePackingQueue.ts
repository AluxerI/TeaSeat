import { useCallback, useEffect, useRef, useState, type MutableRefObject } from "react";
import { usePageVisibility } from "./usePageVisibility";
import { packingDuration, type PackingEntry } from "../utils/simplePacking";

/** CSS рисует движение; JS переключает только готовые позиции, без кадрового цикла. */
export function usePackingQueue(entries: PackingEntry[], seen: MutableRefObject<Set<string>>) {
  const visible = usePageVisibility();
  const [reduced, setReduced] = useState(() => window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false);
  const [, refresh] = useState(0);
  const latest = useRef(entries);
  latest.current = entries;
  const clock = useRef<{ key: string; remaining: number } | null>(null);
  const active = entries.find((entry) => entry.key === clock.current?.key && !seen.current.has(entry.key))
    ?? entries.find((entry) => !seen.current.has(entry.key));

  useEffect(() => {
    const media = window.matchMedia?.("(prefers-reduced-motion: reduce)");
    if (!media) return;
    const change = () => setReduced(media.matches);
    media.addEventListener?.("change", change);
    return () => media.removeEventListener?.("change", change);
  }, []);

  useEffect(() => {
    const keys = new Set(entries.map((entry) => entry.key));
    for (const key of seen.current) if (!keys.has(key)) seen.current.delete(key);
    if (reduced && entries.some((entry) => !seen.current.has(entry.key))) {
      for (const entry of entries) seen.current.add(entry.key);
      clock.current = null; refresh((version) => version + 1);
    }
  }, [entries, seen, reduced]);

  const complete = useCallback((key: string) => {
    if (clock.current?.key !== key || seen.current.has(key) || !latest.current.some((entry) => entry.key === key)
      || document.visibilityState !== "visible") return;
    seen.current.add(key); clock.current = null; refresh((version) => version + 1);
  }, [seen]);

  useEffect(() => {
    if (!active || reduced) { clock.current = null; return; }
    if (clock.current?.key !== active.key) clock.current = { key: active.key, remaining: packingDuration(active.role) * 1000 + 80 };
    if (!visible) return;
    const current = clock.current;
    const started = performance.now();
    // Страховка, если animationend не пришёл (например, пользователь отключил CSS).
    // Скрытие вкладки сохраняет остаток; удаление позиции/уход со страницы отменяют таймер.
    const timer = window.setTimeout(() => complete(current.key), current.remaining);
    return () => {
      clearTimeout(timer);
      if (clock.current === current) current.remaining = Math.max(0, current.remaining - Math.max(0, performance.now() - started));
    };
  }, [active?.key, active?.role, visible, reduced, complete]);

  return { activeKey: active?.key ?? null, paused: !visible, reduced, complete };
}
