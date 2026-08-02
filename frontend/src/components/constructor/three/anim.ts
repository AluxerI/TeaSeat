/**
 * Математика и хуки для хореографии 3D-сцены конструктора.
 *
 * Ключевая идея: многотактовые анимации («влетел → раскрылся → насыпался →
 * закрылся → упал») нельзя выразить через useSpring от пропса, поэтому здесь
 * живёт секвенсор — он гоняет время через useFrame и отдаёт номер текущего
 * такта плюс нормализованный прогресс внутри него.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useFrame } from "@react-three/fiber";
import * as THREE from "three";

// ── Easings ─────────────────────────────────────────────────────────────────

export const easeInOutCubic = (t: number): number =>
  t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

export const easeOutCubic = (t: number): number => 1 - Math.pow(1 - t, 3);

export const easeInCubic = (t: number): number => t * t * t;

export const easeOutQuad = (t: number): number => 1 - (1 - t) * (1 - t);

/** Перелёт с возвратом — для «щелчка» банта и посадки крышки */
export const easeOutBack = (t: number, overshoot = 1.7): number => {
  const c3 = overshoot + 1;
  const p = t - 1;
  return 1 + c3 * p * p * p + overshoot * p * p;
};

/** Затухающие колебания — для лёгкого «дребезга» после удара */
export const easeOutElastic = (t: number): number => {
  if (t === 0 || t === 1) return t;
  const c4 = (2 * Math.PI) / 3;
  return Math.pow(2, -10 * t) * Math.sin((t * 10 - 0.75) * c4) + 1;
};

/** Отскок — падение предмета в коробку */
export const easeOutBounce = (t: number): number => {
  const n1 = 7.5625;
  const d1 = 2.75;
  if (t < 1 / d1) return n1 * t * t;
  if (t < 2 / d1) {
    const p = t - 1.5 / d1;
    return n1 * p * p + 0.75;
  }
  if (t < 2.5 / d1) {
    const p = t - 2.25 / d1;
    return n1 * p * p + 0.9375;
  }
  const p = t - 2.625 / d1;
  return n1 * p * p + 0.984375;
};

// ── Скалярные помощники ─────────────────────────────────────────────────────

export const lerp = (a: number, b: number, t: number): number => a + (b - a) * t;

export const clamp = (v: number, min: number, max: number): number =>
  v < min ? min : v > max ? max : v;

export const clamp01 = (v: number): number => clamp(v, 0, 1);

/**
 * Ремап значения из диапазона [inMin, inMax] в [0, 1] с обрезкой по краям.
 * Удобно, когда одна анимация должна стартовать в середине такта.
 */
export const remap01 = (v: number, inMin: number, inMax: number): number =>
  inMax === inMin ? (v >= inMax ? 1 : 0) : clamp01((v - inMin) / (inMax - inMin));

/**
 * Кадронезависимое демпфирование. Классический приём из three.js:
 * вместо lerp(current, target, 0.1) — экспоненциальный доезд, который
 * не зависит от FPS. Обёртка над THREE.MathUtils.damp для читаемости.
 */
export const damp = (
  current: number,
  target: number,
  lambda: number,
  delta: number,
): number => THREE.MathUtils.damp(current, target, lambda, delta);

/** То же для углов — идёт кратчайшим путём через ±π */
export const dampAngle = (
  current: number,
  target: number,
  lambda: number,
  delta: number,
): number => {
  const diff = shortestAngle(current, target);
  return current + diff * (1 - Math.exp(-lambda * delta));
};

/** Кратчайшая угловая разница target − current, приведённая к [−π, π] */
export const shortestAngle = (current: number, target: number): number => {
  let diff = (target - current) % (Math.PI * 2);
  if (diff > Math.PI) diff -= Math.PI * 2;
  if (diff < -Math.PI) diff += Math.PI * 2;
  return diff;
};

// ── Траектории ──────────────────────────────────────────────────────────────

const _bezierTmp = new THREE.Vector3();

/**
 * Квадратичная кривая Безье — дуга, по которой предмет летит в коробку.
 * Пишет результат в `out`, чтобы не аллоцировать вектор каждый кадр.
 */
export function quadBezier(
  out: THREE.Vector3,
  p0: THREE.Vector3,
  p1: THREE.Vector3,
  p2: THREE.Vector3,
  t: number,
): THREE.Vector3 {
  const inv = 1 - t;
  const a = inv * inv;
  const b = 2 * inv * t;
  const c = t * t;
  out.set(
    a * p0.x + b * p1.x + c * p2.x,
    a * p0.y + b * p1.y + c * p2.y,
    a * p0.z + b * p1.z + c * p2.z,
  );
  return out;
}

/**
 * Контрольная точка для дуги между двумя точками: середина, поднятая на `lift`.
 * Даёт естественный навесной бросок вместо прямой линии.
 */
export function arcControl(
  out: THREE.Vector3,
  from: THREE.Vector3,
  to: THREE.Vector3,
  lift: number,
): THREE.Vector3 {
  out.copy(from).add(to).multiplyScalar(0.5);
  out.y = Math.max(from.y, to.y) + lift;
  return out;
}

