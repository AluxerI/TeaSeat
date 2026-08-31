/**
 * Подарочная коробка: складывается из плоской развёртки, принимает крышку,
 * обвязывается лентой и бантом.
 *
 * Анимация приходит не через React-пропсы, а через мутируемый ref (`BoxDrive`):
 * значения меняются каждый кадр, и пропсы означали бы 60 ререндеров в секунду.
 * Режиссёр (ThreeScene) пишет в drive, компонент читает его в useFrame.
 */

import { useEffect, useMemo, useRef } from "react";
import { useFrame } from "@react-three/fiber";
import * as THREE from "three";
import {
  BD,
  BH,
  BW,
  COLORS,
  LID_GAP,
  LID_H,
  THICK,
} from "./sceneConfig";
import { clamp01, easeOutBack, easeOutCubic, lerp, remap01 } from "./anim";
import {
  buildMaterial,
  buildPlainMaterial,
  disposeMaterial,
  type SaturationUniform,
} from "./materials";

// ── Управляющие значения ────────────────────────────────────────────────────

export interface BoxDrive {
  /** 0 — плоская развёртка, 1 — коробка собрана */
  fold: number;
  /** 0 — крышка села на коробку, 1 — крышка отведена вверх и назад */
  lidLift: number;
  /** 0 — ленты нет, 1 — лента затянута */
  ribbon: number;
  /** 0 — банта нет, 1 — бант завязан */
  bow: number;
  /** 0 — ч/б, 1 — полный цвет */
  saturation: number;
}

export const createBoxDrive = (init: Partial<BoxDrive> = {}): BoxDrive => ({
  fold: 1,
  lidLift: 1,
  ribbon: 0,
  bow: 0,
  saturation: 1,
  ...init,
});

interface GiftBoxProps {
  drive: React.MutableRefObject<BoxDrive>;
  /** Обесцвечивание нужно только на этапе 0 — патч шейдера не бесплатный */
  desaturable?: boolean;
  position?: [number, number, number];
  scale?: number;
  /** Редактор дна убирает крышку, чтобы она не перекрывала вид сверху. */
  showLid?: boolean;
  /** Содержимое коробки — пакетики и десерт кладутся внутрь этой группы */
  children?: React.ReactNode;
}

// ── Окна тактов складывания ─────────────────────────────────────────────────
// Стенки поднимаются не разом, а внахлёст: сначала задняя, последней передняя.
// Одновременный подъём выглядит механически, лесенка — как живая рука.

const WALL_WINDOW = {
  back: [0.0, 0.45],
  left: [0.12, 0.6],
  right: [0.18, 0.66],
  front: [0.3, 0.8],
} as const;

/** Юбка крышки складывается ближе к концу, уже после стенок корпуса */
const LID_SKIRT_WINDOW = [0.5, 0.88] as const;
/** И только потом крышка отрывается от стола и уходит на парковку */
const LID_PARK_WINDOW = [0.74, 1.0] as const;

/** Лёгкий перелёт: картон пружинит, вставая вертикально */
const WALL_OVERSHOOT = 1.08;

// ── Складная панель ─────────────────────────────────────────────────────────

type HingeAxis = "x" | "z";

interface FoldPanelProps {
  /** Положение шарнира в системе координат родителя */
  hinge: [number, number, number];
  axis: HingeAxis;
  /** Угол в разложенном состоянии (радианы); собранное состояние — всегда 0 */
  flatAngle: number;
  /** Габариты панели: [ширина, высота, толщина] в локальных осях меша */
  size: [number, number, number];
  materials: THREE.Material[];
  /** Сюда родитель кладёт ссылку, чтобы крутить угол в useFrame */
  groupRef: React.MutableRefObject<THREE.Group | null>;
}

/**
 * Панель на шарнире. Меш смещён на половину высоты вверх от точки поворота,
 * поэтому вращение группы вокруг шарнира поднимает панель как настоящий клапан.
 */
