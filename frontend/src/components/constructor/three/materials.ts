/**
 * Процедурные текстуры и материалы конструктора.
 *
 * Внешних ассетов в public/ нет, и мы их сознательно не заводим: drei-шные
 * `Environment preset` и `Text` тянут файлы с CDN, что даёт сетевую зависимость
 * на рантайме и ломает офлайн у PWA. Поэтому весь картон, крафт, фольга и
 * бумага рисуются в <canvas> при первом обращении и кешируются на уровне модуля.
 */

import * as THREE from "three";
import { COLORS } from "./sceneConfig";

// ── Шум ─────────────────────────────────────────────────────────────────────

/**
 * Детерминированный ГПСЧ (mulberry32). Нужен именно детерминированный:
 * иначе текстура меняется от загрузки к загрузке, и «поймать» удачный вид
 * при подкрутке параметров невозможно.
 */
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

/** Билинейная выборка из решётки размера n×n с бесшовным заворотом по краям */
function sampleGrid(grid: Float32Array, n: number, x: number, y: number): number {
  const gx = x * n;
  const gy = y * n;
  const x0 = Math.floor(gx);
  const y0 = Math.floor(gy);
  const fx = gx - x0;
  const fy = gy - y0;
  const i0 = ((x0 % n) + n) % n;
  const j0 = ((y0 % n) + n) % n;
  const i1 = (i0 + 1) % n;
  const j1 = (j0 + 1) % n;

  // Сглаживание Кена Перлина — убирает решётчатые артефакты билинейки
  const sx = fx * fx * (3 - 2 * fx);
  const sy = fy * fy * (3 - 2 * fy);

  const a = grid[j0 * n + i0];
  const b = grid[j0 * n + i1];
  const c = grid[j1 * n + i0];
  const d = grid[j1 * n + i1];

  return (a + (b - a) * sx) * (1 - sy) + (c + (d - c) * sx) * sy;
}

/**
 * Многооктавный value-noise, бесшовный по обеим осям.
 * Возвращает массив size² значений в диапазоне 0..1.
 */
function fbm(size: number, octaves: number, seed: number, baseFreq = 4): Float32Array {
  const out = new Float32Array(size * size);
  let amp = 1;
  let ampSum = 0;

  for (let o = 0; o < octaves; o++) {
    const n = baseFreq << o;
    const rnd = mulberry32(seed + o * 7919);
    const grid = new Float32Array(n * n);
    for (let i = 0; i < grid.length; i++) grid[i] = rnd();

    for (let y = 0; y < size; y++) {
      for (let x = 0; x < size; x++) {
        out[y * size + x] += amp * sampleGrid(grid, n, x / size, y / size);
      }
    }

    ampSum += amp;
    amp *= 0.5;
  }

  for (let i = 0; i < out.length; i++) out[i] /= ampSum;
  return out;
}

// ── Помощники канвы ─────────────────────────────────────────────────────────

function makeCanvas(size: number): { canvas: HTMLCanvasElement; ctx: CanvasRenderingContext2D } {
  const canvas = document.createElement("canvas");
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("2D-контекст недоступен — процедурные текстуры не построить");
  return { canvas, ctx };
}

function toTexture(canvas: HTMLCanvasElement, srgb: boolean, repeat = 1): THREE.CanvasTexture {
  const tex = new THREE.CanvasTexture(canvas);
  tex.wrapS = THREE.RepeatWrapping;
  tex.wrapT = THREE.RepeatWrapping;
  tex.repeat.set(repeat, repeat);
  tex.anisotropy = 4;
  // Цветовые карты — в sRGB, служебные (bump/roughness) — линейные.
  tex.colorSpace = srgb ? THREE.SRGBColorSpace : THREE.NoColorSpace;
  tex.needsUpdate = true;
  return tex;
}

function hexToRgb(hex: string): [number, number, number] {
  const c = new THREE.Color(hex);
  return [c.r * 255, c.g * 255, c.b * 255];
}

// ── Генераторы текстур ──────────────────────────────────────────────────────

export interface SurfaceMaps {
  map: THREE.CanvasTexture;
  bumpMap: THREE.CanvasTexture;
  roughnessMap: THREE.CanvasTexture;
}

interface CardboardOptions {
  /** Базовый цвет поверхности */
  color: string;
  /** Сила зернистости, 0..1 */
  grain?: number;
  /** Насколько выражены продольные волокна, 0..1 */
  fiber?: number;
  /** Зерно ГПСЧ — разные значения дают разный рисунок */
  seed?: number;
  /** Разрешение текстуры */
  size?: number;
}

