import { Component, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Html } from "@react-three/drei";
import { OrthographicCamera } from "three";
import FloorGrid, { type FloorProps } from "./FloorGrid";
import { footprint } from "../../utils/giftLayout";
import { advanceFloorCamera, floorCameraPosition } from "../../utils/floorCamera";
import GiftBox, { createBoxDrive } from "./three/GiftBox";
import { BD, BH, BW, THICK } from "./three/sceneConfig";
import { disposeConstructorTextures } from "./three/materials";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

class WebGLBoundary extends Component<{ children: ReactNode; fallback: ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  render() { return this.state.failed ? this.props.fallback : this.props.children; }
}

/** Единственное движение: от наклонного вида к дну. После остановки Canvas не перерисовывается. */
export function FloorCamera({ width, height, editing }: { width: number; height: number; editing: boolean }) {
  const { camera, size, invalidate } = useThree();
  // Повторное открытие Canvas в уже выбранной коробке сразу сохраняет вид сверху.
  const progress = useRef(editing ? 1 : 0);
  const reduced = useRef(false);
  useEffect(() => {
    reduced.current = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
    invalidate();
  }, [invalidate]);
  useEffect(() => { invalidate(); }, [editing, invalidate]);
  useEffect(() => {
    if (camera instanceof OrthographicCamera) {
      camera.zoom = Math.min(size.width / (width + 1.4), size.height / (height + 1.4));
      camera.updateProjectionMatrix();
    }
    invalidate();
  }, [camera, width, height, size.width, size.height, invalidate]);
  useFrame((_, delta) => {
    progress.current = advanceFloorCamera(progress.current, delta, editing, reduced.current);
    camera.up.set(0, 0, -1);
    camera.position.set(...floorCameraPosition(progress.current, width, height));
    camera.lookAt(0, 0, 0);
    if (editing && progress.current < 1) invalidate();
  });
  return null;
}

function ContextLoss({ onLost }: { onLost: () => void }) {
  const gl = useThree((state) => state.gl);
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
}

/** Та же GiftBox, что использовалась в прежнем конструкторе; сетка — слой над её дном. */
function FloorContent({ box, sizes, items, selectedId, onSelect, onCell, editing, onChoose }: BoxFloorSceneProps) {
  const width = box.width_cells;
  const height = box.height_cells;
  const drive = useRef(createBoxDrive({ fold: 1, lidLift: 0 }));
  useLayoutEffect(() => {
    drive.current.ribbon = editing ? 0 : 1;
    drive.current.bow = editing ? 0 : 1;
  }, [editing]);
  useEffect(() => () => disposeConstructorTextures(), []);
  const grid = useMemo(() => {
    const lines: number[] = [];
    for (let x = 0; x <= width; x += 1) lines.push(x - width / 2, .012, -height / 2, x - width / 2, .012, height / 2);
    for (let y = 0; y <= height; y += 1) lines.push(-width / 2, .012, y - height / 2, width / 2, .012, y - height / 2);
    return new Float32Array(lines);
  }, [width, height]);
  return <>
    <FloorCamera width={width} height={height} editing={editing} />
    <ambientLight intensity={1.5} />
    <directionalLight position={[4, 8, 3]} intensity={2} />
    <group scale={[width / (BW - THICK), 1, height / (BD - THICK)]}
      onClick={(event) => { event.stopPropagation(); if (!editing) onChoose(); }}>
      <GiftBox drive={drive} position={[0, BH / 2, 0]} showLid={!editing} />
    </group>
    {editing && <>
      <lineSegments raycast={() => {}}>
        <bufferGeometry><bufferAttribute attach="attributes-position" args={[grid, 3]} /></bufferGeometry>
        <lineBasicMaterial color="#edd5ad" />
      </lineSegments>
      <mesh position={[0, .008, 0]} rotation={[-Math.PI / 2, 0, 0]} onClick={(event) => {
        event.stopPropagation();
        onCell(Math.min(width - 1, Math.max(0, Math.floor(event.point.x + width / 2))), Math.min(height - 1, Math.max(0, Math.floor(event.point.z + height / 2))));
      }}>
        <planeGeometry args={[width, height]} /><meshBasicMaterial transparent opacity={0} depthWrite={false} />
      </mesh>
    </>}
    {editing && items.map((item, index) => {
      const product = sizes.find((size) => size.id === item.product_size_id);
      if (!product) return null;
      const [w, h] = footprint(product, item.is_rotated);
      return <group key={item.client_item_id} position={[item.position_x - width / 2 + w / 2, .08, item.position_y - height / 2 + h / 2]}>
        <mesh onClick={(event) => { event.stopPropagation(); onSelect(item.client_item_id); }}>
          <boxGeometry args={[w - .09, .14, h - .09]} />
          <meshStandardMaterial color={item.client_item_id === selectedId ? "#b6c784" : product.constructor_role === "sweet" ? "#d1a684" : "#ccd7b5"} />
        </mesh>
        <Html center position={[0, .1, 0]} style={{ pointerEvents: "none" }}><span className={styles.meshLabel}>{index + 1}</span></Html>
      </group>;
    })}
  </>;
}

export default function BoxFloorScene(props: BoxFloorSceneProps) {
  const [lost, setLost] = useState(false);
  const [staticView, setStaticView] = useState(false);
  const fallback = props.editing ? <FloorGrid {...props} /> : <p className={styles.hint}>Предпросмотр 3D недоступен. Выберите коробку кнопкой ниже — плоская сетка останется рабочей.</p>;
  if (props.box.width_cells * props.box.height_cells > 1600) return fallback;
  return <>
    <div className={styles.scene} aria-label={props.editing ? "Сетка на дне выбранной коробки" : "Предпросмотр подарочной коробки"}>
      {lost || staticView ? fallback : <WebGLBoundary fallback={fallback}>
        <Canvas orthographic frameloop="demand" dpr={[1, 1.5]} camera={{ position: [0, 8, 5], near: .1, far: 1000 }} fallback={fallback}>
          <ContextLoss onLost={() => setLost(true)} />
          <FloorContent {...props} />
        </Canvas>
      </WebGLBoundary>}
    </div>
    {!lost && props.editing && <button type="button" className={styles.textButton} onClick={() => setStaticView((value) => !value)}>{staticView ? "Показать объём коробки" : "Плоская сетка без WebGL"}</button>}
  </>;
}