function FoldPanel({ hinge, axis, flatAngle, size, materials, groupRef }: FoldPanelProps) {
  const geometry = useMemo(
    () => new THREE.BoxGeometry(size[0], size[1], size[2]),
    [size[0], size[1], size[2]], // eslint-disable-line react-hooks/exhaustive-deps
  );
  useEffect(() => () => geometry.dispose(), [geometry]);

  // Панель растёт вверх от шарнира — вдоль своей «высоты».
  const offset: [number, number, number] = [0, size[1] / 2, 0];

  return (
    <group
      ref={groupRef}
      position={hinge}
      rotation={axis === "x" ? [flatAngle, 0, 0] : [0, 0, flatAngle]}
    >
      <mesh position={offset} material={materials} castShadow receiveShadow>
        <primitive object={geometry} attach="geometry" />
      </mesh>
    </group>
  );
}

// ── Материалы ───────────────────────────────────────────────────────────────

function useBoxMaterials(desaturable: boolean) {
  return useMemo(() => {
    const sats: SaturationUniform[] = [];
    const track = (b: { material: THREE.MeshStandardMaterial; saturation?: SaturationUniform }) => {
      if (b.saturation) sats.push(b.saturation);
      return b.material;
    };

    const outer = track(
      buildMaterial("cardboardOuter", { repeat: 2, desaturable, bumpScale: 0.014 }),
    );
    const inner = track(
      buildMaterial("cardboardInner", { repeat: 2.5, desaturable, bumpScale: 0.016 }),
    );
    const lidTop = track(buildMaterial("lidTop", { repeat: 1, desaturable, bumpScale: 0.02 }));
    const ribbon = track(
      buildPlainMaterial(COLORS.ribbon, { roughness: 0.42, metalness: 0.08, desaturable }),
    );
    const bow = track(
      buildPlainMaterial(COLORS.bow, { roughness: 0.38, metalness: 0.08, desaturable }),
    );

    /**
     * Раскладка материалов по граням BoxGeometry: [+x, -x, +y, -y, +z, -z].
     * Внешняя и внутренняя стороны панели — разные грани одного меша, поэтому
     * достаточно подставить нужный материал в нужный слот.
     */
    const faces = (outerFace: number, innerFace: number): THREE.Material[] => {
      const arr: THREE.Material[] = [inner, inner, inner, inner, inner, inner];
      arr[outerFace] = outer;
      arr[innerFace] = inner;
      return arr;
    };

    return {
      outer,
      inner,
      lidTop,
      ribbon,
      bow,
      saturations: sats,
      // Стенки, тонкие по Z: снаружи +z / -z в зависимости от стороны.
      wallFrontMats: faces(4, 5),
      wallBackMats: faces(5, 4),
      // Стенки, тонкие по X.
      wallRightMats: faces(0, 1),
      wallLeftMats: faces(1, 0),
      // Дно: низ наружу, верх внутрь.
      baseMats: faces(3, 2),
      // Крышка: верх — тиснёная панель, низ — крафт.
      lidTopMats: (() => {
        const arr: THREE.Material[] = [inner, inner, inner, inner, inner, inner];
        arr[2] = lidTop;
        return arr;
      })(),
      lidSkirtFrontMats: faces(4, 5),
      lidSkirtBackMats: faces(5, 4),
      lidSkirtRightMats: faces(0, 1),
      lidSkirtLeftMats: faces(1, 0),
    };
  }, [desaturable]);
}

// ── Коробка ─────────────────────────────────────────────────────────────────

