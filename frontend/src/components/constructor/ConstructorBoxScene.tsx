import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { useFrame, useThree } from "@react-three/fiber";
import { Environment, Lightformer } from "@react-three/drei";
import { PerspectiveCamera, type Group } from "three";
import ConstructorCanvas from "./ConstructorCanvas";
import type { ConstructorSceneProps, SceneChoice } from "./ConstructorSceneChoice";
import GiftBox, { createBoxDrive } from "./three/GiftBox";
import { BD, BH, BW, THICK, STAGE0_REST_FOLD, STAGE0_HOVER_FOLD } from "./three/sceneConfig";
import { damp } from "./three/anim";
import styles from "../../scss/pages/ConstructorChoiceScene.module.scss";

/** Неподвижный ракурс. Ни pointer, ни часы сцены не вращают камеру. */
export function SelectionCamera({ columns: _columns, rows: _rows, hero = false }: { columns: number; rows: number; hero?: boolean }) {
  const { camera, size, invalidate } = useThree();
  useEffect(() => {
    if (!(camera instanceof PerspectiveCamera)) return;
    const halfFov = camera.fov * Math.PI / 360;
    const aspect = size.width / Math.max(1, size.height);
    const frameWidth = hero ? 3.65 : 3.25;
    const frameHeight = hero ? 2.75 : 2.55;
    const distance = Math.max(frameWidth / (Math.tan(halfFov) * Math.max(.82, aspect)), frameHeight / Math.tan(halfFov));
    camera.position.set(0, distance * .53, distance * .85);
    camera.lookAt(0, hero ? .16 : .12, 0);
    camera.updateProjectionMatrix();
    invalidate();
  }, [camera, size.width, size.height, hero, invalidate]);
  return null;
}

const easeOutCubic = (value: number) => 1 - Math.pow(1 - Math.max(0, Math.min(1, value)), 3);

/**
 * На втором шаге содержимое не существует заранее в двух разных коробках.
 * Оно каскадом въезжает в одну выбранную коробку после shared-element перехода.
 */