/** Точка на дуге за один вызов — для случаев, где контрольная точка не нужна снаружи */
export function arcPoint(
  out: THREE.Vector3,
  from: THREE.Vector3,
  to: THREE.Vector3,
  lift: number,
  t: number,
): THREE.Vector3 {
  arcControl(_bezierTmp, from, to, lift);
  return quadBezier(out, from, _bezierTmp, to, t);
}

// ── Доступность ─────────────────────────────────────────────────────────────

/**
 * Уважаем prefers-reduced-motion: секвенсор в этом режиме проскакивает
 * такты мгновенно, камера ставится в целевую точку без демпфирования.
 */
export function usePrefersReducedMotion(): boolean {
  const [reduced, setReduced] = useState(() => {
    if (typeof window === "undefined" || !window.matchMedia) return false;
    return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  });

  useEffect(() => {
    if (typeof window === "undefined" || !window.matchMedia) return;
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    const onChange = (e: MediaQueryListEvent) => setReduced(e.matches);
    mq.addEventListener("change", onChange);
    return () => mq.removeEventListener("change", onChange);
  }, []);

  return reduced;
}

// ── Секвенсор ───────────────────────────────────────────────────────────────

/**
 * Состояние проигрывания последовательности тактов.
 * Живёт в ref, а не в state: обновляется каждый кадр, ререндеры не нужны.
 */
export interface SequenceState {
  /** Индекс текущего такта; равен beats.length, когда всё доиграно */
  beat: number;
  /** Прогресс внутри текущего такта, 0..1 */
  t: number;
  /** Секунд с начала всей последовательности */
  elapsed: number;
  /** Общий прогресс по всей последовательности, 0..1 */
  progress: number;
  /** Последовательность доиграна до конца */
  finished: boolean;
}

export interface SequenceHandle {
  /** Читать в useFrame; объект переиспользуется, копируй значения, а не ссылку */
  readonly state: Readonly<SequenceState>;
  /**
   * Прогресс конкретного такта: 0 — ещё не начался, 1 — уже прошёл,
   * 0..1 — идёт сейчас. Основной способ читать секвенсор из компонентов.
   */
  beatProgress(index: number): number;
  /** Начался ли такт (в т.ч. уже завершён) */
  beatStarted(index: number): boolean;
  /** Перезапустить с нуля */
  restart(): void;
}

/**
 * Проигрывает список тактов, пока `active`. По завершении последнего такта
 * один раз дёргает `onComplete`.
 *
 * @param beats     длительности тактов в секундах
 * @param active    идёт ли проигрывание; переход false→true перезапускает
 * @param onComplete вызывается один раз после последнего такта
 * @param resetKey  смена значения перезапускает последовательность
 *                  (нужно, чтобы два чая подряд отыграли независимо)
 */
export function useSequence(
  beats: readonly number[],
  active: boolean,
  onComplete?: () => void,
  resetKey?: string | number,
): SequenceHandle {
  const reduced = usePrefersReducedMotion();

  const stateRef = useRef<SequenceState>({
    beat: 0,
    t: 0,
    elapsed: 0,
    progress: 0,
    finished: false,
  });

  const completedRef = useRef(false);
  const onCompleteRef = useRef(onComplete);
  onCompleteRef.current = onComplete;

  const total = useMemo(() => beats.reduce((a, b) => a + b, 0), [beats]);

  const reset = useCallback(() => {
    const s = stateRef.current;
    s.beat = 0;
    s.t = 0;
    s.elapsed = 0;
    s.progress = 0;
    s.finished = false;
    completedRef.current = false;
  }, []);

  // Перезапуск на входе в фазу и при смене resetKey.
  useEffect(() => {
    if (active) reset();
  }, [active, resetKey, reset]);

  useFrame((_, rawDelta) => {
    if (!active || completedRef.current) return;

    const s = stateRef.current;

    // Клампим дельту: после переключения вкладки прилетает огромный кадр,
    // без клампа анимация просто телепортируется в конец.
    const delta = reduced ? total : Math.min(rawDelta, 0.05);
    s.elapsed += delta;

    if (s.elapsed >= total) {
      s.elapsed = total;
      s.beat = beats.length;
      s.t = 1;
      s.progress = 1;
      s.finished = true;
      completedRef.current = true;
      onCompleteRef.current?.();
      return;
    }

    s.progress = total > 0 ? s.elapsed / total : 1;

    let acc = 0;
    for (let i = 0; i < beats.length; i++) {
      const end = acc + beats[i];
      if (s.elapsed < end) {
        s.beat = i;
        s.t = beats[i] > 0 ? (s.elapsed - acc) / beats[i] : 1;
        break;
      }
      acc = end;
    }
  });

  const beatProgress = useCallback(
    (index: number): number => {
      const s = stateRef.current;
      if (s.finished) return 1;
      if (index < s.beat) return 1;
      if (index > s.beat) return 0;
      return s.t;
    },
    [],
  );

  const beatStarted = useCallback((index: number): boolean => {
    const s = stateRef.current;
    return s.finished || s.beat >= index;
  }, []);

  return useMemo(
    () => ({
      state: stateRef.current,
      beatProgress,
      beatStarted,
      restart: reset,
    }),
    [beatProgress, beatStarted, reset],
  );
}
