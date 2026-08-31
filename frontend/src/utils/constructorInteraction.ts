import type { GiftSizeProfile } from "../interfaces/giftConstructor";

export const PREVIEW_ROTATION_LIMIT = Math.PI / 5;
export const clampPreviewRotation = (angle: number) => Math.max(-PREVIEW_ROTATION_LIMIT, Math.min(PREVIEW_ROTATION_LIMIT, angle));

/** Автопрокрутка только у края экрана во время drag; скорость не зависит от FPS. */
export function dragScrollDelta(clientY: number, viewportHeight: number, elapsedMs: number): number {
  if (viewportHeight <= 0) return 0;
  const edge = Math.min(64, viewportHeight / 4);
  const intensity = clientY < edge ? -Math.min(1, (edge - clientY) / edge)
    : clientY > viewportHeight - edge ? Math.min(1, (clientY - viewportHeight + edge) / edge) : 0;
  return intensity * 480 * Math.max(0, Math.min(32, elapsedMs)) / 1000;
}

/** Один расчёт масштаба для ортографической камеры и DOM-слоя перетаскивания. */
export function floorViewport(width: number, height: number, columns: number, rows: number) {
  const zoom = Math.min(width / (columns + 1.4), height / (rows + 1.4));
  return { zoom, width: columns * zoom, height: rows * zoom };
}

export function floorPoint(clientX: number, clientY: number, rect: Pick<DOMRect, "left" | "top" | "width" | "height">, box: GiftSizeProfile) {
  if (rect.width <= 0 || rect.height <= 0) return null;
  return { x: (clientX - rect.left) / rect.width * box.width_cells, y: (clientY - rect.top) / rect.height * box.height_cells };
}

export function formatFootprint(width: number, height: number, cellSizeMm?: number | null): string {
  const cells = `${width} × ${height} кл.`;
  return cellSizeMm && Number.isFinite(cellSizeMm) && cellSizeMm > 0
    ? `${cells} · ${Number((width * cellSizeMm).toFixed(2))} × ${Number((height * cellSizeMm).toFixed(2))} мм`
    : cells;
}
