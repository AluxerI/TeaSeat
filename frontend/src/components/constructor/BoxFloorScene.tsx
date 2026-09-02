import { Component, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { OrthographicCamera } from "three";
import RotateLeftRounded from "@mui/icons-material/RotateLeftRounded";
import RotateRightRounded from "@mui/icons-material/RotateRightRounded";
import RestartAltRounded from "@mui/icons-material/RestartAltRounded";
import IconButton from "@mui/material/IconButton";
import FloorGrid, { type FloorProps } from "./FloorGrid";
import { footprint } from "../../utils/giftLayout";
import { advanceFloorCamera, floorCameraPosition } from "../../utils/floorCamera";
import { clampPreviewRotation, floorViewport, PREVIEW_ROTATION_LIMIT } from "../../utils/constructorInteraction";
import GiftBox, { createBoxDrive } from "./three/GiftBox";
import { BD, BH, BW, THICK } from "./three/sceneConfig";
import { useConstructorTextures } from "./ConstructorCanvas";
import { usePageVisibility } from "../../hooks/usePageVisibility";
import TeaSachet, { createSachetDrive, SACHET_SIZE } from "./three/TeaSachet";
import SweetItem, { createSweetDrive, SWEET_SIZE } from "./three/SweetItem";
import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

// Условная высота визуальных моделей, не физический размер из backend.
// Фольга и лента добавляют сладости 0.064; бортик немного выше любого предмета.
export const FLOOR_ITEM_Y = .15;
export const FLOOR_ITEM_TOP = FLOOR_ITEM_Y + Math.max(SACHET_SIZE.depthFull, SWEET_SIZE.h + .064, .2) / 2;
export const FLOOR_WALL_HEIGHT = FLOOR_ITEM_TOP + .08;

class WebGLBoundary extends Component<{ children: ReactNode; fallback: ReactNode; onFailure: () => void }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  componentDidCatch() { this.props.onFailure(); }
  render() { return this.state.failed ? this.props.fallback : this.props.children; }
}

/** Единственное движение: от наклонного вида к дну. После остановки Canvas не перерисовывается. */
export function FloorCamera({ width, height, editing, previewRotation = 0, onSettled }: {
  width: number; height: number; editing: boolean; previewRotation?: number; onSettled?: (ready: boolean) => void;
}) {
  const { camera, size, invalidate } = useThree();
  // После выбора ручной сборки коробка сама переходит к виду сверху.
  const progress = useRef(0);
  const reduced = useRef(false);
  const settled = useRef(false);
  useEffect(() => {
    reduced.current = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
    invalidate();
  }, [invalidate]);
  useEffect(() => { settled.current = false; onSettled?.(false); invalidate(); }, [editing, onSettled, invalidate]);
  useEffect(() => { invalidate(); }, [previewRotation, invalidate]);
  useEffect(() => {
    if (camera instanceof OrthographicCamera) {
      camera.zoom = floorViewport(size.width, size.height, width, height).zoom;
      camera.updateProjectionMatrix();
    }
    invalidate();
  }, [camera, width, height, size.width, size.height, invalidate]);
  useFrame((_, delta) => {
    progress.current = advanceFloorCamera(progress.current, delta, editing, reduced.current);
    camera.up.set(0, 0, -1);
    const [, y, z] = floorCameraPosition(progress.current, width, height);
    const angle = clampPreviewRotation(previewRotation) * (1 - progress.current);
    camera.position.set(Math.sin(angle) * z, y, Math.cos(angle) * z);
    camera.lookAt(0, 0, 0);
    if (editing && progress.current < 1) invalidate();
    if (editing && progress.current === 1 && !settled.current) { settled.current = true; onSettled?.(true); }
  });
  return null;
}

function ContextLoss({ onLost, visible }: { onLost: () => void; visible: boolean }) {
  const gl = useThree((state) => state.gl);
  const invalidate = useThree((state) => state.invalidate);
  useEffect(() => { if (visible) invalidate(); }, [visible, invalidate]);
  useEffect(() => {
    const lost = (event: Event) => { event.preventDefault(); onLost(); };
    gl.domElement.addEventListener("webglcontextlost", lost);
    return () => gl.domElement.removeEventListener("webglcontextlost", lost);
  }, [gl, onLost]);
  return null;
}

export interface BoxFloorSceneProps extends FloorProps {
  editing: boolean;
  onChoose: () => void;
  controlsLocked?: boolean;
}

