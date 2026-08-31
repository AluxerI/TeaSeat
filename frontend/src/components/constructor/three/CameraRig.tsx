/**
 * Камера конструктора: сферический облёт вокруг коробки с демпфированием.
 *
 * Своя реализация вместо OrbitControls намеренно. Скриптовая камера (кадр под
 * каждую фазу) и OrbitControls дерутся за одну и ту же матрицу: на стыке
 * «пользователь отпустил мышь → начался такт» получается рывок. Здесь единый
 * владелец позиции — этот риг, а пользовательский drag живёт как затухающий
 * оффсет поверх скриптового кадра.
 */

import { useEffect, useRef } from "react";
import { useFrame, useThree } from "@react-three/fiber";
import * as THREE from "three";
import {
  DRAG_LIMITS,
  POLAR_CLAMP,
  type CameraShot,
} from "./sceneConfig";
import { clamp, damp, dampAngle, shortestAngle, usePrefersReducedMotion } from "./anim";

interface CameraRigProps {
  /** Целевой кадр текущей фазы */
  shot: CameraShot;
  /**
   * Можно ли крутить камеру пальцем/мышью. На время скриптовых тактов
   * выключаем, чтобы пользователь не увёл кадр с происходящего.
   */
  interactive?: boolean;
}

/** Порог в пикселях, после которого касание считается вращением, а не скроллом */
const TOUCH_SLOP = 8;