function PackingPreview({ kind, width, depth }: { kind: "simple" | "advanced"; width: number; depth: number }) {
  const tea = useRef<Group>(null);
  const sweetTop = useRef<Group>(null);
  const sweetBottom = useRef<Group>(null);
  const elapsed = useRef(0);
  const { invalidate } = useThree();

  const grid = useMemo(() => new Float32Array(Array.from({ length: 3 }, (_, index) => {
    const part = (index + 1) / 4 - .5;
    return [part * width, .026, -depth / 2, part * width, .026, depth / 2,
      -width / 2, .026, part * depth, width / 2, .026, part * depth];
  }).flat()), [width, depth]);

  const place = (node: Group | null, progress: number,
    start: [number, number, number], target: [number, number, number], targetRotation = 0) => {
    if (!node) return;
    const p = easeOutCubic(progress);
    node.position.set(
      start[0] + (target[0] - start[0]) * p,
      start[1] + (target[1] - start[1]) * p,
      start[2] + (target[2] - start[2]) * p,
    );
    node.scale.setScalar(.72 + .28 * p);
    node.rotation.y = targetRotation * p;
  };

  const renderAt = (time: number) => {
    const progress = (delay: number) => Math.max(0, Math.min(1, (time - delay) / .58));
    place(tea.current, progress(.12), [-width * .86, .92, -depth * .34], [-width * .22, .12, 0], kind === "advanced" ? -.12 : 0);
    place(sweetTop.current, progress(.27), [width * .82, 1.02, -depth * .58], [width * .23, .1, -depth * .21], kind === "advanced" ? .08 : 0);
    place(sweetBottom.current, progress(.42), [width * .88, 1.16, depth * .6], [width * .23, .1, depth * .21], kind === "advanced" ? -.08 : 0);
  };

  useEffect(() => {
    const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
    elapsed.current = reduced ? 2 : 0;
    renderAt(elapsed.current);
    invalidate();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [invalidate]);

  useFrame((_, rawDelta) => {
    if (elapsed.current >= 1.25) return;
    elapsed.current = Math.min(1.25, elapsed.current + Math.min(rawDelta, .05));
    renderAt(elapsed.current);
    if (elapsed.current < 1.25) invalidate();
  });

  return <group>
    {kind === "advanced" && <lineSegments raycast={() => {}}>
      <bufferGeometry><bufferAttribute attach="attributes-position" args={[grid, 3]} /></bufferGeometry>
      <lineBasicMaterial color="#bda579" transparent opacity={.42} />
    </lineSegments>}

    <group ref={tea}>
      <mesh castShadow>
        <boxGeometry args={[width * .32, .2, depth * .64]} />
        <meshStandardMaterial color="#e1d9ba" roughness={.9} />
      </mesh>
      <mesh position={[0, .103, 0]} rotation={[-Math.PI / 2, 0, 0]}>
        <circleGeometry args={[Math.min(width, depth) * .09, 24]} />
        <meshStandardMaterial color="#687e4c" roughness={.8} />
      </mesh>
    </group>

    <group ref={sweetTop}>
      <mesh castShadow>
        <boxGeometry args={[width * .32, .16, depth * .3]} />
        <meshStandardMaterial color="#b7755f" roughness={.65} />
      </mesh>
    </group>
    <group ref={sweetBottom}>
      <mesh castShadow>
        <boxGeometry args={[width * .32, .16, depth * .3]} />
        <meshStandardMaterial color="#dacaa9" roughness={.65} />
      </mesh>
    </group>
  </group>;
}

function BoxModel({ choice, selected, hovered }: { choice: SceneChoice; selected: boolean; hovered: boolean }) {
  const { invalidate } = useThree();
  const restFold = Math.max(STAGE0_REST_FOLD, .5);
  const hoverFold = Math.max(STAGE0_HOVER_FOLD, .68);
  const drive = useRef(createBoxDrive({ fold: restFold, lidLift: 1, saturation: .86 }));
  const targetFold = selected ? 1 : hovered ? hoverFold : restFold;
  const targetSaturation = choice.disabled ? .08 : hovered || selected ? 1 : .86;

  useEffect(() => {
    if (window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) {
      drive.current.fold = targetFold;
      drive.current.saturation = targetSaturation;
    }
    invalidate();
  }, [targetFold, targetSaturation, invalidate]);

  useFrame((_, rawDelta) => {
    const d = drive.current;
    const moving = Math.abs(d.fold - targetFold) > .001 || Math.abs(d.saturation - targetSaturation) > .001;
    d.fold = moving ? damp(d.fold, targetFold, 8, Math.min(rawDelta, .05)) : targetFold;
    d.saturation = moving ? damp(d.saturation, targetSaturation, 8, Math.min(rawDelta, .05)) : targetSaturation;
    if (moving) invalidate();
  });

  const scale = 3.1 / Math.max(1, choice.box.width_cells, choice.box.height_cells);
  const width = choice.box.width_cells * scale;
  const depth = choice.box.height_cells * scale;

  return <group>
    <group scale={[width / (BW - THICK), .4, depth / (BD - THICK)]}>
      <GiftBox drive={drive} position={[0, BH / 2, 0]} showLid desaturable />
    </group>
    <mesh position={[0, .28, 0]} onClick={(event) => {
      event.stopPropagation();
      if (!choice.disabled && event.delta < 6) choice.onSelect();
    }}>
      <boxGeometry args={[width + .8, 1.15, depth + .8]} />
      <meshBasicMaterial transparent opacity={0} depthWrite={false} />
    </mesh>
  </group>;
}

function SharedModeBox({ box, kind }: { box: SceneChoice["box"]; kind: "simple" | "advanced" }) {
  const drive = useRef(createBoxDrive({ fold: 1, lidLift: 1, saturation: .96 }));
  const scale = 3.38 / Math.max(1, box.width_cells, box.height_cells);
  const width = box.width_cells * scale;
  const depth = box.height_cells * scale;

  return <group>
    <group scale={[width / (BW - THICK), .4, depth / (BD - THICK)]}>
      <GiftBox drive={drive} position={[0, BH / 2, 0]} showLid={false} desaturable />
    </group>
    <PackingPreview kind={kind} width={width} depth={depth} />
  </group>;
}

function SceneCanvas({ children, hero = false }: { children: ReactNode; hero?: boolean }) {
  return <ConstructorCanvas shadows camera={{ position: [0, hero ? 4.8 : 4.4, hero ? 7.2 : 6.6], fov: 36, near: .1, far: 100 }}
    fallback={<div className={styles.modelFallback}>3D недоступно</div>}>
    <ambientLight intensity={.8} />
    <directionalLight position={[4, 7, 5]} intensity={1.75} castShadow shadow-mapSize={[768, 768]} />
    <Environment resolution={32} frames={1}>
      <Lightformer form="rect" intensity={1.55} position={[-3, 4, 3]} scale={[5, 5, 1]} rotation={[0, Math.PI / 4, 0]} />
    </Environment>
    <mesh rotation={[-Math.PI / 2, 0, 0]} position={[0, -.03, 0]} receiveShadow>
      <planeGeometry args={[18, 18]} /><shadowMaterial transparent opacity={.1} />
    </mesh>
    <SelectionCamera columns={1} rows={1} hero={hero} />
    {children}
  </ConstructorCanvas>;
}

function ChoiceCard({ choice, selected }: { choice: SceneChoice; selected: boolean }) {
  const [hovered, setHovered] = useState(false);
  const action = choice.disabled ? "Недоступно" : selected ? "Переходим…" : "Выбрать →";
  const modelClass = `${styles.model}${choice.kind === "box" && selected ? ` ${styles.sharedBox}` : ""}`;

  return <article className={styles.card} data-selected={selected || undefined} data-disabled={choice.disabled || undefined}
    onMouseEnter={() => !choice.disabled && setHovered(true)} onMouseLeave={() => setHovered(false)}>
    <div className={modelClass} aria-hidden="true">
      <SceneCanvas><BoxModel choice={choice} selected={selected} hovered={hovered} /></SceneCanvas>
    </div>
    <button type="button" className={styles.label} aria-label={choice.ariaLabel} aria-pressed={selected}
      disabled={choice.disabled} onClick={choice.onSelect} onFocus={() => !choice.disabled && setHovered(true)} onBlur={() => setHovered(false)}>
      <strong>{choice.title}</strong>
      <span className={styles.caption}>{choice.caption}</span>
      <span className={styles.action}>{action}</span>
    </button>
  </article>;
}

function ModeOption({ choice, selected, onPreview }: {
  choice: SceneChoice;
  selected: boolean;
  onPreview: () => void;
}) {
  const kicker = choice.kind === "simple" ? "Автоматически" : "Вручную";
  return <button type="button" className={styles.modeOption} data-selected={selected || undefined}
    disabled={choice.disabled} aria-label={choice.ariaLabel} aria-pressed={selected}
    onMouseEnter={onPreview} onFocus={onPreview} onClick={choice.onSelect}>
    <span className={styles.modeKicker}>{kicker}</span>
    <strong>{choice.title}</strong>
    <span className={styles.modeCaption}>{choice.caption}</span>
    <span className={styles.action}>{choice.disabled ? "Недоступно" : selected ? "Переходим…" : "Выбрать →"}</span>
  </button>;
}

function ModeChoiceScene({ choices, selectedId, label }: ConstructorSceneProps) {
  const firstAvailable = choices.find((choice) => !choice.disabled) ?? choices[0];
  const [previewId, setPreviewId] = useState(firstAvailable?.id ?? "advanced");
  const preview = choices.find((choice) => choice.id === previewId && !choice.disabled) ?? firstAvailable;
  if (!preview) return null;
  const previewKind = preview.kind === "advanced" ? "advanced" : "simple";
  const sharedClass = `${styles.modeHero}${selectedId ? "" : ` ${styles.sharedBox}`}`;

  return <div className={styles.modeScene} aria-label={label}>
    <div className={sharedClass} aria-label={`Выбранная коробка: ${preview.box.name}`}>
      <SceneCanvas hero><SharedModeBox box={preview.box} kind={previewKind} /></SceneCanvas>
    </div>
    <p className={styles.selectedBoxName}><span>Выбранная коробка</span><strong>{preview.box.name}</strong></p>
    <div className={styles.modeOptions} role="group" aria-label={`${label}: варианты`}>
      {choices.map((choice) => <ModeOption key={choice.id} choice={choice} selected={selectedId === choice.id}
        onPreview={() => !choice.disabled && setPreviewId(choice.id)} />)}
    </div>
  </div>;
}

export default function ConstructorBoxScene(props: ConstructorSceneProps) {
  const isModeChoice = props.choices.length > 0 && props.choices.every((choice) => choice.kind !== "box");
  if (isModeChoice) return <ModeChoiceScene {...props} />;

  return <div className={styles.scene} aria-label={props.label}>
    <div className={styles.grid} data-single={props.choices.length === 1 || undefined} role="group" aria-label={`${props.label}: варианты`}>
      {props.choices.map((choice) => <ChoiceCard key={choice.id} choice={choice} selected={props.selectedId === choice.id} />)}
    </div>
  </div>;
}
