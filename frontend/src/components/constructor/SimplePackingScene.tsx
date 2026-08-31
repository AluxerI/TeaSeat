import { useEffect, useRef, useState, type MutableRefObject } from "react";
import { useFrame, useThree } from "@react-three/fiber";
import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import ConstructorCanvas from "./ConstructorCanvas";
import GiftBox, { createBoxDrive } from "./three/GiftBox";
import TeaSachet, { createSachetDrive, sachetMouth } from "./three/TeaSachet";
import SweetItem, { createSweetDrive } from "./three/SweetItem";
import LeafParticles, { createPourDrive } from "./three/LeafParticles";
import { BD, BH, BW, THICK } from "./three/sceneConfig";
import { easeOutCubic, lerp } from "./three/anim";
import { packingDuration, packingKeys, packingProgress, packingSlot } from "../../utils/simplePacking";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

export function PackedItem({ role, slot, seen, itemKey, pending = false, onComplete }: {
  role: "tea" | "sweet"; slot: ReturnType<typeof packingSlot>; seen: MutableRefObject<Set<string>>; itemKey: string;
  pending?: boolean; onComplete?: () => void;
}) {
  const tea = useRef(createSachetDrive());
  const sweet = useRef(createSweetDrive());
  const pour = useRef(createPourDrive());
  const duration = packingDuration(role);
  const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
  const elapsed = useRef(seen.current.has(itemKey) || reduced ? duration : 0);
  const completed = useRef(seen.current.has(itemKey));
  const { invalidate } = useThree();
  useEffect(() => { invalidate(); }, [pending, invalidate]);
  useFrame((_, delta) => {
    if (pending && !reduced) {
      tea.current.visible = false; sweet.current.visible = false; pour.current.active = false;
      return;
    }
    elapsed.current = reduced ? duration : Math.min(duration, elapsed.current + Math.min(delta, .05));
    const t = elapsed.current;
    const drop = easeOutCubic(packingProgress(t, duration - .7, .7));
    const fly = easeOutCubic(packingProgress(t, 0, .55));
    const drive = role === "tea" ? tea.current : sweet.current;
    drive.visible = true;
    drive.scale = lerp(.9, slot.scale, drop);
    drive.position.set(lerp((1 - fly) * (role === "tea" ? 2.4 : -2.4), slot.x, drop),
      lerp(1.25 + (1 - fly), -BH / 2 + .15 * slot.scale, drop), lerp(.3, slot.z, drop));
    // Пакетик ложится на дно (XZ); сладость изначально горизонтальна.
    drive.rotation.set(role === "tea" ? -Math.PI / 2 * drop : 0, 0, 0);
    tea.current.flap = packingProgress(t, .55, .6) * (1 - packingProgress(t, 2.35, .6));
    tea.current.fill = packingProgress(t, 1.15, 1.2);
    sweet.current.wrap = packingProgress(t, .55, .8);
    sweet.current.ribbon = packingProgress(t, 1.35, .55);
    pour.current.active = role === "tea" && t > 1.15 && t < 2.35;
    pour.current.progress = packingProgress(t, 1.15, 1.2);
    sachetMouth(tea.current, pour.current.to);
    pour.current.from.copy(pour.current.to); pour.current.from.y += .65;
    if (t < duration) invalidate();
    else if (!completed.current) {
      completed.current = true; seen.current.add(itemKey); onComplete?.();
    }
  });
  return role === "tea" ? <><TeaSachet drive={tea} /><LeafParticles drive={pour} /></> : <SweetItem drive={sweet} />;
}

function PackingCamera() {
  const { camera, size, invalidate } = useThree();
  useEffect(() => {
    const distance = Math.max(1, size.height / Math.max(size.width, 1)) * 6;
    camera.position.set(0, distance * 1.1, distance * .5);
    camera.lookAt(0, .4, 0); camera.updateProjectionMatrix(); invalidate();
  }, [camera, size.width, size.height, invalidate]);
  return null;
}

export interface SimplePackingSceneProps {
  box: GiftSizeProfile; teas: number[]; sweets: number[]; seen: MutableRefObject<Set<string>>;
}
export default function SimplePackingScene({ box, teas, sweets, seen }: SimplePackingSceneProps) {
  const boxDrive = useRef(createBoxDrive({ fold: 1, lidLift: 1 }));
  const unit = 2.4 / Math.max(box.width_cells, box.height_cells);
  const width = box.width_cells * unit, depth = box.height_cells * unit;
  const requirements = box.simple_requirements;
  const slots = (requirements?.tea_count ?? teas.length) + (requirements?.sweet_count ?? sweets.length);
  const entries = packingKeys(teas, sweets);
  const [animatingKey, setAnimatingKey] = useState<string | null>(null);
  useEffect(() => {
    const ordered = packingKeys(teas, sweets);
    const currentKeys = new Set(ordered.map((item) => item.key));
    for (const key of seen.current) if (!currentKeys.has(key)) seen.current.delete(key);
    // Быстрые клики обновляют состав сразу, но пакетики упаковываются по одному.
    // Удаление текущего предмета отменяет его такт; никакого POST из анимации нет.
    if (!animatingKey || !currentKeys.has(animatingKey) || seen.current.has(animatingKey)) {
      setAnimatingKey(ordered.find((item) => !seen.current.has(item.key))?.key ?? null);
    }
  }, [teas, sweets, seen, animatingKey]);
  return <div className={styles.scene} aria-label="Анимация упаковки подарка">
    <ConstructorCanvas camera={{ position: [0, 4.8, 6], fov: 38, near: .1, far: 50 }}
      fallback={<p role="status" className={styles.hint}>Анимация недоступна. Выбор товаров продолжает работать.</p>}>
      <PackingCamera />
      <ambientLight intensity={1.5} /><directionalLight position={[4, 7, 3]} intensity={2} />
      <group scale={[width / (BW - THICK), 1, depth / (BD - THICK)]}><GiftBox drive={boxDrive} showLid={false} /></group>
      {entries.map((entry, index) => <PackedItem key={entry.key} itemKey={entry.key} role={entry.role} seen={seen}
        pending={entry.key !== animatingKey && !seen.current.has(entry.key)} onComplete={() => setAnimatingKey(null)}
        slot={packingSlot(entry.role === "tea" ? index : (requirements?.tea_count ?? teas.length) + index - teas.length, slots, width, depth)} />)}
    </ConstructorCanvas>
    <span className={styles.srOnly}>Условная анимация упаковки; точную раскладку проверяет сервер.</span>
  </div>;
}