/**
 * Картон/крафт: базовый цвет, поверх многооктавный шум, продольные волокна
 * и редкие тёмные вкрапления. Одновременно строит bump и roughness из той же
 * высотной карты — так рельеф и блики совпадают, чего не даст случайный шум.
 */
function createCardboard(opts: CardboardOptions): SurfaceMaps {
  const size = opts.size ?? 512;
  const grain = opts.grain ?? 0.35;
  const fiberAmt = opts.fiber ?? 0.4;
  const seed = opts.seed ?? 1337;

  const noise = fbm(size, 5, seed);
  const fine = fbm(size, 3, seed + 4211, 32);
  const [br, bg, bb] = hexToRgb(opts.color);

  const albedo = makeCanvas(size);
  const height = makeCanvas(size);
  const rough = makeCanvas(size);

  const aImg = albedo.ctx.createImageData(size, size);
  const hImg = height.ctx.createImageData(size, size);
  const rImg = rough.ctx.createImageData(size, size);

  const rnd = mulberry32(seed + 99);

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const i = y * size + x;

      // Волокна: вытянутый по X шум даёт «полосатость» прессованной бумаги.
      const fiber =
        Math.sin((y / size) * Math.PI * 2 * 96 + noise[i] * 9) * 0.5 + 0.5;

      let h = noise[i] * 0.65 + fine[i] * 0.35;
      h = h * (1 - fiberAmt) + fiber * fiberAmt * (0.35 + noise[i] * 0.65);

      // Редкие тёмные крапины — вкрапления целлюлозы.
      const speck = rnd() > 0.9975 ? -0.35 : 0;
      h = Math.min(1, Math.max(0, h + speck));

      const shade = 1 - grain * 0.5 + h * grain;
      const p = i * 4;

      aImg.data[p] = Math.min(255, br * shade);
      aImg.data[p + 1] = Math.min(255, bg * shade);
      aImg.data[p + 2] = Math.min(255, bb * shade);
      aImg.data[p + 3] = 255;

      const hv = h * 255;
      hImg.data[p] = hv;
      hImg.data[p + 1] = hv;
      hImg.data[p + 2] = hv;
      hImg.data[p + 3] = 255;

      // Впадины бликуют меньше, гребни — сильнее: инвертируем высоту.
      const rv = (0.62 + (1 - h) * 0.3) * 255;
      rImg.data[p] = rv;
      rImg.data[p + 1] = rv;
      rImg.data[p + 2] = rv;
      rImg.data[p + 3] = 255;
    }
  }

  albedo.ctx.putImageData(aImg, 0, 0);
  height.ctx.putImageData(hImg, 0, 0);
  rough.ctx.putImageData(rImg, 0, 0);

  return {
    map: toTexture(albedo.canvas, true),
    bumpMap: toTexture(height.canvas, false),
    roughnessMap: toTexture(rough.canvas, false),
  };
}

/**
 * Фольга: анизотропные штрихи вдоль одной оси + крупные пятна отражения.
 * Рельеф намеренно мелкий и частый — так металл «играет» при повороте.
 */
function createFoil(): SurfaceMaps {
  const size = 512;
  const blotch = fbm(size, 4, 20240, 6);
  const [br, bg, bb] = hexToRgb(COLORS.foil);

  const albedo = makeCanvas(size);
  const height = makeCanvas(size);
  const rough = makeCanvas(size);

  const aImg = albedo.ctx.createImageData(size, size);
  const hImg = height.ctx.createImageData(size, size);
  const rImg = rough.ctx.createImageData(size, size);

  const rnd = mulberry32(777);
  // Строчный шум: одно значение на строку, растянутое по X — это и есть
  // «щётка», характерная для мятой фольги.
  const streak = new Float32Array(size);
  for (let i = 0; i < size; i++) streak[i] = rnd();

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const i = y * size + x;
      const s = streak[y] * 0.6 + streak[(y + 1) % size] * 0.4;
      const h = Math.min(1, Math.max(0, s * 0.45 + blotch[i] * 0.55));

      const shade = 0.78 + h * 0.34;
      const p = i * 4;

      aImg.data[p] = Math.min(255, br * shade);
      aImg.data[p + 1] = Math.min(255, bg * shade);
      aImg.data[p + 2] = Math.min(255, bb * shade);
      aImg.data[p + 3] = 255;

      const hv = h * 255;
      hImg.data[p] = hv;
      hImg.data[p + 1] = hv;
      hImg.data[p + 2] = hv;
      hImg.data[p + 3] = 255;

      // Фольга в целом гладкая: держим roughness низким и узким по разбросу.
      const rv = (0.14 + h * 0.24) * 255;
      rImg.data[p] = rv;
      rImg.data[p + 1] = rv;
      rImg.data[p + 2] = rv;
      rImg.data[p + 3] = 255;
    }
  }

  albedo.ctx.putImageData(aImg, 0, 0);
  height.ctx.putImageData(hImg, 0, 0);
  rough.ctx.putImageData(rImg, 0, 0);

  return {
    map: toTexture(albedo.canvas, true),
    bumpMap: toTexture(height.canvas, false),
    roughnessMap: toTexture(rough.canvas, false),
  };
}

