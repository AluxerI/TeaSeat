/**
 * 3D-сцена конструктора: режиссёр фаз.
 *
 * Страница переводит фазы, сцена их отыгрывает и сообщает наверх о завершении
 * скриптовых тактов. Вся покадровая анимация идёт через мутируемые drive-объекты,
 * а не через React-состояние: пропс, меняющийся 60 раз в секунду, означал бы
 * 60 ререндеров дерева в секунду.
 */

import { Suspense, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { ContactShadows, Environment, Lightformer } from "@react-three/drei";
import * as THREE from "three";
import CameraRig from "./three/CameraRig";
import GiftBox, { createBoxDrive, type BoxDrive } from "./three/GiftBox";
import TeaSachet, { createSachetDrive, sachetMouth, type SachetDrive } from "./three/TeaSachet";
import LeafParticles, { createPourDrive, type PourDrive } from "./three/LeafParticles";
import SweetItem, { createSweetDrive, type SweetDrive } from "./three/SweetItem";
import Stage0Boxes from "./three/Stage0Boxes";
import { disposeConstructorTextures } from "./three/materials";
import {
  BEATS_ASSEMBLE,
  BEATS_SEAL,
  BEATS_SWEET,
  BEATS_TEA,
  BH,
  COMPLEX_SCALE,
  POUR_SPOUT,
  SEAL_BEAT,
  SHOTS,
  STAGE0_REST_FOLD,
  STAGING_POS,
  SWEET_BEAT,
  SWEET_SLOT,
  SWEET_STAGING,
  TEA_BEAT,
  TEA_SLOTS,
  TEA_SLOT_TILT,
  THICK,
  isScripted,
  shotForPhase,
  type CameraShot,
  type GiftType,
  type ScenePhase,
} from "./three/sceneConfig";
import {
  arcPoint,
  clamp01,
  easeInOutCubic,
  easeOutBack,
  easeOutBounce,
  easeOutCubic,
  lerp,
  useSequence,
} from "./three/anim";
import styles from "../../scss/pages/ConstructorPage.module.scss";

// ── Публичный интерфейс ─────────────────────────────────────────────────────

export interface ThreeSceneProps {
  phase: ScenePhase;
  giftType: GiftType;
  /** Сколько чаёв уже уложено — они лежат в коробке статично */
  packedTeas: number;
  /** Уложен ли десерт */
  packedSweet: boolean;
  /** Выбранный тип на этапе 0 (для анимации ухода невыбранной коробки) */
  selectedType: GiftType | null;
  onSelectType: (type: GiftType) => void;
  /** Скриптовый такт доиграл — страница переводит фазу дальше */
  onPhaseComplete: (phase: ScenePhase) => void;
}

// ── Вспомогательное ─────────────────────────────────────────────────────────

/**
 * Переводит центр DOM-элемента в мировую точку на заданном расстоянии от
 * камеры. Нужно для полёта в корзину: иконка живёт в шапке, вне канваса,
 * поэтому её NDC-координаты выходят за пределы [−1, 1] — это нормально,
 * unproject всё равно даёт корректный луч.
 */
function domToWorld(
  el: HTMLElement,
  canvas: HTMLCanvasElement,
  camera: THREE.Camera,
  distance: number,
  out: THREE.Vector3,
): THREE.Vector3 {
  const target = el.getBoundingClientRect();
  const view = canvas.getBoundingClientRect();

  const cx = target.left + target.width / 2;
  const cy = target.top + target.height / 2;

  const ndcX = ((cx - view.left) / view.width) * 2 - 1;
  const ndcY = -((cy - view.top) / view.height) * 2 + 1;

  out.set(ndcX, ndcY, 0.5).unproject(camera);
  // Держим постоянную глубину: иначе коробка «проваливается» в перспективу
  // непредсказуемо далеко и исчезает раньше, чем долетит.
  out.sub(camera.position).normalize().multiplyScalar(distance).add(camera.position);
  return out;
}

// ── Содержимое сцены ────────────────────────────────────────────────────────

function SceneContent({
  phase,
  giftType,
  packedTeas,
  packedSweet,
  selectedType,
  onSelectType,
  onPhaseComplete,
}: ThreeSceneProps) {
  const { gl, camera } = useThree();

  const boxScale = giftType === "complex" ? COMPLEX_SCALE : 1;

  // ── Drive-объекты ─────────────────────────────────────────────────────────
  const boxDrive = useRef<BoxDrive>(
    createBoxDrive({ fold: STAGE0_REST_FOLD, lidLift: 1, ribbon: 0, bow: 0 }),
  );
  // Один «летающий» пакетик на всю сцену: одновременно упаковывается только один.
  const flySachet = useRef<SachetDrive>(createSachetDrive());
  const pour = useRef<PourDrive>(createPourDrive());
  const flySweet = useRef<SweetDrive>(createSweetDrive());

  // Уложенные предметы лежат в коробке статично и едут вместе с ней.
  const restTea0 = useRef<SachetDrive>(createSachetDrive());
  const restTea1 = useRef<SachetDrive>(createSachetDrive());
  const restSweet = useRef<SweetDrive>(createSweetDrive());

  const boxRootRef = useRef<THREE.Group>(null);

  // Переиспользуемые векторы: аллокация в useFrame — прямой путь к рывкам GC.
  const tmpA = useMemo(() => new THREE.Vector3(), []);
  const tmpB = useMemo(() => new THREE.Vector3(), []);
  const tmpC = useMemo(() => new THREE.Vector3(), []);
  const cartTarget = useRef(new THREE.Vector3());
  const cartResolved = useRef(false);

  // Камера на такте отлёта меняет кадр — это единственный ререндер за фазу.
  const [shotOverride, setShotOverride] = useState<CameraShot | null>(null);
  const pullBackFired = useRef(false);

  useEffect(() => {
    setShotOverride(null);
    pullBackFired.current = false;
    cartResolved.current = false;
  }, [phase.kind, phase.kind === "packTea" ? phase.slot : -1]);

  // ── Статичные позы уложенных предметов ────────────────────────────────────
  // Проставляются один раз при изменении набора, дальше не трогаются.
  useEffect(() => {
    const apply = (d: React.MutableRefObject<SachetDrive>, i: number) => {
      const packed = packedTeas > i;
      d.current.visible = packed;
      if (!packed) return;
      d.current.position.set(...TEA_SLOTS[i]);
      // Пакетик лежит плашмя, слегка развёрнут — уложено рукой, а не станком.
      d.current.rotation.set(-Math.PI / 2, 0, TEA_SLOT_TILT[i]);
      d.current.fill = 1;
      d.current.flap = 0;
      d.current.scale = 1;
    };
    apply(restTea0, 0);
    apply(restTea1, 1);
  }, [packedTeas]);

  useEffect(() => {
    const d = restSweet.current;
    d.visible = packedSweet;
    if (!packedSweet) return;
    d.position.set(...SWEET_SLOT);
    d.rotation.set(0, 0.12, 0);
    d.wrap = 1;
    d.ribbon = 1;
    d.scale = 1;
  }, [packedSweet]);

  // ── Секвенсоры ────────────────────────────────────────────────────────────
  const complete = useCallback(() => onPhaseComplete(phase), [onPhaseComplete, phase]);

  const assembleSeq = useSequence(BEATS_ASSEMBLE, phase.kind === "assembleBox", complete);
  const teaSeq = useSequence(
    BEATS_TEA,
    phase.kind === "packTea",
    complete,
    phase.kind === "packTea" ? phase.slot : undefined,
  );
  const sweetSeq = useSequence(BEATS_SWEET, phase.kind === "packSweet", complete);
  const sealSeq = useSequence(BEATS_SEAL, phase.kind === "sealAndFly", complete);

  // ── Покадровая режиссура ──────────────────────────────────────────────────
  useFrame(() => {
    const box = boxDrive.current;
    const root = boxRootRef.current;

    switch (phase.kind) {
      // ── Сборка коробки ──────────────────────────────────────────────────
      case "assembleBox": {
        const fold = assembleSeq.beatProgress(0);
        box.fold = lerp(STAGE0_REST_FOLD, 1, easeInOutCubic(fold));
        box.lidLift = 1;
        box.saturation = 1;

        // Короб «оседает» после сборки — лёгкая просадка по высоте.
        const settle = assembleSeq.beatProgress(1);
        if (root) {
          const squash = settle > 0 ? Math.sin(settle * Math.PI) * 0.035 : 0;
          root.scale.set(boxScale * (1 + squash), boxScale * (1 - squash), boxScale * (1 + squash));
        }
        break;
      }

      // ── Упаковка чая ────────────────────────────────────────────────────
      case "packTea": {
        const s = flySachet.current;
        const slot = phase.slot;

        box.fold = 1;
        box.lidLift = 1;

        const bFly = teaSeq.beatProgress(TEA_BEAT.flyIn);
        const bOpen = teaSeq.beatProgress(TEA_BEAT.openFlap);
        const bPour = teaSeq.beatProgress(TEA_BEAT.pour);
        const bClose = teaSeq.beatProgress(TEA_BEAT.closeFlap);
        const bDrop = teaSeq.beatProgress(TEA_BEAT.dropIn);

        s.visible = true;
        // Летающий пакетик живёт в мировых координатах, а уложенный — внутри
        // коробки и наследует её масштаб. Выравниваем, иначе на большом наборе
        // в момент передачи предмет скакнёт в размере.
        s.scale = boxScale;
        s.fill = easeOutCubic(bPour);
        // Клапан открывается на своём такте и закрывается на своём.
        s.flap = clamp01(bOpen - bClose);

        if (bDrop > 0) {
          // ── Падение в коробку ──
          tmpA.set(...STAGING_POS);
          tmpB.set(
            TEA_SLOTS[slot][0] * boxScale,
            TEA_SLOTS[slot][1] * boxScale,
            TEA_SLOTS[slot][2] * boxScale,
          );
          // Отскок только по вертикали: горизонталь с отскоком выглядит
          // так, будто предмет ёрзает по столу.
          const horiz = easeOutCubic(bDrop);
          const vert = easeOutBounce(bDrop);
          arcPoint(tmpC, tmpA, tmpB, 0.35, horiz);
          s.position.set(tmpC.x, lerp(tmpA.y, tmpB.y, vert), tmpC.z);
          // Из вертикального положения заваливается плашмя в слот.
          s.rotation.set(
            lerp(0, -Math.PI / 2, easeOutCubic(bDrop)),
            0,
            lerp(0, TEA_SLOT_TILT[slot], bDrop),
          );
        } else if (bFly < 1) {
          // ── Влёт в кадр ──
          // Прилетает сбоку-сверху, из-за края экрана.
          tmpA.set(STAGING_POS[0] + 2.6, STAGING_POS[1] + 1.4, STAGING_POS[2] + 1.2);
          tmpB.set(...STAGING_POS);
          arcPoint(tmpC, tmpA, tmpB, 0.5, easeOutCubic(bFly));
          s.position.copy(tmpC);
          // Гасим кувырок к моменту прибытия.
          const spin = 1 - easeOutCubic(bFly);
          s.rotation.set(spin * 1.2, spin * -2.2, spin * 0.9);
        } else {
          // ── Висит в точке распаковки ──
          s.position.set(...STAGING_POS);
          // Едва заметное покачивание — иначе кадр «замерзает».
          s.rotation.set(0, Math.sin(teaSeq.state.elapsed * 1.6) * 0.06, 0);
        }

        // ── Поток чаинок ──
        const pouring = bPour > 0 && bPour < 1;
        pour.current.active = pouring;
        if (pouring) {
          pour.current.progress = bPour;
          pour.current.from.set(...POUR_SPOUT);
          sachetMouth(s, pour.current.to);
        }
        break;
      }

      // ── Упаковка десерта ────────────────────────────────────────────────
      case "packSweet": {
        const w = flySweet.current;
        box.fold = 1;
        box.lidLift = 1;
        pour.current.active = false;

        const bFly = sweetSeq.beatProgress(SWEET_BEAT.flyIn);
        const bWrap = sweetSeq.beatProgress(SWEET_BEAT.wrap);
        const bRibbon = sweetSeq.beatProgress(SWEET_BEAT.ribbon);
        const bDrop = sweetSeq.beatProgress(SWEET_BEAT.dropIn);

        w.visible = true;
        w.scale = boxScale;
        w.wrap = bWrap;
        w.ribbon = bRibbon;

        if (bDrop > 0) {
          tmpA.set(...SWEET_STAGING);
          tmpB.set(
            SWEET_SLOT[0] * boxScale,
            SWEET_SLOT[1] * boxScale,
            SWEET_SLOT[2] * boxScale,
          );
          arcPoint(tmpC, tmpA, tmpB, 0.32, easeOutCubic(bDrop));
          w.position.set(tmpC.x, lerp(tmpA.y, tmpB.y, easeOutBounce(bDrop)), tmpC.z);
          w.rotation.set(0, lerp(-0.4, 0.12, easeOutCubic(bDrop)), 0);
        } else if (bFly < 1) {
          tmpA.set(SWEET_STAGING[0] - 2.4, SWEET_STAGING[1] + 1.3, SWEET_STAGING[2] + 1.1);
          tmpB.set(...SWEET_STAGING);
          arcPoint(tmpC, tmpA, tmpB, 0.45, easeOutCubic(bFly));
          w.position.copy(tmpC);
          const spin = 1 - easeOutCubic(bFly);
          w.rotation.set(spin * -0.9, spin * 2.4, spin * 0.5);
        } else {
          w.position.set(...SWEET_STAGING);
          w.rotation.set(0, -0.4 + Math.sin(sweetSeq.state.elapsed * 1.5) * 0.08, 0);
        }
        break;
      }

      // ── Запечатка и полёт в корзину ─────────────────────────────────────
      case "sealAndFly": {
        box.fold = 1;
        pour.current.active = false;
        flySachet.current.visible = false;
        flySweet.current.visible = false;

        const bLid = sealSeq.beatProgress(SEAL_BEAT.closeLid);
        const bRibbon = sealSeq.beatProgress(SEAL_BEAT.ribbon);
        const bBow = sealSeq.beatProgress(SEAL_BEAT.bow);
        const bPull = sealSeq.beatProgress(SEAL_BEAT.pullBack);
        const bFly = sealSeq.beatProgress(SEAL_BEAT.flyToCart);

        // Крышка опускается с лёгким перелётом — садится с «пристуком».
        // Значение сознательно не обрезается по нулю: заход в минус и есть
        // перелёт, GiftBox его ждёт.
        box.lidLift = 1 - easeOutBack(bLid, 1.3);
        box.ribbon = bRibbon;
        box.bow = bBow;

        // Кадр меняем один раз, а не каждый кадр — это ререндер.
        if (bPull > 0 && !pullBackFired.current) {
          pullBackFired.current = true;
          setShotOverride(SHOTS.farewell);
        }

        if (root) {
          if (bFly > 0) {
            // Точку назначения берём один раз на старте такта: иконка корзины
            // может «уехать» при скролле, и цель прыгала бы вслед за ней.
            if (!cartResolved.current) {
              cartResolved.current = true;
              const el = document.getElementById("constructor-cart-target");
              if (el) {
                domToWorld(
                  el,
                  gl.domElement,
                  camera,
                  camera.position.length(),
                  cartTarget.current,
                );
              } else {
                // Иконки нет (узкий хедер) — улетаем просто вверх за кадр.
                cartTarget.current.set(0, 4.5, 0);
              }
            }

            const t = easeInOutCubic(bFly);
            tmpA.set(0, 0, 0);
            arcPoint(tmpC, tmpA, cartTarget.current, 0.9, t);
            root.position.copy(tmpC);
            root.scale.setScalar(boxScale * lerp(1, 0.06, t));
            root.rotation.y = t * Math.PI * 1.2;
          } else {
            // Перед отлётом коробка неспешно поворачивается — показывает бант.
            root.position.set(0, 0, 0);
            root.scale.setScalar(boxScale);
            root.rotation.y = bPull * 0.5;
          }
        }
        break;
      }

      // ── Подарок улетел ──────────────────────────────────────────────────
      case "done": {
        if (root) root.visible = false;
        pour.current.active = false;
        flySachet.current.visible = false;
        flySweet.current.visible = false;
        break;
      }

      // ── Спокойные фазы ──────────────────────────────────────────────────
      // Сюда попадают idleBox и chooseType. В chooseType основная коробка
      // вообще не смонтирована — за неё отвечает Stage0Boxes.
      default: {
        box.fold = 1;
        box.lidLift = 1;
        box.saturation = 1;
        box.ribbon = 0;
        box.bow = 0;
        pour.current.active = false;
        flySachet.current.visible = false;
        flySweet.current.visible = false;
        if (root) {
          root.visible = true;
          root.position.set(0, 0, 0);
          root.rotation.y = 0;
          root.scale.setScalar(boxScale);
        }
        break;
      }
    }
  });

  const shot = shotOverride ?? shotForPhase(phase);
  const showStage0 = phase.kind === "chooseType";

  return (
    <>
      {/* ── Свет ──
          Ключевой источник даёт форму и тени, заполняющий снимает провалы,
          Environment добавляет мягкие отражения в фольге и ленте. */}
      <ambientLight intensity={0.42} />
      <directionalLight
        position={[4.5, 7, 4]}
        intensity={1.75}
        castShadow
        shadow-mapSize-width={1024}
        shadow-mapSize-height={1024}
        shadow-camera-near={1}
        shadow-camera-far={22}
        shadow-camera-left={-6}
        shadow-camera-right={6}
        shadow-camera-top={6}
        shadow-camera-bottom={-6}
        shadow-bias={-0.0006}
        shadow-normalBias={0.02}
      />
      <directionalLight position={[-4, 3.5, -3]} intensity={0.4} />

      {/* Собственное окружение из Lightformer'ов вместо preset'а: preset тянет
          HDRI с CDN, а нам нужна работоспособность без сети. */}
      <Environment resolution={256}>
        <Lightformer intensity={2.2} position={[0, 4, 2]} scale={[8, 4, 1]} color="#fffaf0" />
        <Lightformer intensity={0.9} position={[-4, 1.5, 2]} scale={[3, 4, 1]} color="#ffe9c9" />
        <Lightformer intensity={0.7} position={[4, 1.5, -2]} scale={[3, 4, 1]} color="#e8f0ff" />
        <Lightformer
          intensity={0.5}
          position={[0, -3, 0]}
          rotation={[Math.PI / 2, 0, 0]}
          scale={[8, 8, 1]}
          color="#fff3e2"
        />
      </Environment>

      <ContactShadows
        position={[0, -BH / 2 - THICK - 0.01, 0]}
        opacity={0.42}
        scale={12}
        blur={2.6}
        far={4}
        resolution={512}
      />

      {/* Вращение камеры выключено не только на скриптовых тактах, но и на
          этапе 0: там канвас ловит клики по коробкам, и протяжка мышью
          завершалась бы выбором варианта, которого пользователь не хотел. */}
      <CameraRig
        shot={shot}
        interactive={!isScripted(phase) && phase.kind !== "chooseType"}
      />

      {/* ── Этап 0 ── */}
      {showStage0 && (
        <Stage0Boxes
          selected={selectedType}
          onSelect={onSelectType}
          locked={selectedType !== null}
        />
      )}

      {/* ── Основная коробка ── */}
      {!showStage0 && (
        <group ref={boxRootRef} scale={boxScale}>
          <GiftBox drive={boxDrive}>
            {/* Уложенное содержимое — дети коробки, поэтому едут вместе с ней */}
            <TeaSachet drive={restTea0} />
            <TeaSachet drive={restTea1} />
            <SweetItem drive={restSweet} />
          </GiftBox>
        </group>
      )}

      {/* ── Предметы в процессе упаковки: живут в мировых координатах ── */}
      {!showStage0 && (
        <>
          <TeaSachet drive={flySachet} />
          <SweetItem drive={flySweet} />
          <LeafParticles drive={pour} />
        </>
      )}
    </>
  );
}

// ── Обёртка ─────────────────────────────────────────────────────────────────

export default function ThreeScene(props: ThreeSceneProps) {
  // Ленивый монтаж канваса: паттерн из прежней версии сцены, страхует от
  // попыток создать WebGL-контекст до появления DOM.
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);

  // Процедурные текстуры кешируются на уровне модуля; освобождаем их, когда
  // страница конструктора уходит, иначе они висят в памяти всю сессию.
  useEffect(() => () => disposeConstructorTextures(), []);

  if (!mounted) return <div className={styles.heroCanvas} />;

  return (
    <div className={styles.heroCanvas}>
      <Canvas
        shadows
        camera={{ fov: 38, near: 0.1, far: 60, position: [0, 2.5, 7] }}
        dpr={[1, 1.75]}
        gl={{ antialias: true, alpha: true }}
      >
        <Suspense fallback={null}>
          <SceneContent {...props} />
        </Suspense>
      </Canvas>
    </div>
  );
}