export default function CameraRig({ shot, interactive = false }: CameraRigProps) {
  const camera = useThree((s) => s.camera);
  const gl = useThree((s) => s.gl);
  const reduced = usePrefersReducedMotion();

  // Текущее (сглаженное) состояние камеры.
  const cur = useRef({
    azimuth: shot.azimuth,
    polar: shot.polar,
    radius: shot.radius,
    target: new THREE.Vector3(...shot.target),
  });

  // Пользовательский оффсет поверх скриптового кадра.
  const drag = useRef({ az: 0, polar: 0, active: false });

  const firstFrame = useRef(true);
  const interactiveRef = useRef(interactive);
  interactiveRef.current = interactive;

  // ── Ввод ──────────────────────────────────────────────────────────────────

  useEffect(() => {
    if (!interactive) return;
    const el = gl.domElement;

    // pending — палец опущен, но мы ещё не решили, вращение это или скролл.
    let pending = false;
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let lastX = 0;
    let lastY = 0;
    let pointerId = -1;

    const onDown = (e: PointerEvent) => {
      if (!interactiveRef.current || e.button !== 0) return;
      pointerId = e.pointerId;
      startX = lastX = e.clientX;
      startY = lastY = e.clientY;

      if (e.pointerType === "mouse") {
        // Мышь: вращаем сразу, скроллу это не мешает.
        dragging = true;
        drag.current.active = true;
        el.setPointerCapture(pointerId);
        el.style.cursor = "grabbing";
      } else {
        // Палец: ждём, куда поедет. Вертикаль отдаём странице.
        pending = true;
      }
    };

    const onMove = (e: PointerEvent) => {
      if (e.pointerId !== pointerId) return;

      if (pending) {
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        if (Math.abs(dx) > TOUCH_SLOP && Math.abs(dx) > Math.abs(dy)) {
          // Горизонтальное намерение — забираем жест себе.
          pending = false;
          dragging = true;
          drag.current.active = true;
          el.setPointerCapture(pointerId);
        } else if (Math.abs(dy) > TOUCH_SLOP) {
          // Вертикальное — это скролл страницы, не вмешиваемся.
          pending = false;
          pointerId = -1;
          return;
        } else {
          return;
        }
      }

      if (!dragging) return;

      // Отменяем скролл только когда жест уже наш.
      if (e.cancelable) e.preventDefault();

      const dx = e.clientX - lastX;
      const dy = e.clientY - lastY;
      lastX = e.clientX;
      lastY = e.clientY;

      const d = drag.current;
      d.az = clamp(
        d.az - dx * DRAG_LIMITS.sensitivity,
        -DRAG_LIMITS.azimuth,
        DRAG_LIMITS.azimuth,
      );
      d.polar = clamp(
        d.polar - dy * DRAG_LIMITS.sensitivity,
        -DRAG_LIMITS.polar,
        DRAG_LIMITS.polar,
      );
    };

    const onUp = (e: PointerEvent) => {
      if (e.pointerId !== pointerId) return;
      if (dragging && el.hasPointerCapture(pointerId)) {
        el.releasePointerCapture(pointerId);
      }
      pending = false;
      dragging = false;
      drag.current.active = false;
      pointerId = -1;
      el.style.cursor = "";
    };

    el.addEventListener("pointerdown", onDown);
    // passive: false — иначе preventDefault на тач-move игнорируется.
    el.addEventListener("pointermove", onMove, { passive: false });
    el.addEventListener("pointerup", onUp);
    el.addEventListener("pointercancel", onUp);

    return () => {
      el.removeEventListener("pointerdown", onDown);
      el.removeEventListener("pointermove", onMove);
      el.removeEventListener("pointerup", onUp);
      el.removeEventListener("pointercancel", onUp);
      el.style.cursor = "";
    };
  }, [gl, interactive]);

  // Смена кадра при выключенном взаимодействии — сбрасываем оффсет плавно
  // (само затухание в useFrame), но фиксируем, что жест больше не активен.
  useEffect(() => {
    if (!interactive) drag.current.active = false;
  }, [interactive]);

  // ── Кадр ──────────────────────────────────────────────────────────────────

  useFrame((_, rawDelta) => {
    const delta = Math.min(rawDelta, 0.05);
    const c = cur.current;
    const d = drag.current;
    const lambda = shot.lambda ?? 2;

    // Автоматического облёта больше нет. Позиция меняется только при смене shot.

    // Оффсет затухает к нулю — кадр всегда возвращается к режиссёрскому.
    if (!d.active) {
      d.az = damp(d.az, 0, DRAG_LIMITS.decayLambda, delta);
      d.polar = damp(d.polar, 0, DRAG_LIMITS.decayLambda, delta);
    }

    const targetAz = shot.azimuth + d.az;
    const targetPolar = clamp(shot.polar + d.polar, POLAR_CLAMP[0], POLAR_CLAMP[1]);

    if (firstFrame.current && reduced) {
      // Без анимации — сразу в целевую точку.
      c.azimuth = targetAz;
      c.polar = targetPolar;
      c.radius = shot.radius;
      c.target.set(...shot.target);
      firstFrame.current = false;
    } else if (reduced) {
      c.azimuth = targetAz;
      c.polar = targetPolar;
      c.radius = shot.radius;
      c.target.set(...shot.target);
    } else {
      firstFrame.current = false;
      // Углы демпфируем по кратчайшей дуге: иначе переход через ±π
      // разворачивает камеру «через всю сцену».
      c.azimuth = dampAngle(c.azimuth, targetAz, lambda, delta);
      c.polar = damp(c.polar, targetPolar, lambda, delta);
      c.radius = damp(c.radius, shot.radius, lambda, delta);
      c.target.x = damp(c.target.x, shot.target[0], lambda, delta);
      c.target.y = damp(c.target.y, shot.target[1], lambda, delta);
      c.target.z = damp(c.target.z, shot.target[2], lambda, delta);
    }

    const sinP = Math.sin(c.polar);
    camera.position.set(
      c.target.x + c.radius * sinP * Math.sin(c.azimuth),
      c.target.y + c.radius * Math.cos(c.polar),
      c.target.z + c.radius * sinP * Math.cos(c.azimuth),
    );
    camera.lookAt(c.target);
  });

  return null;
}

/**
 * Текущая угловая «непричёсанность» камеры относительно кадра — пригодится,
 * если понадобится дождаться, пока камера доедет, прежде чем стартовать такт.
 */
export function cameraSettled(
  camera: THREE.Camera,
  shot: CameraShot,
  epsilon = 0.05,
): boolean {
  const target = new THREE.Vector3(...shot.target);
  const sinP = Math.sin(shot.polar);
  const want = new THREE.Vector3(
    target.x + shot.radius * sinP * Math.sin(shot.azimuth),
    target.y + shot.radius * Math.cos(shot.polar),
    target.z + shot.radius * sinP * Math.cos(shot.azimuth),
  );
  return camera.position.distanceTo(want) < epsilon;
}

/** Ре-экспорт, чтобы вызывающему коду не тянуть anim.ts ради одной функции */
export { shortestAngle };