/**
 * Текстура крышки: тот же картон плюс тиснёная надпись «TeaSeat».
 * Текст рисуется прямо в канву — это дешевле, чем тащить шрифт для drei/Text,
 * и не создаёт сетевого запроса.
 */
function createLidTop(): SurfaceMaps {
  const base = createCardboard({ color: COLORS.lid, seed: 5150, grain: 0.3, fiber: 0.3 });
  const size = 512;

  const { canvas, ctx } = makeCanvas(size);
  ctx.drawImage(base.map.image as HTMLCanvasElement, 0, 0);

  ctx.save();
  ctx.translate(size / 2, size / 2);

  // Двойная отрисовка со сдвигом — имитация вдавленного тиснения:
  // тёмная «канавка» и светлая «фаска» по краю букв.
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.font = `600 ${Math.round(size * 0.13)}px Georgia, "Times New Roman", serif`;

  ctx.fillStyle = "rgba(70, 44, 18, 0.55)";
  ctx.fillText("TeaSeat", 0, -2);
  ctx.fillStyle = "rgba(255, 240, 210, 0.30)";
  ctx.fillText("TeaSeat", 0, 2);

  ctx.font = `400 ${Math.round(size * 0.045)}px Georgia, "Times New Roman", serif`;
  ctx.fillStyle = "rgba(70, 44, 18, 0.42)";
  ctx.fillText("П О Д А Р О Ч Н Ы Й   Н А Б О Р", 0, size * 0.11);

  // Тонкая рамка по периметру крышки
  ctx.strokeStyle = "rgba(70, 44, 18, 0.28)";
  ctx.lineWidth = 3;
  ctx.strokeRect(-size * 0.42, -size * 0.42, size * 0.84, size * 0.84);
  ctx.restore();

  // Высотную карту дублируем с текстом, чтобы тиснение читалось в рельефе.
  const h = makeCanvas(size);
  h.ctx.drawImage(base.bumpMap.image as HTMLCanvasElement, 0, 0);
  h.ctx.save();
  h.ctx.translate(size / 2, size / 2);
  h.ctx.textAlign = "center";
  h.ctx.textBaseline = "middle";
  h.ctx.font = `600 ${Math.round(size * 0.13)}px Georgia, "Times New Roman", serif`;
  h.ctx.fillStyle = "rgba(0, 0, 0, 0.6)";
  h.ctx.fillText("TeaSeat", 0, 0);
  h.ctx.restore();

  return {
    map: toTexture(canvas, true),
    bumpMap: toTexture(h.canvas, false),
    roughnessMap: base.roughnessMap,
  };
}

// ── Кеш ─────────────────────────────────────────────────────────────────────

type SurfaceKey =
  | "cardboardOuter"
  | "cardboardInner"
  | "lidTop"
  | "sachetPaper"
  | "foil"
  | "chocolate";

const cache = new Map<SurfaceKey, SurfaceMaps>();

const builders: Record<SurfaceKey, () => SurfaceMaps> = {
  cardboardOuter: () =>
    createCardboard({ color: COLORS.cardboardOuter, seed: 1337, grain: 0.34, fiber: 0.38 }),
  cardboardInner: () =>
    createCardboard({ color: COLORS.cardboardInner, seed: 2244, grain: 0.42, fiber: 0.5 }),
  lidTop: createLidTop,
  sachetPaper: () =>
    createCardboard({ color: COLORS.sachetPaper, seed: 8801, grain: 0.22, fiber: 0.62, size: 256 }),
  foil: createFoil,
  chocolate: () =>
    createCardboard({ color: COLORS.chocolate, seed: 3312, grain: 0.3, fiber: 0.1, size: 256 }),
};

/** Ленивое построение с кешированием — каждая поверхность считается один раз */
export function getSurface(key: SurfaceKey): SurfaceMaps {
  let s = cache.get(key);
  if (!s) {
    s = builders[key]();
    cache.set(key, s);
  }
  return s;
}

/**
 * Освобождает текстуры и чистит кеш. Вызывается при размонтировании страницы
 * конструктора; при следующем заходе поверхности пересоберутся (единицы мс).
 */
export function disposeConstructorTextures(): void {
  for (const s of cache.values()) {
    s.map.dispose();
    s.bumpMap.dispose();
    s.roughnessMap.dispose();
  }
  cache.clear();
}

