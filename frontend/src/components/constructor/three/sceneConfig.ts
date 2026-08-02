/**
 * Единая точка правды для 3D-конструктора: габариты, палитра, цели камеры
 * и тайминги анимационных тактов. Всё, что нужно подкрутить «на глаз»,
 * лежит здесь, а не размазано по компонентам.
 */

// ── Габариты коробки (мировые единицы) ──────────────────────────────────────
// Перенесены из старой ThreeScene, чтобы не ломать привычные пропорции.

/** Ширина коробки (по X) */
export const BW = 2;
/** Высота стенок (по Y) */
export const BH = 1.2;
/** Глубина коробки (по Z) */
export const BD = 1.5;
/** Толщина картона */
export const THICK = 0.045;
/** Высота бортика крышки */
export const LID_H = 0.18;
/** Зазор крышки относительно корпуса, чтобы она садилась снаружи */
export const LID_GAP = 0.035;

/** Множитель размера для усложнённого набора */
export const COMPLEX_SCALE = 1.18;

// ── Палитра ─────────────────────────────────────────────────────────────────
// Согласована с токенами ConstructorPage.module.scss:
// $color-accent-light #c8903c, $color-badge #b5532e, $color-success #5a8a2a.

export const COLORS = {
  /** Внешняя сторона картона — тёплое золото */
  cardboardOuter: "#c8903c",
  /** Внутренняя сторона — крафт потемнее */
  cardboardInner: "#9c6f34",
  /** Крышка чуть светлее корпуса, чтобы читалась граница */
  lid: "#dcae63",
  /** Лента */
  ribbon: "#b5532e",
  /** Бант — на тон темнее ленты */
  bow: "#9c4426",
  /** Бумага чайного пакетика */
  sachetPaper: "#efe3cd",
  /** Ярлычок на нитке */
  sachetTag: "#c8903c",
  /** Нитка */
  sachetString: "#b8a88f",
  /** Чаинки */
  teaLeaf: "#4a6b1f",
  teaLeafAlt: "#5f8a2a",
  /** Фольга обёртки десерта */
  foil: "#d8d2c4",
  /** Шоколад */
  chocolate: "#4a2c14",
  /** Фон сцены (совпадает с $color-bg-page) */
  pageBg: "#faf5ee",
} as const;

// ── Камера ──────────────────────────────────────────────────────────────────

/**
 * Цель камеры в сферических координатах вокруг точки `target`.
 * azimuth/polar в радианах, radius в мировых единицах.
 */
export interface CameraShot {
  azimuth: number;
  polar: number;
  radius: number;
  target: [number, number, number];
  /** Скорость демпфирования: больше — резче доезд. См. damp() в anim.ts */
  lambda?: number;
  /** Медленный автоматический дрейф азимута, рад/сек */
  drift?: number;
}

const D2R = Math.PI / 180;

/**
 * Пофазные положения камеры. Ключи соответствуют ScenePhase.kind
 * (плюс отдельные кадры для тактов, которые двигают камеру внутри фазы).
 */
export const SHOTS = {
  /** Этап 0: обе коробки в кадре, камера чуть сверху и далеко */
  chooseType: {
    azimuth: 0,
    polar: 68 * D2R,
    radius: 7.6,
    target: [0, 0.1, 0],
    lambda: 2.2,
    drift: 0.05,
  },
  /** Сборка коробки: подлетаем ближе и опускаемся к уровню стола */
  assembleBox: {
    azimuth: -28 * D2R,
    polar: 62 * D2R,
    radius: 4.6,
    target: [0, 0.05, 0],
    lambda: 1.6,
    drift: 0.12,
  },
  /** Ожидание выбора: спокойный облёт */
  idleBox: {
    azimuth: -20 * D2R,
    polar: 58 * D2R,
    radius: 4.9,
    target: [0, 0.1, 0],
    lambda: 2.4,
    drift: 0.16,
  },
  /** Упаковка чая: смещаемся вбок, чтобы видеть и пакетик, и коробку */
  packTea: {
    azimuth: 34 * D2R,
    polar: 70 * D2R,
    radius: 4.2,
    target: [0.15, 0.45, 0],
    lambda: 2,
    drift: 0.04,
  },
  /** Упаковка десерта: зеркально с другой стороны */
  packSweet: {
    azimuth: -46 * D2R,
    polar: 72 * D2R,
    radius: 4.1,
    target: [-0.1, 0.45, 0],
    lambda: 2,
    drift: 0.04,
  },
  /** Запечатка: смотрим сверху на крышку и бант */
  seal: {
    azimuth: 12 * D2R,
    polar: 44 * D2R,
    radius: 4.4,
    target: [0, 0.35, 0],
    lambda: 2,
    drift: 0.1,
  },
  /** Отлёт перед полётом в корзину */
  farewell: {
    azimuth: 0,
    polar: 62 * D2R,
    radius: 6.4,
    target: [0, 0.3, 0],
    lambda: 1.4,
    drift: 0,
  },
} satisfies Record<string, CameraShot>;

export type ShotName = keyof typeof SHOTS;

/** Пределы пользовательского drag-оффсета поверх скриптовой камеры */
export const DRAG_LIMITS = {
  azimuth: 55 * D2R,
  polar: 22 * D2R,
  /** Скорость затухания оффсета обратно к нулю в скриптовых фазах, 1/сек */
  decayLambda: 1.1,
  /** Чувствительность: радиан на пиксель */
  sensitivity: 0.005,
};

/** Жёсткие пределы полярного угла, чтобы не пролететь сквозь пол/зенит */
export const POLAR_CLAMP: [number, number] = [24 * D2R, 86 * D2R];

// ── Тайминги тактов (секунды) ───────────────────────────────────────────────

