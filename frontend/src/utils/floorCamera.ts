/** Координаты единственного перехода к дну. Нет времени/азимута для постоянного вращения. */
export function floorCameraPosition(progress: number, width: number, height: number): [number, number, number] {
  const clamped = Math.max(0, Math.min(1, progress));
  const distance = Math.max(width, height, 8);
  return [0, distance, Math.pow(1 - clamped, 3) * distance * 1.3];
}

/** До подтверждения коробки камера неподвижна; изменение каталога её не запускает. */
export function advanceFloorCamera(progress: number, delta: number, editing: boolean, reducedMotion: boolean): number {
  if (!editing) return 0;
  if (reducedMotion) return 1;
  return Math.min(1, progress + Math.max(0, Math.min(delta, .05)) / .8);
}