// ── Обесцвечивание ──────────────────────────────────────────────────────────

export interface SaturationUniform {
  value: number;
}

/**
 * Патчит материал юниформой uSaturation (0 — ч/б, 1 — исходный цвет).
 *
 * Обесцвечиваем итоговый цвет пикселя, а не альбедо: так гаснут и блики от
 * окружения, иначе на серой коробке остаются золотистые отблески. Точка врезки
 * — перед тонмаппингом, когда освещение уже посчитано.
 *
 * Возвращает объект юниформы; мутируй `.value` в useFrame.
 */
export function patchSaturation(
  material: THREE.MeshStandardMaterial,
  initial = 1,
): SaturationUniform {
  const uniform: SaturationUniform = { value: initial };

  material.onBeforeCompile = (shader) => {
    shader.uniforms.uSaturation = uniform;
    shader.fragmentShader = shader.fragmentShader
      .replace(
        "void main() {",
        "uniform float uSaturation;\nvoid main() {",
      )
      .replace(
        "#include <tonemapping_fragment>",
        `
        {
          float _lum = dot( gl_FragColor.rgb, vec3( 0.2126, 0.7152, 0.0722 ) );
          gl_FragColor.rgb = mix( vec3( _lum ), gl_FragColor.rgb, uSaturation );
        }
        #include <tonemapping_fragment>
        `,
      );
  };

  // Без своего ключа three переиспользует программу непатченного материала.
  material.customProgramCacheKey = () => "constructor-saturation";

  return uniform;
}

// ── Фабрики материалов ──────────────────────────────────────────────────────

export interface MaterialOptions {
  /** Повтор текстуры по поверхности — крупные панели требуют больше тайлов */
  repeat?: number;
  roughness?: number;
  metalness?: number;
  /** Сила рельефа */
  bumpScale?: number;
  /** Добавить поддержку обесцвечивания */
  desaturable?: boolean;
  color?: string;
}

export interface BuiltMaterial {
  material: THREE.MeshStandardMaterial;
  saturation?: SaturationUniform;
}

/**
 * Собирает MeshStandardMaterial поверх процедурной поверхности.
 *
 * Текстуры общие (из кеша), но `repeat` у каждого материала свой, поэтому
 * карты клонируются: THREE.Texture.clone() разделяет исходную картинку и не
 * грузит GPU повторно, зато даёт независимые offset/repeat.
 */
export function buildMaterial(key: SurfaceKey, opts: MaterialOptions = {}): BuiltMaterial {
  const src = getSurface(key);
  const repeat = opts.repeat ?? 1;

  const clone = (t: THREE.CanvasTexture): THREE.Texture => {
    if (repeat === 1) return t;
    const c = t.clone();
    c.needsUpdate = true;
    c.repeat.set(repeat, repeat);
    // Метка для disposeMaterial: общие текстуры из кеша освобождать нельзя,
    // а вот персональные копии — нужно, иначе они утекают при размонтировании.
    c.userData.isClone = true;
    return c;
  };

  const material = new THREE.MeshStandardMaterial({
    map: clone(src.map),
    bumpMap: clone(src.bumpMap),
    roughnessMap: clone(src.roughnessMap),
    bumpScale: opts.bumpScale ?? 0.012,
    roughness: opts.roughness ?? 0.85,
    metalness: opts.metalness ?? 0.05,
    color: opts.color ? new THREE.Color(opts.color) : 0xffffff,
  });

  const saturation = opts.desaturable ? patchSaturation(material) : undefined;
  return { material, saturation };
}

/**
 * Освобождает материал вместе с его персональными копиями текстур.
 *
 * `THREE.Material.dispose()` намеренно не трогает текстуры — они часто общие.
 * У нас общие как раз лежат в кеше модуля, а копии с собственным `repeat`
 * принадлежат конкретному материалу и без этого шага утекали бы.
 */
export function disposeMaterial(material: THREE.MeshStandardMaterial): void {
  for (const map of [material.map, material.bumpMap, material.roughnessMap]) {
    if (map?.userData.isClone) map.dispose();
  }
  material.dispose();
}

/** Однотонный материал без текстуры — для лент, нитки, банта */
export function buildPlainMaterial(
  color: string,
  opts: { roughness?: number; metalness?: number; desaturable?: boolean } = {},
): BuiltMaterial {
  const material = new THREE.MeshStandardMaterial({
    color: new THREE.Color(color),
    roughness: opts.roughness ?? 0.6,
    metalness: opts.metalness ?? 0.05,
  });
  const saturation = opts.desaturable ? patchSaturation(material) : undefined;
  return { material, saturation };
}
