/**
 * Чайный пакетик: бумажный конверт с отгибающимся клапаном, ниткой и ярлыком.
 *
 * Наполнение показано не «внутренним содержимым» (его всё равно не видно),
 * а раздуванием самого пакетика по мере заполнения: пустой конверт плоский,
 * полный — пухлый. Это то, что глаз реально считывает.
 */

import { useEffect, useMemo, useRef } from "react";
import { useFrame } from "@react-three/fiber";
import { RoundedBox } from "@react-three/drei";
import * as THREE from "three";
import { COLORS } from "./sceneConfig";
import { clamp01, easeOutCubic, lerp } from "./anim";
import { buildMaterial, buildPlainMaterial, disposeMaterial } from "./materials";

// ── Габариты ────────────────────────────────────────────────────────────────

const SACHET_W = 0.42;
const SACHET_H = 0.58;
/** Толщина пустого пакетика */
const DEPTH_EMPTY = 0.05;
/** Толщина полного */
const DEPTH_FULL = 0.19;
/** Высота отгибающегося клапана */
const FLAP_H = 0.13;

// ── Управляющие значения ────────────────────────────────────────────────────

export interface SachetDrive {
  /** Пакетик вообще в сцене */
  visible: boolean;
  /** 0 — клапан закрыт, 1 — раскрыт полностью */
  flap: number;
  /** 0 — пустой, 1 — полный */
  fill: number;
  /** Мировая позиция */
  position: THREE.Vector3;
  /** Наклон (радианы) */
  rotation: THREE.Euler;
  /** Общий масштаб — для «схлопывания» после укладки */
  scale: number;
}

export const createSachetDrive = (): SachetDrive => ({
  visible: false,
  flap: 0,
  fill: 0,
  position: new THREE.Vector3(),
  rotation: new THREE.Euler(),
  scale: 1,
});

/** Мировая точка горловины — туда целится поток чаинок */
export function sachetMouth(drive: SachetDrive, out: THREE.Vector3): THREE.Vector3 {
  return out.set(
    drive.position.x,
    drive.position.y + (SACHET_H / 2) * drive.scale,
    drive.position.z,
  );
}

export const SACHET_SIZE = { w: SACHET_W, h: SACHET_H, depthFull: DEPTH_FULL };

// ── Компонент ───────────────────────────────────────────────────────────────

interface TeaSachetProps {
  drive: React.MutableRefObject<SachetDrive>;
  /** Цвет ярлычка — разный у двух сортов, чтобы пакетики различались */
  tagColor?: string;
}

export default function TeaSachet({ drive, tagColor = COLORS.sachetTag }: TeaSachetProps) {
  const rootRef = useRef<THREE.Group>(null);
  const bodyRef = useRef<THREE.Group>(null);
  const flapRef = useRef<THREE.Group>(null);
  const tagRef = useRef<THREE.Group>(null);

  const paper = useMemo(
    () => buildMaterial("sachetPaper", { repeat: 1, roughness: 0.92, bumpScale: 0.006 }).material,
    [],
  );
  const tagMat = useMemo(
    () => buildPlainMaterial(tagColor, { roughness: 0.75 }).material,
    [tagColor],
  );
  const stringMat = useMemo(
    () => buildPlainMaterial(COLORS.sachetString, { roughness: 0.95 }).material,
    [],
  );

  useEffect(
    () => () => {
      [paper, tagMat, stringMat].forEach(disposeMaterial);
    },
    [paper, tagMat, stringMat],
  );

  const stringGeo = useMemo(() => new THREE.CylinderGeometry(0.006, 0.006, 0.3, 6), []);
  useEffect(() => () => stringGeo.dispose(), [stringGeo]);

  useFrame(() => {
    const d = drive.current;
    const root = rootRef.current;
    if (!root) return;

    root.visible = d.visible;
    if (!d.visible) return;

    root.position.copy(d.position);
    root.rotation.copy(d.rotation);
    root.scale.setScalar(d.scale);

    // Раздувание: масштабируем по толщине. RoundedBox имеет фиксированную
    // геометрию, поэтому «пухлость» даём через scale.z самого тела.
    if (bodyRef.current) {
      const fill = easeOutCubic(clamp01(d.fill));
      const depth = lerp(DEPTH_EMPTY, DEPTH_FULL, fill);
      bodyRef.current.scale.z = depth / DEPTH_FULL;
      // Полный пакетик чуть оседает и раздаётся вширь — бумага натягивается.
      bodyRef.current.scale.x = lerp(1, 1.04, fill);
      bodyRef.current.scale.y = lerp(1, 0.98, fill);
    }

    // Клапан отгибается назад через шарнир на верхней кромке.
    if (flapRef.current) {
      flapRef.current.rotation.x = lerp(0, -Math.PI * 0.82, easeOutCubic(clamp01(d.flap)));
    }

    // Ярлык на нитке слегка отстаёт при движении — дешёвая имитация инерции.
    if (tagRef.current) {
      tagRef.current.rotation.z = Math.sin(d.position.x * 3 + d.position.y * 2) * 0.18;
    }
  });

  return (
    <group ref={rootRef} visible={false}>
      {/* Тело конверта */}
      <group ref={bodyRef}>
        <RoundedBox
          args={[SACHET_W, SACHET_H, DEPTH_FULL]}
          radius={0.035}
          smoothness={3}
          material={paper}
          castShadow
          receiveShadow
        />
      </group>

      {/* Клапан: шарнир на верхней кромке, отгибается назад */}
      <group ref={flapRef} position={[0, SACHET_H / 2, 0]}>
        <mesh position={[0, FLAP_H / 2, 0]} material={paper} castShadow>
          <boxGeometry args={[SACHET_W * 0.98, FLAP_H, 0.014]} />
        </mesh>
      </group>

      {/* Нитка с ярлыком */}
      <group position={[SACHET_W / 2 - 0.02, SACHET_H / 2 - 0.02, 0]}>
        <mesh
          geometry={stringGeo}
          material={stringMat}
          position={[0.11, 0.02, 0]}
          rotation={[0, 0, -Math.PI / 2.6]}
        />
        <group ref={tagRef} position={[0.24, -0.06, 0]}>
          <mesh material={tagMat} castShadow>
            <boxGeometry args={[0.13, 0.1, 0.008]} />
          </mesh>
        </group>
      </group>
    </group>
  );
}
