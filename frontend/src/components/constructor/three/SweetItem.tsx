/**
 * Десерт: плитка, которую заворачивают в фольгу и перевязывают лентой.
 *
 * Обёртка — две половинки оболочки, съезжающиеся с боков, а не плоские
 * створки на шарнирах. Плоский лист нельзя согнуть через ребро, поэтому
 * «завернуть» им плитку без каскада вложенных шарниров не выходит; две
 * смыкающиеся половинки дают тот же прочитываемый жест куда честнее —
 * в сомкнутом виде это действительно замкнутая фольга вокруг плитки.
 */

import { useEffect, useMemo, useRef } from "react";
import { useFrame } from "@react-three/fiber";
import { RoundedBox } from "@react-three/drei";
import * as THREE from "three";
import { COLORS } from "./sceneConfig";
import { clamp01, easeOutBack, easeOutCubic, lerp } from "./anim";
import { buildMaterial, buildPlainMaterial, disposeMaterial } from "./materials";

const SW = 0.62;
const SH = 0.2;
const SD = 0.44;
/** Насколько фольга «толще» плитки, чтобы не было z-fighting'а по граням */
const FOIL_PAD = 0.022;

/** Габариты одной половинки оболочки */
const SHELL_W = SW / 2 + FOIL_PAD;
const SHELL_H = SH + FOIL_PAD * 2;
const SHELL_D = SD + FOIL_PAD * 2;

/** Откуда половинки стартуют, пока обёртка раскрыта */
const SHELL_OPEN_X = SW * 0.95;

export interface SweetDrive {
  visible: boolean;
  /** 0 — фольга раскрыта, 1 — плитка завёрнута */
  wrap: number;
  /** 0 — ленты нет, 1 — перевязана */
  ribbon: number;
  position: THREE.Vector3;
  rotation: THREE.Euler;
  scale: number;
}

export const createSweetDrive = (): SweetDrive => ({
  visible: false,
  wrap: 0,
  ribbon: 0,
  position: new THREE.Vector3(),
  rotation: new THREE.Euler(),
  scale: 1,
});

export const SWEET_SIZE = { w: SW, h: SH, d: SD };

export default function SweetItem({ drive }: { drive: React.MutableRefObject<SweetDrive> }) {
  const rootRef = useRef<THREE.Group>(null);
  const leftShellRef = useRef<THREE.Group>(null);
  const rightShellRef = useRef<THREE.Group>(null);
  const ribbonRef = useRef<THREE.Group>(null);

  const chocolate = useMemo(
    () => buildMaterial("chocolate", { repeat: 1, roughness: 0.55, bumpScale: 0.008 }).material,
    [],
  );
  const foil = useMemo(
    () =>
      buildMaterial("foil", {
        repeat: 1,
        roughness: 0.26,
        metalness: 0.72,
        bumpScale: 0.01,
      }).material,
    [],
  );
  const ribbonMat = useMemo(
    () => buildPlainMaterial(COLORS.ribbon, { roughness: 0.45 }).material,
    [],
  );

  useEffect(
    () => () => {
      [chocolate, foil, ribbonMat].forEach(disposeMaterial);
    },
    [chocolate, foil, ribbonMat],
  );

  useFrame(() => {
    const d = drive.current;
    const root = rootRef.current;
    if (!root) return;

    root.visible = d.visible;
    if (!d.visible) return;

    root.position.copy(d.position);
    root.rotation.copy(d.rotation);
    root.scale.setScalar(d.scale);

    const w = easeOutCubic(clamp01(d.wrap));

    // Половинки съезжаются к центру. Каждая накрывает свою половину плитки,
    // сомкнувшись — образуют замкнутую фольгу.
    if (leftShellRef.current) {
      leftShellRef.current.position.x = lerp(-SHELL_OPEN_X, -SHELL_W / 2, w);
      // На подлёте створка слегка развёрнута — будто её ещё поправляют.
      leftShellRef.current.rotation.z = lerp(-0.22, 0, w);
    }
    if (rightShellRef.current) {
      rightShellRef.current.position.x = lerp(SHELL_OPEN_X, SHELL_W / 2, w);
      rightShellRef.current.rotation.z = lerp(0.22, 0, w);
    }

    if (ribbonRef.current) {
      const r = clamp01(d.ribbon);
      ribbonRef.current.visible = r > 0.001;
      // Перелёт даёт ощущение затянутого узла.
      const s = r > 0 ? Math.max(0, easeOutBack(r, 2)) : 0;
      ribbonRef.current.scale.set(s, s, 1);
    }
  });

  return (
    <group ref={rootRef} visible={false}>
      {/* Плитка */}
      <RoundedBox
        args={[SW, SH, SD]}
        radius={0.03}
        smoothness={3}
        material={chocolate}
        castShadow
        receiveShadow
      />

      {/* Половинки фольги */}
      <group ref={leftShellRef} position={[-SHELL_OPEN_X, 0, 0]}>
        <RoundedBox
          args={[SHELL_W, SHELL_H, SHELL_D]}
          radius={0.022}
          smoothness={3}
          material={foil}
          castShadow
        />
      </group>
      <group ref={rightShellRef} position={[SHELL_OPEN_X, 0, 0]}>
        <RoundedBox
          args={[SHELL_W, SHELL_H, SHELL_D]}
          radius={0.022}
          smoothness={3}
          material={foil}
          castShadow
        />
      </group>

      {/* Лента поперёк свёртка */}
      <group ref={ribbonRef} visible={false}>
        <mesh material={ribbonMat} castShadow>
          <boxGeometry args={[0.1, SHELL_H + 0.02, SHELL_D + 0.02]} />
        </mesh>
      </group>
    </group>
  );
}
