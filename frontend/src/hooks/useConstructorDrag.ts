import { useEffect, useRef, useState, type MouseEvent, type PointerEvent, type TouchEvent } from "react";
import type { ConstructorProductSize, GiftSizeProfile, LayoutPlacement } from "../interfaces/giftConstructor";
import { createGiftInstanceId } from "../utils/constructorErrors";
import { dragScrollDelta, floorPoint } from "../utils/constructorInteraction";
import { footprint, layoutError } from "../utils/giftLayout";

export const CATALOG_HOLD_MS = 400;
export interface DragPreview { placement: LayoutPlacement; valid: boolean; message: string }
interface Args {
  box: GiftSizeProfile;
  sizes: ConstructorProductSize[];
  items: LayoutPlacement[];
  onCommit: (placement: LayoutPlacement, existing: boolean) => void;
  onSelect: (id: string) => void;
  onError: (message: string) => void;
}
interface Point { clientX: number; clientY: number }
interface Gesture {
  id: number; input: "pointer" | "touch"; mode: "pending" | "drag" | "swipe";
  startX: number; startY: number; moved: boolean;
  source: HTMLElement; placement: LayoutPlacement; existing: boolean; offsetX: number; offsetY: number;
  onSwipe?: (direction: -1 | 1) => void;
}

/** Мышь/перо — Pointer Events. Touch-карточка различает скролл, свайп и удержание.
 * Только активный свайп/перенос отменяет native touchmove; вертикальный скролл остаётся браузеру.
 * Команды API здесь не выполняются. */