function FloorProduct({ product, rotated }: { product: ConstructorProductSize; rotated: boolean }) {
  const tea = useRef(createSachetDrive());
  const sweet = useRef(createSweetDrive());
  tea.current.visible = true; tea.current.fill = 1; tea.current.rotation.set(-Math.PI / 2, 0, 0);
  sweet.current.visible = true; sweet.current.wrap = 1; sweet.current.ribbon = 1;
  const width = product.size.width_cells * .88, depth = product.size.height_cells * .88;
  return <group rotation={[0, rotated ? -Math.PI / 2 : 0, 0]}>
    {product.constructor_role === "tea" ? <group scale={[width / SACHET_SIZE.w, 1, depth / SACHET_SIZE.h]}><TeaSachet drive={tea} /></group>
      : product.constructor_role === "sweet" ? <group scale={[width / (SWEET_SIZE.w + .05), 1, depth / (SWEET_SIZE.d + .05)]}><SweetItem drive={sweet} /></group>
        : <mesh><boxGeometry args={[width, .2, depth]} /><meshStandardMaterial color="#ccd7b5" /></mesh>}
  </group>;
}

/** Та же GiftBox, что использовалась в прежнем конструкторе; сетка — слой над её дном. */
function FloorContent({ box, sizes, items, editing, onChoose, previewRotation, onSettled, showContents }: BoxFloorSceneProps & { previewRotation: number; onSettled: (ready: boolean) => void; showContents: boolean }) {
  const width = box.width_cells;
  const height = box.height_cells;
  const drive = useRef(createBoxDrive({ fold: 1, lidLift: 0 }));
  useLayoutEffect(() => {
    drive.current.ribbon = showContents ? 0 : 1;
    drive.current.bow = showContents ? 0 : 1;
  }, [showContents]);
  const grid = useMemo(() => {
    const lines: number[] = [];
    for (let x = 0; x <= width; x += 1) lines.push(x - width / 2, .012, -height / 2, x - width / 2, .012, height / 2);
    for (let y = 0; y <= height; y += 1) lines.push(-width / 2, .012, y - height / 2, width / 2, .012, y - height / 2);
    return new Float32Array(lines);
  }, [width, height]);
  return <>
    <FloorCamera width={width} height={height} editing={editing} previewRotation={previewRotation} onSettled={onSettled} />
    <ambientLight intensity={1.5} />
    <directionalLight position={[4, 8, 3]} intensity={2} />
    <group scale={[width / (BW - THICK), showContents ? FLOOR_WALL_HEIGHT / BH : 1, height / (BD - THICK)]}
      onClick={(event) => { event.stopPropagation(); if (!showContents) onChoose(); }}>
      <GiftBox drive={drive} position={[0, BH / 2, 0]} showLid={!showContents} />
    </group>
    {editing && <>
      <lineSegments raycast={() => {}}>
        <bufferGeometry><bufferAttribute attach="attributes-position" args={[grid, 3]} /></bufferGeometry>
        <lineBasicMaterial color="#edd5ad" />
      </lineSegments>
    </>}
    {showContents && items.map((item) => {
      const product = sizes.find((size) => size.id === item.product_size_id);
      if (!product) return null;
      const [w, h] = footprint(product, item.is_rotated);
      return <group key={item.client_item_id} position={[item.position_x - width / 2 + w / 2, FLOOR_ITEM_Y, item.position_y - height / 2 + h / 2]}>
        <FloorProduct product={product} rotated={item.is_rotated} />
      </group>;
    })}
  </>;
}