export default function GiftBox({
  drive,
  desaturable = false,
  position = [0, 0, 0],
  scale = 1,
  showLid = true,
  children,
}: GiftBoxProps) {
  const mats = useBoxMaterials(desaturable);

  const baseRef = useRef<THREE.Group>(null);

  const frontRef = useRef<THREE.Group | null>(null);
  const backRef = useRef<THREE.Group | null>(null);
  const leftRef = useRef<THREE.Group | null>(null);
  const rightRef = useRef<THREE.Group | null>(null);

  const lidRef = useRef<THREE.Group>(null);
  const lidSkirtRefs = {
    front: useRef<THREE.Group | null>(null),
    back: useRef<THREE.Group | null>(null),
    left: useRef<THREE.Group | null>(null),
    right: useRef<THREE.Group | null>(null),
  };

  const ribbonRef = useRef<THREE.Group>(null);
  const bowRef = useRef<THREE.Group>(null);
  const contentsRef = useRef<THREE.Group>(null);

  // Освобождаем материалы при размонтировании: текстуры кешируются глобально,
  // а вот сами MeshStandardMaterial создаются на каждый экземпляр коробки.
  useEffect(
    () => () => {
      [mats.outer, mats.inner, mats.lidTop, mats.ribbon, mats.bow].forEach(disposeMaterial);
    },
    [mats],
  );

  // ── Геометрия крышки ──────────────────────────────────────────────────────
  // Крышка садится снаружи корпуса, поэтому шире на зазор и толщину картона.
  const lidW = BW + 2 * (LID_GAP + THICK);
  const lidD = BD + 2 * (LID_GAP + THICK);

  useFrame(() => {
    const d = drive.current;
    const fold = clamp01(d.fold);

    // ── Стенки корпуса ──────────────────────────────────────────────────────
    const wall = (
      groupRef: React.MutableRefObject<THREE.Group | null>,
      axis: HingeAxis,
      flatAngle: number,
      window: readonly [number, number],
    ) => {
      const g = groupRef.current;
      if (!g) return;
      const p = easeOutBack(remap01(fold, window[0], window[1]), WALL_OVERSHOOT);
      const angle = lerp(flatAngle, 0, p);
      if (axis === "x") g.rotation.x = angle;
      else g.rotation.z = angle;
    };

    wall(backRef, "x", -Math.PI / 2, WALL_WINDOW.back);
    wall(leftRef, "z", Math.PI / 2, WALL_WINDOW.left);
    wall(rightRef, "z", -Math.PI / 2, WALL_WINDOW.right);
    wall(frontRef, "x", Math.PI / 2, WALL_WINDOW.front);

    // Дно «проявляется» в самом начале, чтобы развёртка не возникала рывком.
    if (baseRef.current) {
      const s = easeOutCubic(remap01(fold, 0, 0.18));
      baseRef.current.scale.setScalar(lerp(0.85, 1, s));
    }

    // ── Юбка крышки ─────────────────────────────────────────────────────────
    const skirtP = easeOutBack(
      remap01(fold, LID_SKIRT_WINDOW[0], LID_SKIRT_WINDOW[1]),
      WALL_OVERSHOOT,
    );
    const skirt = (
      groupRef: React.MutableRefObject<THREE.Group | null>,
      axis: HingeAxis,
      flatAngle: number,
    ) => {
      const g = groupRef.current;
      if (!g) return;
      const angle = lerp(flatAngle, 0, skirtP);
      if (axis === "x") g.rotation.x = angle;
      else g.rotation.z = angle;
    };
    skirt(lidSkirtRefs.back, "x", Math.PI / 2);
    skirt(lidSkirtRefs.front, "x", -Math.PI / 2);
    skirt(lidSkirtRefs.left, "z", -Math.PI / 2);
    skirt(lidSkirtRefs.right, "z", Math.PI / 2);

    // ── Положение крышки ────────────────────────────────────────────────────
    // Две независимые причины сдвига: незавершённая сборка (крышка ещё лежит
    // на столе рядом с развёрткой) и lidLift (крышка отведена, коробка открыта).
    if (lidRef.current) {
      const parked = easeOutCubic(remap01(fold, LID_PARK_WINDOW[0], LID_PARK_WINDOW[1]));
      // Намеренно без clamp01: небольшой заход в отрицательные значения —
      // это перелёт садящейся крышки, тот самый «пристук». Обрезка по нулю
      // съела бы его и крышка просто останавливалась бы на месте.
      const lift = d.lidLift;

      // Лежит на столе позади развёртки → поднимается к коробке.
      const flatY = -BH / 2 - THICK / 2;
      const flatZ = -(BD / 2 + lidD / 2 + 0.25);

      // Парковка «открытой» крышки: выше коробки и отодвинута назад.
      const parkY = BH / 2 + 1.05;
      const parkZ = -0.55;
      const seatY = BH / 2;

      const y = lerp(flatY, lerp(seatY, parkY, lift), parked);
      const z = lerp(flatZ, lerp(0, parkZ, lift), parked);

      lidRef.current.position.set(0, y, z);
      // Отведённая крышка слегка наклонена — так читается, что она снята.
      lidRef.current.rotation.x = lerp(0, -0.32, lift * parked);
      lidRef.current.rotation.z = lerp(0, 0.06, lift * parked);
    }

    // ── Лента ───────────────────────────────────────────────────────────────
    if (ribbonRef.current) {
      const r = clamp01(d.ribbon);
      ribbonRef.current.visible = r > 0.001;
      // Лента затягивается: растёт из центра крышки к краям и вниз по бокам.
      ribbonRef.current.scale.set(1, easeOutCubic(r), 1);
    }

    // ── Бант ────────────────────────────────────────────────────────────────
    if (bowRef.current) {
      const b = clamp01(d.bow);
      bowRef.current.visible = b > 0.001;
      // Перелёт даёт «щелчок» затянутого узла.
      const s = b > 0 ? easeOutBack(b, 2.4) : 0;
      bowRef.current.scale.setScalar(Math.max(0, s));
    }

    // Содержимое видно, только когда коробка уже собрана.
    if (contentsRef.current) contentsRef.current.visible = fold > 0.8;

    // ── Обесцвечивание ──────────────────────────────────────────────────────
    if (desaturable) {
      for (const u of mats.saturations) u.value = d.saturation;
    }
  });

  return (
    <group position={position} scale={scale}>
      {/* ── Дно ── */}
      <group ref={baseRef}>
        <mesh
          position={[0, -BH / 2 - THICK / 2, 0]}
          material={mats.baseMats}
          castShadow
          receiveShadow
        >
          <boxGeometry args={[BW, THICK, BD]} />
        </mesh>
      </group>

      {/* ── Стенки корпуса ── */}
      <FoldPanel
        groupRef={backRef}
        hinge={[0, -BH / 2, -BD / 2]}
        axis="x"
        flatAngle={-Math.PI / 2}
        size={[BW, BH, THICK]}
        materials={mats.wallBackMats}
      />
      <FoldPanel
        groupRef={frontRef}
        hinge={[0, -BH / 2, BD / 2]}
        axis="x"
        flatAngle={Math.PI / 2}
        size={[BW, BH, THICK]}
        materials={mats.wallFrontMats}
      />
      <FoldPanel
        groupRef={leftRef}
        hinge={[-BW / 2, -BH / 2, 0]}
        axis="z"
        flatAngle={Math.PI / 2}
        size={[THICK, BH, BD]}
        materials={mats.wallLeftMats}
      />
      <FoldPanel
        groupRef={rightRef}
        hinge={[BW / 2, -BH / 2, 0]}
        axis="z"
        flatAngle={-Math.PI / 2}
        size={[THICK, BH, BD]}
        materials={mats.wallRightMats}
      />

      {/* ── Содержимое ── */}
      <group ref={contentsRef}>{children}</group>

      {/* ── Крышка ── */}
      {/* Начало координат группы — плоскость верхней панели, юбка свисает вниз */}
      <group ref={lidRef} position={[0, BH / 2, 0]} visible={showLid}>
        <mesh position={[0, THICK / 2, 0]} material={mats.lidTopMats} castShadow receiveShadow>
          <boxGeometry args={[lidW, THICK, lidD]} />
        </mesh>

        <FoldPanel
          groupRef={lidSkirtRefs.back}
          hinge={[0, 0, -lidD / 2 + THICK / 2]}
          axis="x"
          flatAngle={Math.PI / 2}
          size={[lidW, LID_H, THICK]}
          materials={mats.lidSkirtBackMats}
        />
        <FoldPanel
          groupRef={lidSkirtRefs.front}
          hinge={[0, 0, lidD / 2 - THICK / 2]}
          axis="x"
          flatAngle={-Math.PI / 2}
          size={[lidW, LID_H, THICK]}
          materials={mats.lidSkirtFrontMats}
        />
        <FoldPanel
          groupRef={lidSkirtRefs.left}
          hinge={[-lidW / 2 + THICK / 2, 0, 0]}
          axis="z"
          flatAngle={-Math.PI / 2}
          size={[THICK, LID_H, lidD]}
          materials={mats.lidSkirtLeftMats}
        />
        <FoldPanel
          groupRef={lidSkirtRefs.right}
          hinge={[lidW / 2 - THICK / 2, 0, 0]}
          axis="z"
          flatAngle={Math.PI / 2}
          size={[THICK, LID_H, lidD]}
          materials={mats.lidSkirtRightMats}
        />
      </group>

      {/* ── Лента ── */}
      {/* Юбка крышки свисает вниз, поэтому лента строится от её нижней кромки. */}
      <Ribbon
        groupRef={ribbonRef}
        material={mats.ribbon}
        lidW={lidW}
        lidD={lidD}
        topY={BH / 2 + THICK}
      />

      {/* ── Бант ── */}
      <Bow groupRef={bowRef} material={mats.bow} y={BH / 2 + THICK + 0.05} />
    </group>
  );
}