export function useConstructorDrag(args: Args) {
  const latest = useRef(args);
  latest.current = args;
  const surfaceRef = useRef<HTMLDivElement | null>(null);
  const gesture = useRef<Gesture | null>(null);
  const holdTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const clickTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const suppressClick = useRef(false);
  const [preview, setPreview] = useState<DragPreview | null>(null);
  const [dragging, setDragging] = useState(false);
  const [cursor, setCursor] = useState<(Point & { placement: LayoutPlacement }) | null>(null);

  const release = () => {
    clearTimeout(holdTimer.current);
    const current = gesture.current;
    gesture.current = null;
    if (current?.input === "pointer" && current.source.hasPointerCapture?.(current.id)) current.source.releasePointerCapture(current.id);
  };
  const suppressNextClick = () => {
    suppressClick.current = true;
    clearTimeout(clickTimer.current);
    clickTimer.current = setTimeout(() => { suppressClick.current = false; }, 0);
  };

  useEffect(() => {
    let scrollFrame: number | undefined;
    let lastFrameTime: number | null = null;
    let lastPoint: Point | null = null;
    const stopScroll = () => {
      if (scrollFrame !== undefined) cancelAnimationFrame(scrollFrame);
      scrollFrame = undefined; lastFrameTime = null; lastPoint = null;
    };
    const candidate = (pointEvent: Point): DragPreview | null => {
      const current = gesture.current;
      const surface = surfaceRef.current;
      if (!current || !surface) return null;
      const { box, sizes, items } = latest.current;
      const point = floorPoint(pointEvent.clientX, pointEvent.clientY, surface.getBoundingClientRect(), box);
      if (!point) return null;
      const placement = { ...current.placement, position_x: Math.floor(point.x - current.offsetX), position_y: Math.floor(point.y - current.offsetY) };
      const remaining = items.filter((item) => !current.existing || item.client_item_id !== placement.client_item_id);
      const message = layoutError(box, sizes, [...remaining, placement]);
      const inside = point.x >= 0 && point.y >= 0 && point.x < box.width_cells && point.y < box.height_cells;
      return { placement, valid: inside && !message, message: inside ? message ?? "Можно разместить" : "Перенесите предмет внутрь дна коробки" };
    };
    const scrollStep = (time: number) => {
      scrollFrame = undefined;
      if (!gesture.current?.moved || !lastPoint) return;
      const delta = dragScrollDelta(lastPoint.clientY, window.innerHeight, lastFrameTime === null ? 16 : time - lastFrameTime);
      lastFrameTime = time;
      if (!delta) return;
      const before = window.scrollY;
      window.scrollBy({ top: delta, behavior: "instant" });
      setPreview(candidate(lastPoint));
      // На краю документа тоже останавливаем кадры: нет вечного фонового цикла.
      if (window.scrollY !== before) scrollFrame = requestAnimationFrame(scrollStep);
    };
    const reset = () => { stopScroll(); release(); setPreview(null); setDragging(false); setCursor(null); };
    const cancel = () => { if (gesture.current) suppressNextClick(); reset(); };
    const move = (point: Point, event: Event) => {
      const current = gesture.current;
      if (!current) return;
      const dx = point.clientX - current.startX;
      const dy = point.clientY - current.startY;
      if (current.mode === "pending") {
        if (Math.hypot(dx, dy) < 8) return;
        clearTimeout(holdTimer.current);
        if (Math.abs(dy) >= Math.abs(dx)) { reset(); return; }
        current.mode = "swipe";
      }
      if (current.mode === "swipe") { event.preventDefault(); return; }
      if (!current.moved && Math.hypot(dx, dy) < 5) return;
      current.moved = true;
      suppressClick.current = true;
      event.preventDefault();
      setDragging(true);
      setCursor({ clientX: point.clientX, clientY: point.clientY, placement: current.placement });
      setPreview(candidate(point));
      if (dragScrollDelta(point.clientY, window.innerHeight, 16)) {
        lastPoint = point;
        if (scrollFrame === undefined) scrollFrame = requestAnimationFrame(scrollStep);
      } else stopScroll();
    };
    const up = (point: Point, event: Event) => {
      const current = gesture.current;
      if (!current) return;
      if (current.mode === "swipe") {
        event.preventDefault(); suppressNextClick();
        const dx = point.clientX - current.startX;
        if (Math.abs(dx) >= 40) current.onSwipe?.(dx < 0 ? 1 : -1);
      } else if (current.moved) {
        event.preventDefault(); suppressNextClick();
        const next = candidate(point);
        if (next?.valid) latest.current.onCommit(next.placement, current.existing);
        else latest.current.onError(next?.message ?? "Перенос отменён: отпустите предмет над сеткой.");
      }
      reset();
    };
    const pointerMove = (event: globalThis.PointerEvent) => { if (gesture.current?.input === "pointer" && event.pointerId === gesture.current.id) move(event, event); };
    const pointerUp = (event: globalThis.PointerEvent) => { if (gesture.current?.input === "pointer" && event.pointerId === gesture.current.id) up(event, event); };
    const pointerCancel = (event: globalThis.PointerEvent) => { if (gesture.current?.input === "pointer" && event.pointerId === gesture.current.id) cancel(); };
    const touchMove = (event: globalThis.TouchEvent) => {
      if (gesture.current?.input !== "touch") return;
      if (event.touches.length !== 1) { cancel(); return; }
      const point = Array.from(event.touches).find((touch) => touch.identifier === gesture.current?.id);
      if (point) move(point, event);
    };
    const touchEnd = (event: globalThis.TouchEvent) => {
      if (gesture.current?.input !== "touch") return;
      const point = Array.from(event.changedTouches).find((touch) => touch.identifier === gesture.current?.id);
      if (point) up(point, event);
    };
    const multiTouch = (event: globalThis.TouchEvent) => { if (event.touches.length > 1) cancel(); };
    const escape = (event: KeyboardEvent) => { if (event.key === "Escape" && gesture.current) { event.preventDefault(); cancel(); } };
    const hidden = () => { if (document.hidden) cancel(); };
    window.addEventListener("pointermove", pointerMove, { passive: false });
    window.addEventListener("pointerup", pointerUp);
    window.addEventListener("pointercancel", pointerCancel);
    window.addEventListener("lostpointercapture", pointerCancel);
    window.addEventListener("touchstart", multiTouch, { passive: true });
    window.addEventListener("touchmove", touchMove, { passive: false });
    window.addEventListener("touchend", touchEnd, { passive: false });
    window.addEventListener("touchcancel", cancel);
    window.addEventListener("keydown", escape);
    window.addEventListener("blur", cancel);
    document.addEventListener("visibilitychange", hidden);
    return () => {
      stopScroll(); release(); clearTimeout(clickTimer.current);
      window.removeEventListener("pointermove", pointerMove);
      window.removeEventListener("pointerup", pointerUp);
      window.removeEventListener("pointercancel", pointerCancel);
      window.removeEventListener("lostpointercapture", pointerCancel);
      window.removeEventListener("touchstart", multiTouch);
      window.removeEventListener("touchmove", touchMove);
      window.removeEventListener("touchend", touchEnd);
      window.removeEventListener("touchcancel", cancel);
      window.removeEventListener("keydown", escape);
      window.removeEventListener("blur", cancel);
      document.removeEventListener("visibilitychange", hidden);
    };
  }, []);

  function start(event: PointerEvent<HTMLElement>, placement: LayoutPlacement, existing: boolean) {
    if (event.button !== 0 || event.isPrimary === false || gesture.current) return;
    clearTimeout(clickTimer.current); suppressClick.current = false;
    const point = surfaceRef.current ? floorPoint(event.clientX, event.clientY, surfaceRef.current.getBoundingClientRect(), args.box) : null;
    gesture.current = { id: event.pointerId, input: "pointer", mode: "drag", source: event.currentTarget, placement, existing,
      startX: event.clientX, startY: event.clientY, moved: false,
      offsetX: existing && point ? point.x - placement.position_x : .5,
      offsetY: existing && point ? point.y - placement.position_y : .5 };
    event.currentTarget.setPointerCapture?.(event.pointerId);
    if (existing) args.onSelect(placement.client_item_id);
  }

  function catalogPlacement(size: ConstructorProductSize): LayoutPlacement {
    const [w, h] = footprint(size, false);
    const rotated = Boolean(size.size.can_rotate && (w > args.box.width_cells || h > args.box.height_cells));
    return { client_item_id: createGiftInstanceId(), product_size_id: size.id, position_x: 0, position_y: 0, is_rotated: rotated };
  }

  return {
    surfaceRef, preview, dragging, cursor,
    startItem: (item: LayoutPlacement, event: PointerEvent<HTMLElement>) => start(event, item, true),
    startCatalog: (size: ConstructorProductSize, event: PointerEvent<HTMLElement>) => {
      if (event.pointerType !== "touch") start(event, catalogPlacement(size), false);
    },
    startCatalogTouch: (size: ConstructorProductSize, event: TouchEvent<HTMLElement>, onSwipe: (direction: -1 | 1) => void, canDrag = true) => {
      if (event.touches.length !== 1 || gesture.current) return;
      clearTimeout(clickTimer.current); suppressClick.current = false;
      const touch = event.touches[0];
      const current: Gesture = { id: touch.identifier, input: "touch", mode: "pending", source: event.currentTarget,
        startX: touch.clientX, startY: touch.clientY, moved: false, placement: catalogPlacement(size), existing: false, offsetX: .5, offsetY: .5, onSwipe };
      gesture.current = current;
      if (canDrag) holdTimer.current = setTimeout(() => {
        if (gesture.current !== current || !current.source.isConnected) return;
        current.mode = "drag"; setDragging(true);
        setCursor({ clientX: current.startX, clientY: current.startY, placement: current.placement });
      }, CATALOG_HOLD_MS);
    },
    allowCatalogClick: (event: MouseEvent) => {
      if (!suppressClick.current) return true;
      suppressClick.current = false; event.preventDefault(); event.stopPropagation(); return false;
    },
    preventTouchMenu: (event: MouseEvent) => { if (gesture.current?.input === "touch") event.preventDefault(); },
  };
}
