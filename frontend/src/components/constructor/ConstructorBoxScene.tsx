import { useEffect, useMemo, useRef } from "react";
import { useThree } from "@react-three/fiber";
import { Html } from "@react-three/drei";
import { OrthographicCamera } from "three";
import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import ConstructorCanvas from "./ConstructorCanvas";
import GiftBox, { createBoxDrive } from "./three/GiftBox";
import { BD, BH, BW, THICK } from "./three/sceneConfig";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

function SelectionCamera({ rows, columns }: { rows: number; columns: number }) {
  const { camera, size, invalidate } = useThree();
  useEffect(() => {
    if (!(camera instanceof OrthographicCamera)) return;
    camera.position.set(0, 10, 9);
    camera.lookAt(0, 0, 0);
    camera.zoom = Math.min(size.width / (columns * 4.4), size.height / (rows * 3.7 + 1.5));
    camera.updateProjectionMatrix(); invalidate();
  }, [camera, size.width, size.height, rows, columns, invalidate]);
  return null;
}

function BoxModel({ box, scale, selected, onSelect, x, z }: {
  box: GiftSizeProfile; scale: number; selected: boolean; onSelect: () => void; x: number; z: number;
}) {
  const drive = useRef(createBoxDrive({ fold: 1, lidLift: 0, ribbon: 1, bow: 1 }));
  const w = box.width_cells * scale;
  const h = box.height_cells * scale;
  // Размерные линии вдоль X/Z, без выдуманной высоты: backend хранит только площадь.
  const lines = useMemo(() => new Float32Array([
    -w / 2, 0, h / 2 + .2, w / 2, 0, h / 2 + .2,
    -w / 2, 0, h / 2 + .1, -w / 2, 0, h / 2 + .3,
    w / 2, 0, h / 2 + .1, w / 2, 0, h / 2 + .3,
    w / 2 + .2, 0, -h / 2, w / 2 + .2, 0, h / 2,
    w / 2 + .1, 0, -h / 2, w / 2 + .3, 0, -h / 2,
    w / 2 + .1, 0, h / 2, w / 2 + .3, 0, h / 2,
  ]), [w, h]);
  return <group position={[x, 0, z]} onClick={(event) => { event.stopPropagation(); onSelect(); }}>
    <group scale={[w / (BW - THICK), .65, h / (BD - THICK)]}>
      <GiftBox drive={drive} position={[0, BH / 2, 0]} />
    </group>
    <lineSegments raycast={() => {}}>
      <bufferGeometry><bufferAttribute attach="attributes-position" args={[lines, 3]} /></bufferGeometry>
      <lineBasicMaterial color={selected ? "#5b7130" : "#927b5d"} />
    </lineSegments>
    <Html position={[0, 0, h / 2 + .55]} center className={styles.boxSceneLabel}><span>{box.name}</span></Html>
  </group>;
}

export default function ConstructorBoxScene({ boxes, selectedId, onSelect }: {
  boxes: GiftSizeProfile[]; selectedId: number | null; onSelect: (box: GiftSizeProfile) => void;
}) {
  const columns = Math.max(1, Math.min(2, boxes.length));
  const rows = Math.max(1, Math.ceil(boxes.length / columns));
  const scale = 2.6 / Math.max(1, ...boxes.flatMap((box) => [box.width_cells, box.height_cells]));
  return <div className={styles.boxSelectionScene} aria-label="Коробки в 3D">
    <ConstructorCanvas orthographic camera={{ position: [0, 10, 9], near: .1, far: 100 }}
      fallback={<p role="status" className={styles.hint}>3D недоступно. Выберите размер ниже.</p>}>
      <SelectionCamera rows={rows} columns={columns} />
      <ambientLight intensity={1.5} /><directionalLight position={[4, 8, 5]} intensity={2} />
      {boxes.map((box, index) => <BoxModel key={box.id} box={box} selected={selectedId === box.id} scale={scale}
        x={(index % columns - (columns - 1) / 2) * 4.4} z={(Math.floor(index / columns) - (rows - 1) / 2) * 3.7}
        onSelect={() => onSelect(box)} />)}
    </ConstructorCanvas>
  </div>;
}
