import { useEffect, useRef, useState, type PointerEvent } from "react";
import type { LayoutPlacement } from "../interfaces/giftConstructor";

/** Свободный жест мышью/пером/пальцем, результат — только допустимые API четверть-обороты. */
export function useItemRotation(onRotate?: (id: string, rotated: boolean) => void, onBusy?: (busy: boolean) => void) {
  const latest = useRef({ onRotate, onBusy }); latest.current = { onRotate, onBusy };
  const gesture = useRef<{ pointerId: number; item: LayoutPlacement; angle: number; x: number; y: number; source: HTMLElement } | null>(null);
  const [preview, setPreview] = useState(0);
  const [active, setActive] = useState(false);
  useEffect(() => {
    const finish = () => {
      const current = gesture.current; gesture.current = null;
      if (current?.source.hasPointerCapture?.(current.pointerId)) current.source.releasePointerCapture(current.pointerId);
      setActive(false); setPreview(0); latest.current.onBusy?.(false);
    };
    const turns = (event: globalThis.PointerEvent) => {
      const current = gesture.current!;
      const delta = Math.atan2(event.clientY - current.y, event.clientX - current.x) - current.angle;
      return Math.round(Math.atan2(Math.sin(delta), Math.cos(delta)) / (Math.PI / 2));
    };
    const move = (event: globalThis.PointerEvent) => {
      if (event.pointerId !== gesture.current?.pointerId) return;
      event.preventDefault(); setPreview(turns(event) * 90);
    };
    const up = (event: globalThis.PointerEvent) => {
      const current = gesture.current;
      if (!current || current.pointerId !== event.pointerId) return;
      event.preventDefault();
      const change = Math.abs(turns(event)) % 2 === 1;
      finish();
      if (change) latest.current.onRotate?.(current.item.client_item_id, !current.item.is_rotated);
    };
    const cancel = (event: globalThis.PointerEvent) => { if (event.pointerId === gesture.current?.pointerId) finish(); };
    const escape = (event: KeyboardEvent) => { if (event.key === "Escape" && gesture.current) { event.preventDefault(); finish(); } };
    const blur = () => { if (gesture.current) finish(); };
    const hidden = () => { if (document.hidden) blur(); };
    window.addEventListener("pointermove", move, { passive: false });
    window.addEventListener("pointerup", up);
    window.addEventListener("pointercancel", cancel);
    window.addEventListener("lostpointercapture", cancel);
    window.addEventListener("keydown", escape);
    window.addEventListener("blur", blur);
    document.addEventListener("visibilitychange", hidden);
    return () => {
      if (gesture.current) finish();
      window.removeEventListener("pointermove", move); window.removeEventListener("pointerup", up);
      window.removeEventListener("pointercancel", cancel); window.removeEventListener("lostpointercapture", cancel);
      window.removeEventListener("keydown", escape); window.removeEventListener("blur", blur);
      document.removeEventListener("visibilitychange", hidden);
    };
  }, []);
  return { preview, active, start: (event: PointerEvent<HTMLElement>, item: LayoutPlacement, frame: DOMRect) => {
    if (!onRotate || event.button !== 0 || event.isPrimary === false || gesture.current) return;
    event.preventDefault(); event.stopPropagation();
    const x = frame.left + frame.width / 2, y = frame.top + frame.height / 2;
    gesture.current = { pointerId: event.pointerId, source: event.currentTarget, item, x, y,
      angle: Math.atan2(event.clientY - y, event.clientX - x) };
    event.currentTarget.setPointerCapture?.(event.pointerId);
    setActive(true); latest.current.onBusy?.(true);
  } };
}
