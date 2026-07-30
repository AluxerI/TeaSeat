/**
 * Поток чаинок, сыплющихся в пакетик.
 *
 * Один InstancedMesh на все частицы: 220 отдельных мешей означали бы 220
 * draw call'ов и заметную просадку на слабых машинах, здесь же — один.
 * Листики — приплюснутые коробочки, а не точки: точки всегда обращены к
 * камере и читаются как пыль, а кувыркающиеся плоскости — как заварка.
 */

import { useEffect, useMemo, useRef } from "react";
import { useFrame } from "@react-three/fiber";
import * as THREE from "three";
import { COLORS, LEAF_COUNT } from "./sceneConfig";
import { clamp01, easeInCubic, easeOutCubic } from "./anim";

export interface PourDrive {
  /** Идёт ли пересыпание прямо сейчас */
  active: boolean;
  /** Прогресс такта пересыпания, 0..1 */
  progress: number;
  /** Откуда сыплется (носик) */
  from: THREE.Vector3;
  /** Куда сыплется (горловина пакетика) */
  to: THREE.Vector3;
}

export const createPourDrive = (): PourDrive => ({
  active: false,
  progress: 0,
  from: new THREE.Vector3(),
  to: new THREE.Vector3(),
});

/** Какую долю такта летит одна чаинка от носика до горловины */
const FLIGHT_FRACTION = 0.34;

interface Leaf {
  /** Момент старта в долях такта */
  t0: number;
  /** Боковой разброс у носика */
  spreadX: number;
  spreadZ: number;
  /** Смещение точки приземления внутри горловины */
  landX: number;
  landZ: number;
  scale: number;
  spinX: number;
  spinY: number;
  spinZ: number;
  phase: number;
}

export default function LeafParticles({ drive }: { drive: React.MutableRefObject<PourDrive> }) {
  const meshRef = useRef<THREE.InstancedMesh>(null);
  const dummy = useMemo(() => new THREE.Object3D(), []);
  const pos = useMemo(() => new THREE.Vector3(), []);

  // Раскладка частиц фиксируется один раз: пересчёт на каждый показ давал бы
  // разный рисунок потока при повторном проигрывании.
  const leaves = useMemo<Leaf[]>(() => {
    const rnd = mulberry32(4242);
    const arr: Leaf[] = [];
    for (let i = 0; i < LEAF_COUNT; i++) {
      arr.push({
        // Старты растянуты так, чтобы последняя чаинка успела долететь
        // до конца такта — иначе поток обрывается в воздухе.
        t0: (i / LEAF_COUNT) * (1 - FLIGHT_FRACTION),
        spreadX: (rnd() - 0.5) * 0.07,
        spreadZ: (rnd() - 0.5) * 0.07,
        landX: (rnd() - 0.5) * 0.2,
        landZ: (rnd() - 0.5) * 0.09,
        scale: 0.7 + rnd() * 0.7,
        spinX: (rnd() - 0.5) * 14,
        spinY: (rnd() - 0.5) * 14,
        spinZ: (rnd() - 0.5) * 14,
        phase: rnd() * Math.PI * 2,
      });
    }
    return arr;
  }, []);

  const geometry = useMemo(() => new THREE.BoxGeometry(0.032, 0.009, 0.019), []);
  const material = useMemo(
    () =>
      new THREE.MeshStandardMaterial({
        color: new THREE.Color(COLORS.teaLeaf),
        roughness: 0.88,
        metalness: 0,
      }),
    [],
  );

  useEffect(
    () => () => {
      geometry.dispose();
      material.dispose();
    },
    [geometry, material],
  );

  // Разнотон заварки: половина чаинок светлее. Задаётся один раз через
  // instanceColor, в каждом кадре пересчитывать нечего.
  useEffect(() => {
    const mesh = meshRef.current;
    if (!mesh) return;
    const dark = new THREE.Color(COLORS.teaLeaf);
    const light = new THREE.Color(COLORS.teaLeafAlt);
    const c = new THREE.Color();
    const rnd = mulberry32(909);
    for (let i = 0; i < LEAF_COUNT; i++) {
      c.copy(rnd() > 0.45 ? dark : light);
      // Небольшая случайная яркость поверх — заварка не бывает однотонной.
      c.multiplyScalar(0.82 + rnd() * 0.36);
      mesh.setColorAt(i, c);
    }
    if (mesh.instanceColor) mesh.instanceColor.needsUpdate = true;
  }, []);

  useFrame(() => {
    const mesh = meshRef.current;
    if (!mesh) return;

    const d = drive.current;
    if (!d.active) {
      if (mesh.visible) mesh.visible = false;
      return;
    }
    mesh.visible = true;

    const t = clamp01(d.progress);
    const { from, to } = d;

    for (let i = 0; i < LEAF_COUNT; i++) {
      const leaf = leaves[i];
      const local = (t - leaf.t0) / FLIGHT_FRACTION;

      if (local <= 0 || local >= 1) {
        // Ещё не вылетела или уже в пакетике — прячем нулевым масштабом.
        dummy.scale.setScalar(0);
        dummy.position.set(0, -999, 0);
        dummy.updateMatrix();
        mesh.setMatrixAt(i, dummy.matrix);
        continue;
      }

      // Горизонталь — равномерно, вертикаль — с ускорением: свободное падение.
      const h = easeOutCubic(local);
      const v = easeInCubic(local);

      pos.set(
        THREE.MathUtils.lerp(from.x + leaf.spreadX, to.x + leaf.landX, h),
        THREE.MathUtils.lerp(from.y, to.y, v),
        THREE.MathUtils.lerp(from.z + leaf.spreadZ, to.z + leaf.landZ, h),
      );

      // Лёгкое рыскание в полёте — струя не идеально прямая.
      pos.x += Math.sin(local * 9 + leaf.phase) * 0.012 * (1 - local);
      pos.z += Math.cos(local * 8 + leaf.phase) * 0.012 * (1 - local);

      dummy.position.copy(pos);
      dummy.rotation.set(
        leaf.spinX * local,
        leaf.spinY * local,
        leaf.spinZ * local,
      );
      // У самой горловины чаинка «уходит внутрь» — гасим масштаб.
      const fade = local > 0.86 ? 1 - (local - 0.86) / 0.14 : 1;
      dummy.scale.setScalar(leaf.scale * fade);
      dummy.updateMatrix();
      mesh.setMatrixAt(i, dummy.matrix);
    }

    mesh.instanceMatrix.needsUpdate = true;
  });

  return (
    <instancedMesh
      ref={meshRef}
      args={[geometry, material, LEAF_COUNT]}
      visible={false}
      castShadow
      // Частицы летят по произвольным траекториям, встроенный frustum culling
      // считает bounding sphere по исходной геометрии и мигает — отключаем.
      frustumCulled={false}
    />
  );
}

// Локальная копия ГПСЧ: тащить его из materials.ts значило бы экспортировать
// внутреннюю деталь того модуля наружу ради двух вызовов.
function mulberry32(seed: number): () => number {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