// ── Лента ───────────────────────────────────────────────────────────────────

const RIBBON_W = 0.16;
const RIBBON_T = 0.012;

/**
 * Две перекрещенные полосы поверх крышки плюс четыре свисающих по бокам.
 * Группа масштабируется по Y при затягивании, поэтому боковые полосы строятся
 * от верха вниз — так они «раскатываются» по стенкам, а не растут из воздуха.
 */
function Ribbon({
  groupRef,
  material,
  lidW,
  lidD,
  topY,
}: {
  groupRef: React.RefObject<THREE.Group | null>;
  material: THREE.Material;
  lidW: number;
  lidD: number;
  topY: number;
}) {
  const dropH = BH + LID_H + THICK;
  const sideY = topY - dropH / 2;

  return (
    <group ref={groupRef} visible={false}>
      {/* Поперёк крышки */}
      <mesh position={[0, topY + RIBBON_T / 2, 0]} material={material} castShadow>
        <boxGeometry args={[lidW + 0.02, RIBBON_T, RIBBON_W]} />
      </mesh>
      <mesh position={[0, topY + RIBBON_T / 2, 0]} material={material} castShadow>
        <boxGeometry args={[RIBBON_W, RIBBON_T, lidD + 0.02]} />
      </mesh>

      {/* По бокам вниз */}
      <mesh position={[lidW / 2 + RIBBON_T / 2, sideY, 0]} material={material}>
        <boxGeometry args={[RIBBON_T, dropH, RIBBON_W]} />
      </mesh>
      <mesh position={[-lidW / 2 - RIBBON_T / 2, sideY, 0]} material={material}>
        <boxGeometry args={[RIBBON_T, dropH, RIBBON_W]} />
      </mesh>
      <mesh position={[0, sideY, lidD / 2 + RIBBON_T / 2]} material={material}>
        <boxGeometry args={[RIBBON_W, dropH, RIBBON_T]} />
      </mesh>
      <mesh position={[0, sideY, -lidD / 2 - RIBBON_T / 2]} material={material}>
        <boxGeometry args={[RIBBON_W, dropH, RIBBON_T]} />
      </mesh>
    </group>
  );
}

