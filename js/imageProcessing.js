// Recorte del fondo de una foto de producto y generación de la imagen recortada (PNG con alfa).

const WORK_MAX = 1024; // resolución máxima de trabajo

export function loadImageFromBlob(blob) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('No se pudo leer la imagen')); };
    img.src = url;
  });
}

export function loadImageFromUrl(url) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error(`No se pudo cargar ${url}`));
    img.src = url;
  });
}

/** Dibuja la imagen en un canvas de trabajo de tamaño acotado. */
export function toWorkCanvas(img) {
  const w0 = img.naturalWidth || img.width;
  const h0 = img.naturalHeight || img.height;
  const k = Math.min(1, WORK_MAX / Math.max(w0, h0));
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(w0 * k));
  canvas.height = Math.max(1, Math.round(h0 * k));
  canvas.getContext('2d', { willReadFrequently: true }).drawImage(img, 0, 0, canvas.width, canvas.height);
  return canvas;
}

/** true si la imagen ya trae transparencia útil (p. ej. un PNG sin fondo). */
export function hasTransparency(data) {
  let transparent = 0;
  for (let i = 3; i < data.length; i += 4) if (data[i] < 250) transparent++;
  return transparent > (data.length / 4) * 0.01;
}

/**
 * Calcula la máscara de producto (1 = producto, 0 = fondo).
 * - Si la imagen tiene alfa, se usa directamente.
 * - Si no, se rellena el fondo desde los bordes con tolerancia de color.
 */
export function computeMask(canvas, tolerance = 40) {
  const { width: w, height: h } = canvas;
  const data = canvas.getContext('2d').getImageData(0, 0, w, h).data;
  const n = w * h;
  const mask = new Uint8Array(n);

  if (hasTransparency(data)) {
    for (let i = 0; i < n; i++) mask[i] = data[i * 4 + 3] > 128 ? 1 : 0;
    return keepMainParts(mask, w, h);
  }

  const bg = borderColor(data, w, h);
  const tol2 = tolerance * tolerance;
  const isBg = (i) => {
    const dr = data[i * 4] - bg[0];
    const dg = data[i * 4 + 1] - bg[1];
    const db = data[i * 4 + 2] - bg[2];
    return dr * dr + dg * dg + db * db <= tol2;
  };

  mask.fill(1);
  const stack = new Int32Array(n);
  let sp = 0;
  const push = (i) => { if (mask[i] === 1 && isBg(i)) { mask[i] = 0; stack[sp++] = i; } };
  for (let x = 0; x < w; x++) { push(x); push((h - 1) * w + x); }
  for (let y = 0; y < h; y++) { push(y * w); push(y * w + w - 1); }
  while (sp > 0) {
    const i = stack[--sp];
    const x = i % w;
    if (x > 0) push(i - 1);
    if (x < w - 1) push(i + 1);
    if (i >= w) push(i - w);
    if (i < n - w) push(i + w);
  }
  return keepMainParts(mask, w, h);
}

// Mediana por canal de los píxeles del borde: el color de fondo dominante.
function borderColor(data, w, h) {
  const r = [], g = [], b = [];
  const add = (x, y) => { const i = (y * w + x) * 4; r.push(data[i]); g.push(data[i + 1]); b.push(data[i + 2]); };
  for (let x = 0; x < w; x++) { add(x, 0); add(x, h - 1); }
  for (let y = 0; y < h; y++) { add(0, y); add(w - 1, y); }
  const med = (a) => a.sort((p, q) => p - q)[a.length >> 1];
  return [med(r), med(g), med(b)];
}

// Elimina manchas pequeñas: conserva componentes con al menos el 2 % del área del mayor.
function keepMainParts(mask, w, h) {
  const n = w * h;
  const label = new Int32Array(n);
  const sizes = [0];
  const stack = new Int32Array(n);
  for (let s = 0; s < n; s++) {
    if (!mask[s] || label[s]) continue;
    const id = sizes.length;
    let sp = 0, size = 0;
    label[s] = id; stack[sp++] = s;
    while (sp > 0) {
      const i = stack[--sp];
      size++;
      const x = i % w;
      const nb = [x > 0 ? i - 1 : -1, x < w - 1 ? i + 1 : -1, i >= w ? i - w : -1, i < n - w ? i + w : -1];
      for (const j of nb) if (j >= 0 && mask[j] && !label[j]) { label[j] = id; stack[sp++] = j; }
    }
    sizes.push(size);
  }
  const max = Math.max(0, ...sizes);
  for (let i = 0; i < n; i++) if (mask[i] && sizes[label[i]] < max * 0.02) mask[i] = 0;
  return mask;
}

/** Aplica los retoques de pincel (1 = conservar, 2 = borrar) sobre la máscara automática. */
export function applyEdits(mask, edits) {
  const out = new Uint8Array(mask.length);
  for (let i = 0; i < mask.length; i++) out[i] = edits[i] === 1 ? 1 : edits[i] === 2 ? 0 : mask[i];
  return out;
}

/** Recorta el producto a su caja envolvente y devuelve un canvas RGBA con el fondo transparente. */
export function makeCutout(canvas, mask) {
  const { width: w, height: h } = canvas;
  let x0 = w, y0 = h, x1 = -1, y1 = -1;
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      if (mask[y * w + x]) {
        if (x < x0) x0 = x;
        if (x > x1) x1 = x;
        if (y < y0) y0 = y;
        if (y > y1) y1 = y;
      }
    }
  }
  if (x1 < 0) return null;
  const cw = x1 - x0 + 1, ch = y1 - y0 + 1;
  const src = canvas.getContext('2d').getImageData(x0, y0, cw, ch);
  for (let y = 0; y < ch; y++) {
    for (let x = 0; x < cw; x++) {
      src.data[(y * cw + x) * 4 + 3] = mask[(y + y0) * w + (x + x0)] ? 255 : 0;
    }
  }
  const out = document.createElement('canvas');
  out.width = cw; out.height = ch;
  out.getContext('2d').putImageData(src, 0, 0);
  return out;
}

/** Pinta la vista previa: imagen con el fondo atenuado y el contorno resaltado. */
export function drawMaskPreview(target, canvas, mask) {
  const { width: w, height: h } = canvas;
  target.width = w; target.height = h;
  const ctx = target.getContext('2d');
  ctx.drawImage(canvas, 0, 0);
  const img = ctx.getImageData(0, 0, w, h);
  const d = img.data;
  for (let i = 0; i < w * h; i++) {
    if (!mask[i]) {
      // fondo: damero gris para que se note qué se elimina
      const x = i % w, y = (i / w) | 0;
      const c = ((x >> 4) + (y >> 4)) & 1 ? 200 : 235;
      d[i * 4] = c; d[i * 4 + 1] = c; d[i * 4 + 2] = c; d[i * 4 + 3] = 255;
    } else {
      const x = i % w;
      const edge = (x > 0 && !mask[i - 1]) || (x < w - 1 && !mask[i + 1]) ||
        (i >= w && !mask[i - w]) || (i < w * h - w && !mask[i + w]);
      if (edge) { d[i * 4] = 0; d[i * 4 + 1] = 160; d[i * 4 + 2] = 255; }
    }
  }
  ctx.putImageData(img, 0, 0);
}

export function canvasToBlob(canvas) {
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}