/**
 * Сборка коробки из плоской развёртки.
 * 0: стенки поднимаются, 1: короб «оседает», крышка встаёт на место.
 */
export const BEATS_ASSEMBLE = [1.1, 0.5];

/**
 * Полная упаковка чая — 5 тактов, согласованы с пользователем:
 * влетает → клапан раскрывается → поток чаинок → клапан закрывается → дуга в коробку.
 */
export const BEATS_TEA = [0.55, 0.6, 1.2, 0.6, 0.7];

/** Индексы тактов чая — чтобы не считать магические числа в коде */
export const TEA_BEAT = {
  flyIn: 0,
  openFlap: 1,
  pour: 2,
  closeFlap: 3,
  dropIn: 4,
} as const;

/**
 * Десерт: влетает → фольга оборачивается → лента → падает в коробку.
 */
export const BEATS_SWEET = [0.55, 0.9, 0.45, 0.7];

export const SWEET_BEAT = {
  flyIn: 0,
  wrap: 1,
  ribbon: 2,
  dropIn: 3,
} as const;

/**
 * Финал: клапаны закрываются → лента затягивается → бант → отлёт камеры → полёт в корзину.
 */
export const BEATS_SEAL = [0.85, 0.55, 0.45, 0.5, 0.9];

export const SEAL_BEAT = {
  closeLid: 0,
  ribbon: 1,
  bow: 2,
  pullBack: 3,
  flyToCart: 4,
} as const;

// ── Позиции содержимого внутри коробки ──────────────────────────────────────

/**
 * Куда ложатся пакетики чая (локальные координаты коробки).
 *
 * Пакетик кладётся плашмя — повёрнутый на −90° вокруг X, — поэтому вверх
 * у него смотрит толщина (DEPTH_FULL ≈ 0.19), а не высота. Отсюда и подъём
 * над дном: половина толщины плюс толщина картона, иначе пакетик либо висит
 * в воздухе, либо утапливается в дно.
 */
export const TEA_SLOTS: [number, number, number][] = [
  [-0.44, -BH / 2 + 0.1, -0.16],
  [0.44, -BH / 2 + 0.1, -0.16],
];

/** Поворот пакетиков в слотах, чтобы лежали не строго параллельно */
export const TEA_SLOT_TILT = [-0.16, 0.13];

/** Куда ложится десерт: половина высоты завёрнутой плитки над дном */
export const SWEET_SLOT: [number, number, number] = [0, -BH / 2 + 0.13, 0.36];

/** Точка «на весу», где происходит распаковка чая, перед падением в коробку */
export const STAGING_POS: [number, number, number] = [0.95, 1.15, 0.55];

/** Откуда сыплются чаинки — носик над горловиной пакетика */
export const POUR_SPOUT: [number, number, number] = [
  STAGING_POS[0] + 0.02,
  STAGING_POS[1] + 0.95,
  STAGING_POS[2],
];

/** Точка «на весу» для десерта */
export const SWEET_STAGING: [number, number, number] = [-0.95, 1.05, 0.5];

// ── Прочее ──────────────────────────────────────────────────────────────────

/** Сколько чаинок в потоке */
export const LEAF_COUNT = 220;

/** Разброс позиций двух коробок на этапе 0 */
export const STAGE0_OFFSET_X = 2.15;

/**
 * Тип набора. Живёт здесь, а не в ConstructorPage: 3D-компоненты тоже им
 * оперируют, а импорт из страницы замкнул бы граф зависимостей в кольцо.
 */
export type GiftType = "simplified" | "complex";

/**
 * На этапе 0 коробки показаны недособранными — стенки приподняты примерно
 * на треть. Полностью плоская развёртка не читается как коробка, а готовая
 * коробка не обещает, что её сейчас будут собирать.
 */
export const STAGE0_REST_FOLD = 0.28;
/** При наведении стенки приподнимаются заметнее */
export const STAGE0_HOVER_FOLD = 0.46;
/** Обесцвеченность коробки, на которую не навели */
export const STAGE0_DIM_SATURATION = 0.08;

// ── Фазы сцены ──────────────────────────────────────────────────────────────

/**
 * Фаза — единственный источник правды для 3D. Страница переводит фазы,
 * сцена их отыгрывает и сообщает о завершении скриптовых тактов.
 */
export type ScenePhase =
  /** Этап 0: две коробки, ждём выбора типа */
  | { kind: "chooseType" }
  /** Развёртка складывается в коробку */
  | { kind: "assembleBox" }
  /** Коробка собрана и открыта, ждём действий пользователя */
  | { kind: "idleBox" }
  /** Упаковка одного чая в слот */
  | { kind: "packTea"; slot: 0 | 1 }
  /** Упаковка десерта */
  | { kind: "packSweet" }
  /** Запечатка и полёт в корзину */
  | { kind: "sealAndFly" }
  /** Подарок улетел */
  | { kind: "done" };

/** Фазы, во время которых пользователь не должен вмешиваться */
export const SCRIPTED_PHASES: ReadonlySet<ScenePhase["kind"]> = new Set([
  "assembleBox",
  "packTea",
  "packSweet",
  "sealAndFly",
]);

export const isScripted = (phase: ScenePhase): boolean =>
  SCRIPTED_PHASES.has(phase.kind);

/** Какой кадр камеры соответствует фазе */
export function shotForPhase(phase: ScenePhase): CameraShot {
  switch (phase.kind) {
    case "chooseType":
      return SHOTS.chooseType;
    case "assembleBox":
      return SHOTS.assembleBox;
    case "idleBox":
      return SHOTS.idleBox;
    case "packTea":
      return SHOTS.packTea;
    case "packSweet":
      return SHOTS.packSweet;
    case "sealAndFly":
      return SHOTS.seal;
    case "done":
      return SHOTS.farewell;
  }
}
