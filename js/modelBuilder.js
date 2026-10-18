// Convierte un producto (imagen recortada o GLB) en un objeto 3D a escala real.
//
// Convención de todos los modelos: unidades en metros, base en y = 0, centrado en x,
// cara trasera en z = 0 y frente hacia +z. Así el punto de anclaje es "pie de la pared".
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

const GRID_MAX = 160; // celdas de la malla en el lado mayor

/**
 * Genera un volumen "inflado" a partir de la silueta de la imagen recortada:
 * frente con la foto como textura, laterales cerrados y bordes redondeados según `relief`.
 */
export function buildCutoutMesh(cutout, { width, height, depth, relief = 0.6 }) {
  const aspect = cutout.width / cutout.height;
  const cols = aspect >= 1 ? GRID_MAX : Math.max(8, Math.round(GRID_MAX * aspect));
  const rows = aspect >= 1 ? Math.max(8, Math.round(GRID_MAX / aspect)) : GRID_MAX;

  // Ocupación por celda a partir del alfa reducido.
  const small = document.createElement('canvas');
  small.width = cols; small.height = rows;
  const sctx = small.getContext('2d');
  sctx.drawImage(cutout, 0, 0, cols, rows);
  const alpha = sctx.getImageData(0, 0, cols, rows).data;
  const solid = new Uint8Array(cols * rows);
  for (let i = 0; i < solid.length; i++) solid[i] = alpha[i * 4 + 3] > 127 ? 1 : 0;
  const cell = (i, j) => (i >= 0 && j >= 0 && i < cols && j < rows ? solid[j * cols + i] : 0);

  const dist = distanceTransform(solid, cols, rows);
  let maxDist = 1;
  for (const d of dist) if (d > maxDist) maxDist = d;

  const sx = width / cols, sy = height / rows;
  const half = depth / 2;
  const edgeFrac = 0.18;
  const round = Math.max(1, relief * maxDist);
  const profile = (d) => {
    const t = Math.min(d / round, 1);
    return edgeFrac + (1 - edgeFrac) * Math.sqrt(1 - (1 - t) * (1 - t));
  };

  // Vértices en las esquinas de las celdas.
  const cw = cols + 1, ch = rows + 1;
  const used = new Uint8Array(cw * ch);
  const boundary = new Uint8Array(cw * ch);
  const cornerDist = new Float32Array(cw * ch);
  for (let j = 0; j < ch; j++) {
    for (let i = 0; i < cw; i++) {
      const adj = [cell(i - 1, j - 1), cell(i, j - 1), cell(i - 1, j), cell(i, j)];
      const k = j * cw + i;
      if (!adj.some(Boolean)) continue;
      used[k] = 1;
      if (!adj.every(Boolean)) { boundary[k] = 1; continue; }
      cornerDist[k] = (dist[(j - 1) * cols + i - 1] + dist[(j - 1) * cols + i] +
        dist[j * cols + i - 1] + dist[j * cols + i]) / 4 - 0.5;
    }
  }

  const px = new Float32Array(cw * ch), py = new Float32Array(cw * ch);
  for (let j = 0; j < ch; j++) {
    for (let i = 0; i < cw; i++) {
      px[j * cw + i] = (i - cols / 2) * sx;
      py[j * cw + i] = (rows - j) * sy;
    }
  }
  // Suaviza el contorno escalonado moviendo las esquinas del borde hacia sus vecinas de borde.
  for (let it = 0; it < 2; it++) {
    const nx = px.slice(), ny = py.slice();
    for (let j = 0; j < ch; j++) {
      for (let i = 0; i < cw; i++) {
        const k = j * cw + i;
        if (!boundary[k]) continue;
        let ax = px[k], ay = py[k], c = 1;
        for (const [di, dj] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
          const ii = i + di, jj = j + dj;
          if (ii < 0 || jj < 0 || ii >= cw || jj >= ch) continue;
          const kk = jj * cw + ii;
          if (boundary[kk]) { ax += px[kk]; ay += py[kk]; c++; }
        }
        nx[k] = ax / c; ny[k] = ay / c;
      }
    }
    px.set(nx); py.set(ny);
  }

  const positions = [], uvs = [], indices = [];
  const frontIdx = new Int32Array(cw * ch).fill(-1);
  const backIdx = new Int32Array(cw * ch).fill(-1);
  const zOf = (k) => half * profile(boundary[k] ? 0 : cornerDist[k]);
  const uvOf = (k) => [(k % cw) / cols, 1 - Math.floor(k / cw) / rows];

  for (let k = 0; k < cw * ch; k++) {
    if (!used[k]) continue;
    const z = zOf(k);
    const [u, v] = uvOf(k);
    frontIdx[k] = positions.length / 3;
    positions.push(px[k], py[k], half + z); uvs.push(u, v);
    backIdx[k] = positions.length / 3;
    positions.push(px[k], py[k], half - z); uvs.push(u, v);
  }

  const side = (p, q) => {
    // Pared lateral entre esquinas p→q (contorno recorrido en sentido antihorario).
    const base = positions.length / 3;
    for (const k of [p, q]) {
      const z = zOf(k);
      const [u, v] = uvOf(k);
      positions.push(px[k], py[k], half + z); uvs.push(u, v);
      positions.push(px[k], py[k], half - z); uvs.push(u, v);
    }
    // base: fp, bp, fq, bq
    indices.push(base, base + 2, base + 3, base, base + 3, base + 1);
  };

  for (let j = 0; j < rows; j++) {
    for (let i = 0; i < cols; i++) {
      if (!cell(i, j)) continue;
      const a = j * cw + i, b = a + 1, c = a + cw + 1, d = a + cw;
      indices.push(frontIdx[d], frontIdx[c], frontIdx[b], frontIdx[d], frontIdx[b], frontIdx[a]);
      indices.push(backIdx[d], backIdx[b], backIdx[c], backIdx[d], backIdx[a], backIdx[b]);
      if (!cell(i, j - 1)) side(a, b);
      if (!cell(i + 1, j)) side(b, c);
      if (!cell(i, j + 1)) side(c, d);
      if (!cell(i - 1, j)) side(d, a);
    }
  }

  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
  geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uvs, 2));
  geometry.setIndex(indices);
  geometry.computeVertexNormals();

  const texture = new THREE.CanvasTexture(bleedColors(cutout));
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.anisotropy = 4;
  const material = new THREE.MeshStandardMaterial({ map: texture, roughness: 0.35, metalness: 0.0 });
  const mesh = new THREE.Mesh(geometry, material);
  mesh.name = 'producto';
  return mesh;
}

