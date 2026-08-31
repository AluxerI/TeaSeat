import { BufferGeometry, CatmullRomCurve3, Float32BufferAttribute, Vector3 } from "three";

/** Объёмная лента с широкой поверхностью, тонкой кромкой и мягким изгибом. */
export function ribbonBand(points: [number, number, number][], width: number, notched = false): BufferGeometry {
  const curve = new CatmullRomCurve3(points.map((point) => new Vector3(...point)), false, "centripetal");
  const positions: number[] = [];
  const indices: number[] = [];
  const segments = 36;
  for (let row = 0; row <= segments; row += 1) {
    for (const side of [1, -1]) {
      for (let column = 0; column < 3; column += 1) {
        // Отступ центра последнего среза создаёт V-образный хвост.
        const baseT = row / segments;
        const t = baseT - (column === 1 && notched ? .1 * Math.max(0, (baseT - .8) / .2) : 0);
        const center = curve.getPoint(t);
        const tangent = curve.getTangent(t);
        const twist = Math.sin(t * Math.PI) * .28;
        const across = new Vector3(0, Math.sin(twist), Math.cos(twist));
        const normal = new Vector3().crossVectors(tangent, across).normalize();
        const point = center.addScaledVector(across, (column - 1) * width / 2).addScaledVector(normal, side * .006);
        positions.push(point.x, point.y, point.z);
      }
    }
  }
  for (let row = 0; row < segments; row += 1) {
    const a = row * 6;
    const b = a + 6;
    for (let col = 0; col < 2; col += 1) {
      indices.push(a + col, b + col, a + col + 1, a + col + 1, b + col, b + col + 1);
      indices.push(a + 3 + col, a + 4 + col, b + 3 + col, a + 4 + col, b + 4 + col, b + 3 + col);
    }
    indices.push(a, a + 3, b, a + 3, b + 3, b, a + 2, b + 2, a + 5, a + 5, b + 2, b + 5);
  }
  const last = segments * 6;
  indices.push(0, 1, 3, 1, 4, 3, 1, 2, 4, 2, 5, 4);
  indices.push(last, last + 3, last + 1, last + 1, last + 3, last + 4, last + 1, last + 4, last + 2, last + 2, last + 4, last + 5);
  const geometry = new BufferGeometry();
  geometry.setAttribute("position", new Float32BufferAttribute(positions, 3));
  geometry.setIndex(indices);
  geometry.computeVertexNormals();
  geometry.computeBoundingSphere();
  return geometry;
}

export function createBowGeometry() {
  const loop = ribbonBand([[0, .04, 0], [-.2, .25, -.01], [-.43, .16, 0], [-.23, .035, .02], [0, .045, .01]], .16);
  const tail = ribbonBand([[0, .015, 0], [-.13, -.01, .13], [-.24, -.025, .28], [-.36, -.025, .42]], .14, true);
  return { loop, tail };
}