export default function BoxFloorScene(props: BoxFloorSceneProps) {
  useConstructorTextures();
  const visible = usePageVisibility();
  const [lost, setLost] = useState(false);
  const [staticView, setStaticView] = useState(false);
  const [ready, setReady] = useState(false);
  const [rotation, setRotation] = useState(0);
  const editing = props.editing;
  const container = useRef<HTMLDivElement | null>(null);
  const orbit = useRef<{ id: number; x: number; start: number; moved: boolean } | null>(null);
  const suppressChoose = useRef(false);
  const [viewport, setViewport] = useState({ width: 0, height: 0 });
  useEffect(() => {
    const cancel = () => {
      const id = orbit.current?.id;
      orbit.current = null;
      if (id !== undefined && container.current?.hasPointerCapture?.(id)) container.current.releasePointerCapture(id);
    };
    const escape = (event: KeyboardEvent) => { if (event.key === "Escape") cancel(); };
    window.addEventListener("blur", cancel);
    window.addEventListener("keydown", escape);
    document.addEventListener("visibilitychange", cancel);
    return () => { cancel(); window.removeEventListener("blur", cancel); window.removeEventListener("keydown", escape); document.removeEventListener("visibilitychange", cancel); };
  }, []);
  useLayoutEffect(() => {
    const element = container.current;
    if (!element) return;
    const measure = () => { const rect = element.getBoundingClientRect(); setViewport({ width: rect.width, height: rect.height }); };
    measure();
    const observer = typeof ResizeObserver !== "undefined" ? new ResizeObserver(measure) : null;
    observer?.observe(element);
    window.addEventListener("resize", measure);
    return () => { observer?.disconnect(); window.removeEventListener("resize", measure); };
  }, []);
  const surface = floorViewport(viewport.width, viewport.height, props.box.width_cells, props.box.height_cells);
  const flatWidth = floorViewport(Math.max(0, viewport.width - 32), Math.max(0, viewport.height - 32), props.box.width_cells, props.box.height_cells).width;
  const fallback = props.editing ? <div className={styles.flatSurface} style={{ width: flatWidth ? flatWidth + 32 : "100%" }}><FloorGrid {...props} /></div>
    : <p className={styles.hint}>Предпросмотр 3D недоступен. Выберите коробку кнопкой ниже — плоская сетка останется рабочей.</p>;
  if (props.box.width_cells * props.box.height_cells > 1600) return <FloorGrid {...props} />;
  return <>
    <div ref={container} className={`${styles.scene} ${!editing ? styles.previewOrbit : ""}`} aria-label={editing ? "Сетка на дне выбранной коробки" : "Предпросмотр подарочной коробки"}
      onPointerDownCapture={(event) => {
        if (editing || event.button !== 0 || event.isPrimary === false) return;
        suppressChoose.current = false;
        orbit.current = { id: event.pointerId, x: event.clientX, start: rotation, moved: false };
      }}
      onPointerMoveCapture={(event) => {
        const current = orbit.current;
        if (editing || !current || current.id !== event.pointerId) return;
        if (Math.abs(event.clientX - current.x) < 5 && !current.moved) return;
        current.moved = true; suppressChoose.current = true;
        event.currentTarget.setPointerCapture?.(event.pointerId);
        setRotation(clampPreviewRotation(current.start + (event.clientX - current.x) * .006));
      }}
      onPointerUpCapture={(event) => {
        if (event.currentTarget.hasPointerCapture?.(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
        orbit.current = null;
      }}
      onPointerCancel={() => { orbit.current = null; suppressChoose.current = true; }}
      onLostPointerCapture={() => { orbit.current = null; }}
      onClickCapture={(event) => { if (suppressChoose.current) { event.stopPropagation(); suppressChoose.current = false; } }}>
      {lost || staticView ? fallback : <WebGLBoundary fallback={fallback} onFailure={() => setLost(true)}>
        <Canvas orthographic frameloop={visible ? "demand" : "never"} dpr={[1, 1.5]} camera={{ position: [0, 8, 5], near: .1, far: 1000 }} fallback={fallback}>
          <ContextLoss visible={visible} onLost={() => setLost(true)} />
          <FloorContent {...props} editing={editing} showContents={props.editing} previewRotation={rotation} onSettled={setReady} />
        </Canvas>
      </WebGLBoundary>}
      {editing && ready && !lost && !staticView && surface.width > 0 && <div className={styles.webglDropSurface} style={{ width: surface.width, height: surface.height }}>
        <FloorGrid {...props} overlay />
      </div>}
    </div>
    {!editing && !lost && <div className={styles.orbitControls} aria-label="Поворот предпросмотра">
      <IconButton title="Повернуть влево" aria-label="Повернуть коробку влево" disabled={rotation <= -PREVIEW_ROTATION_LIMIT} onClick={() => setRotation((value) => clampPreviewRotation(value - .15))}><RotateLeftRounded fontSize="small" /></IconButton>
      <IconButton title="Сбросить ракурс" aria-label="Сбросить ракурс" onClick={() => setRotation(0)}><RestartAltRounded fontSize="small" /></IconButton>
      <IconButton title="Повернуть вправо" aria-label="Повернуть коробку вправо" disabled={rotation >= PREVIEW_ROTATION_LIMIT} onClick={() => setRotation((value) => clampPreviewRotation(value + .15))}><RotateRightRounded fontSize="small" /></IconButton>
    </div>}
    {!lost && props.editing && <button type="button" className={styles.textButton} disabled={props.controlsLocked} onClick={() => setStaticView((value) => !value)}>{staticView ? "Включить 3D" : "Без 3D"}</button>}
  </>;
}