// Distancia (en celdas) de cada celda sólida al exterior; chamfer 3-4 en dos pasadas.
function distanceTransform(solid, w, h) {
  const INF = 1e9;
  const d = new Float32Array(w * h);
  for (let i = 0; i < d.length; i++) d[i] = solid[i] ? INF : 0;
  const at = (x, y) => (x < 0 || y < 0 || x >= w || y >= h ? 0 : d[y * w + x]);
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = y * w + x;
      if (!d[i]) continue;
      d[i] = Math.min(d[i], at(x - 1, y) + 3, at(x, y - 1) + 3, at(x - 1, y - 1) + 4, at(x + 1, y - 1) + 4);
    }
  }
  for (let y = h - 1; y >= 0; y--) {
    for (let x = w - 1; x >= 0; x--) {
      const i = y * w + x;
      if (!d[i]) continue;
      d[i] = Math.min(d[i], at(x + 1, y) + 3, at(x, y + 1) + 3, at(x + 1, y + 1) + 4, at(x - 1, y + 1) + 4);
    }
  }
  for (let i = 0; i < d.length; i++) d[i] /= 3;
  return d;
}

// Extiende el color de los píxeles del borde hacia la zona transparente para evitar halos.
function bleedColors(cutout) {
  const { width: w, height: h } = cutout;
  const out = document.createElement('canvas');
  out.width = w; out.height = h;
  const ctx = out.getContext('2d');
  ctx.drawImage(cutout, 0, 0);
  const img = ctx.getImageData(0, 0, w, h);
  const d = img.data;
  let filled = new Uint8Array(w * h);
  for (let i = 0; i < w * h; i++) filled[i] = d[i * 4 + 3] > 127 ? 1 : 0;
  for (let pass = 0; pass < 12; pass++) {
    const next = filled.slice();
    for (let y = 0; y < h; y++) {
      for (let x = 0; x < w; x++) {
        const i = y * w + x;
        if (filled[i]) continue;
        let r = 0, g = 0, b = 0, c = 0;
        for (const j of [x > 0 ? i - 1 : -1, x < w - 1 ? i + 1 : -1, y > 0 ? i - w : -1, y < h - 1 ? i + w : -1]) {
          if (j >= 0 && filled[j]) { r += d[j * 4]; g += d[j * 4 + 1]; b += d[j * 4 + 2]; c++; }
        }
        if (c) { d[i * 4] = r / c; d[i * 4 + 1] = g / c; d[i * 4 + 2] = b / c; next[i] = 1; }
      }
    }
    filled = next;
  }
  for (let i = 0; i < w * h; i++) d[i * 4 + 3] = 255;
  ctx.putImageData(img, 0, 0);
  return out;
}