// ── Бант ────────────────────────────────────────────────────────────────────

/**
 * Петли банта — торы с вырезом, а не коробки: скруглённый профиль ловит блики
 * и сразу читается как лента, тогда как параллелепипеды выглядят как планки.
 */
function Bow({
  groupRef,
  material,
  y,
}: {
  groupRef: React.RefObject<THREE.Group | null>;
  material: THREE.Material;
  y: number;
}) {
  const loop = useMemo(
    () => new THREE.TorusGeometry(0.17, 0.045, 10, 32, Math.PI * 1.55),
    [],
  );
  const knot = useMemo(() => new THREE.SphereGeometry(0.075, 16, 12), []);
  const tail = useMemo(() => new THREE.BoxGeometry(0.085, 0.02, 0.34), []);

  useEffect(
    () => () => {
      loop.dispose();
      knot.dispose();
      tail.dispose();
    },
    [loop, knot, tail],
  );

  return (
    <group ref={groupRef} position={[0, y, 0]} visible={false}>
      {/* Левая и правая петли: тор поставлен «на ребро» и развёрнут в стороны */}
      <mesh
        geometry={loop}
        material={material}
        position={[-0.16, 0.04, 0]}
        rotation={[Math.PI / 2, 0, Math.PI * 0.72]}
        castShadow
      />
      <mesh
        geometry={loop}
        material={material}
        position={[0.16, 0.04, 0]}
        rotation={[Math.PI / 2, 0, -Math.PI * 0.28]}
        castShadow
      />

      {/* Хвостики */}
      <mesh
        geometry={tail}
        material={material}
        position={[-0.1, 0.005, 0.16]}
        rotation={[0.18, 0.5, 0]}
      />
      <mesh
        geometry={tail}
        material={material}
        position={[0.1, 0.005, 0.16]}
        rotation={[0.18, -0.5, 0]}
      />

      {/* Узел */}
      <mesh geometry={knot} material={material} position={[0, 0.05, 0]} castShadow />
    </group>
  );
}
