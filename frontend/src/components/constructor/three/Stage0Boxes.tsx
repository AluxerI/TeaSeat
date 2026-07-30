/**
 * Этап 0: выбор типа набора двумя коробками прямо в 3D.
 *
 * Коробки показаны недособранными — стенки приподняты примерно на треть.
 * Это решает сразу две задачи: силуэт уже читается как коробка, и при этом
 * видно, что её предстоит собрать. Наведение доводит стенки выше и возвращает
 * цвет, клик уводит невыбранный вариант и подвигает выбранный в центр.
 *
 * Момент передачи управления основной коробке сделан бесшовным: к концу
 * анимации выбора коробка стоит в центре с тем же fold и масштабом, с какими
 * стартует фаза assembleBox, поэтому подмена объекта глазом не ловится.
 */

import { useEffect, useMemo, useRef, useState } from "react";
import { useFrame, useThree } from "@react-three/fiber";
import * as THREE from "three";
import GiftBox, { createBoxDrive, type BoxDrive } from "./GiftBox";
import {
  BD,
  BH,
  BW,
  COMPLEX_SCALE,
  STAGE0_DIM_SATURATION,
  STAGE0_HOVER_FOLD,
  STAGE0_OFFSET_X,
  STAGE0_REST_FOLD,
  type GiftType,
} from "./sceneConfig";
import { damp } from "./anim";

interface Stage0BoxesProps {
  /** Что выбрано; null — ещё выбирают */
  selected: GiftType | null;
  onSelect: (type: GiftType) => void;
  /** Пока идёт уход с этапа, клики игнорируем */
  locked?: boolean;
}

/** Насколько сильно коробка кренится к курсору */
const TILT_STRENGTH = { y: 0.34, x: 0.2 };

interface BoxSlotProps {
  type: GiftType;
  baseX: number;
  baseScale: number;
  selected: GiftType | null;
  hovered: boolean;
  onHover: (hovered: boolean) => void;
  onClick: () => void;
  locked: boolean;
}

function BoxSlot({
  type,
  baseX,
  baseScale,
  selected,
  hovered,
  onHover,
  onClick,
  locked,
}: BoxSlotProps) {
  const groupRef = useRef<THREE.Group>(null);
  const drive = useRef<BoxDrive>(
    createBoxDrive({ fold: STAGE0_REST_FOLD, lidLift: 1, saturation: STAGE0_DIM_SATURATION }),
  );
  const pointer = useThree((s) => s.pointer);

  const isChosen = selected === type;
  const isRejected = selected !== null && !isChosen;

  useFrame((_, rawDelta) => {
    const g = groupRef.current;
    if (!g) return;
    const delta = Math.min(rawDelta, 0.05);
    const d = drive.current;

    // ── Цели ────────────────────────────────────────────────────────────────
    const targetX = isChosen ? 0 : isRejected ? baseX * 3.4 : baseX;
    const targetY = hovered && !selected ? 0.12 : 0;
    const targetScale = isRejected ? 0.001 : baseScale;
    const targetSat = hovered || isChosen ? 1 : STAGE0_DIM_SATURATION;
    const targetFold = hovered && !selected ? STAGE0_HOVER_FOLD : STAGE0_REST_FOLD;

    // Отвергнутая коробка уходит резко, чтобы не перекрывать главное действие.
    // Выбранная тоже спешит: к моменту передачи управления основной коробке
    // она должна стоять ровно в центре, иначе подмена объекта будет заметна.
    const lambda = isRejected ? 4.5 : isChosen ? 5.5 : 3.2;

    g.position.x = damp(g.position.x, targetX, lambda, delta);
    g.position.y = damp(g.position.y, targetY, 3.5, delta);

    const s = damp(g.scale.x, targetScale, lambda, delta);
    g.scale.setScalar(s);

    d.saturation = damp(d.saturation, targetSat, 5, delta);
    d.fold = damp(d.fold, targetFold, 4, delta);

    // ── Крен к курсору ──────────────────────────────────────────────────────
    // pointer в NDC (−1..1), поэтому крен не зависит от размера канваса.
    const tiltY = hovered && !selected ? pointer.x * TILT_STRENGTH.y : 0;
    const tiltX = hovered && !selected ? -pointer.y * TILT_STRENGTH.x : 0;
    g.rotation.y = damp(g.rotation.y, tiltY, 6, delta);
    g.rotation.x = damp(g.rotation.x, tiltX, 6, delta);

    // Отвергнутая коробка прячется полностью, иначе крошечный масштаб
    // всё равно ловит лучи и мигает пикселем.
    g.visible = !isRejected || s > 0.01;
  });

  return (
    <group ref={groupRef} position={[baseX, 0, 0]} scale={baseScale}>
      <GiftBox drive={drive} desaturable />

      {/* Область захвата: прозрачный объём вокруг коробки. Ловить лучи самой
          коробкой ненадёжно — между приподнятыми стенками зияют щели. */}
      <mesh
        position={[0, 0.15, 0]}
        onPointerOver={(e) => {
          e.stopPropagation();
          if (!locked && !selected) onHover(true);
        }}
        onPointerOut={(e) => {
          e.stopPropagation();
          if (!locked) onHover(false);
        }}
        onClick={(e) => {
          e.stopPropagation();
          if (!locked && !selected) onClick();
        }}
      >
        <boxGeometry args={[BW * 1.25, BH * 1.9, BD * 1.25]} />
        <meshBasicMaterial transparent opacity={0} depthWrite={false} />
      </mesh>
    </group>
  );
}

export default function Stage0Boxes({ selected, onSelect, locked = false }: Stage0BoxesProps) {
  const [hovered, setHovered] = useState<GiftType | null>(null);
  const gl = useThree((s) => s.gl);

  // Курсор-указатель поверх канваса — иначе непонятно, что коробки кликабельны.
  useEffect(() => {
    const el = gl.domElement;
    const wantPointer = hovered !== null && !locked && selected === null;
    el.style.cursor = wantPointer ? "pointer" : "";
    return () => {
      el.style.cursor = "";
    };
  }, [hovered, locked, selected, gl]);

  // При блокировке сбрасываем наведение: иначе коробка застынет приподнятой.
  useEffect(() => {
    if (locked || selected) setHovered(null);
  }, [locked, selected]);

  const slots = useMemo(
    () =>
      [
        { type: "simplified" as GiftType, baseX: -STAGE0_OFFSET_X, baseScale: 1 },
        { type: "complex" as GiftType, baseX: STAGE0_OFFSET_X, baseScale: COMPLEX_SCALE },
      ],
    [],
  );

  return (
    <group>
      {slots.map((slot) => (
        <BoxSlot
          key={slot.type}
          type={slot.type}
          baseX={slot.baseX}
          baseScale={slot.baseScale}
          selected={selected}
          hovered={hovered === slot.type}
          onHover={(h) => setHovered(h ? slot.type : null)}
          onClick={() => onSelect(slot.type)}
          locked={locked}
        />
      ))}
    </group>
  );
}