const gltfLoader = new GLTFLoader();

/** Carga un GLB y lo normaliza a la convención (y altura real si se indica). */
export async function buildGlbModel(blob, { height }) {
  const buffer = await blob.arrayBuffer();
  const gltf = await gltfLoader.parseAsync(buffer, '');
  const model = gltf.scene;
  const box = new THREE.Box3().setFromObject(model);
  const size = box.getSize(new THREE.Vector3());
  if (height > 0 && size.y > 0) model.scale.multiplyScalar(height / size.y);
  box.setFromObject(model);
  const center = box.getCenter(new THREE.Vector3());
  model.position.sub(new THREE.Vector3(center.x, box.min.y, box.min.z));
  return model;
}

/** Sombra suave de contacto bajo el producto. */
function contactShadow(width, depth) {
  const c = document.createElement('canvas');
  c.width = c.height = 128;
  const ctx = c.getContext('2d');
  const g = ctx.createRadialGradient(64, 64, 8, 64, 64, 64);
  g.addColorStop(0, 'rgba(0,0,0,0.45)');
  g.addColorStop(1, 'rgba(0,0,0,0)');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, 128, 128);
  const mesh = new THREE.Mesh(
    new THREE.PlaneGeometry(width * 1.25, Math.max(depth, 0.05) * 1.6),
    new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthWrite: false }),
  );
  mesh.rotation.x = -Math.PI / 2;
  mesh.position.set(0, 0.002, depth / 2);
  mesh.name = 'sombra';
  return mesh;
}

/**
 * Objeto listo para colocar: grupo raíz (en el suelo) → `model` elevado `elevation` metros.
 * `product` usa centímetros como los introduce el usuario.
 */
export async function buildProductObject(product, cutoutCanvas) {
  const cm = (v) => (Number(v) || 0) / 100;
  let model;
  if (product.glb) {
    model = await buildGlbModel(product.glb, { height: cm(product.height) });
  } else {
    const aspect = cutoutCanvas.width / cutoutCanvas.height;
    let width = cm(product.width), height = cm(product.height);
    if (!width && !height) width = 0.5;
    if (!width) width = height * aspect;
    if (!height) height = width / aspect;
    model = buildCutoutMesh(cutoutCanvas, { width, height, depth: cm(product.depth) || 0.2, relief: product.relief ?? 0.6 });
  }
  const box = new THREE.Box3().setFromObject(model);
  const size = box.getSize(new THREE.Vector3());

  const root = new THREE.Group();
  const lift = new THREE.Group();
  lift.name = 'elevacion';
  lift.add(model);
  root.add(lift);
  const shadow = contactShadow(size.x, size.z);
  root.add(shadow);
  // `meta` y no `userData`: Object3D.clone() serializa userData a JSON.
  root.meta = { product, size, lift, shadow };
  setElevation(root, cm(product.elevation));
  return root;
}

export function setElevation(root, meters) {
  const e = Math.max(0, meters);
  root.meta.lift.position.y = e;
  root.meta.shadow.material.opacity = Math.max(0.15, 1 - e * 1.5);
  root.meta.elevation = e;
}

/** Clona un objeto ya construido (comparte geometría y textura, materiales de sombra propios). */
export function cloneProductObject(template) {
  const root = template.clone(true);
  const lift = root.getObjectByName('elevacion');
  const shadow = root.getObjectByName('sombra');
  shadow.material = shadow.material.clone();
  root.meta = { ...template.meta, lift, shadow };
  setElevation(root, template.meta.elevation);
  return root;
}
