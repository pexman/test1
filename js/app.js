import { listProducts, saveProduct, deleteProduct } from './storage.js';
import {
  loadImageFromBlob, loadImageFromUrl, toWorkCanvas, computeMask, applyEdits,
  makeCutout, drawMaskPreview, canvasToBlob,
} from './imageProcessing.js';
import { buildProductObject } from './modelBuilder.js';
import { Viewer } from './viewer.js';
import { ArSession, isWebXRArSupported, isQuickLookSupported, openQuickLook } from './ar.js';

// Medidas típicas (cm) para rellenar el formulario según el tipo.
const TYPES = {
  inodoro: { label: 'Inodoro', width: 37, height: 78, depth: 65, elevation: 0 },
  'inodoro-suspendido': { label: 'Inodoro suspendido', width: 36, height: 35, depth: 54, elevation: 40 },
  lavabo: { label: 'Lavabo suspendido', width: 60, height: 18, depth: 46, elevation: 85 },
  'lavabo-pedestal': { label: 'Lavabo con pedestal', width: 55, height: 85, depth: 45, elevation: 0 },
  bide: { label: 'Bidé', width: 36, height: 40, depth: 54, elevation: 0 },
  mueble: { label: 'Mueble de lavabo', width: 80, height: 50, depth: 46, elevation: 35 },
  banera: { label: 'Bañera', width: 170, height: 55, depth: 75, elevation: 0 },
  espejo: { label: 'Espejo', width: 70, height: 90, depth: 3, elevation: 110 },
  otro: { label: 'Otro', width: 40, height: 40, depth: 20, elevation: 0 },
};

const $ = (id) => document.getElementById(id);
const state = {
  products: [],          // { id, name, type, width, height, depth, elevation, relief, cutout: Blob, glb?: Blob, builtin? }
  selectedId: null,
  models: new Map(),     // id → Promise<Object3D>
  thumbs: new Map(),     // id → object URL
  ar: null,
};
const viewer = new Viewer($('viewer'));

// ---------- Catálogo ----------

async function init() {
  for (const [key, t] of Object.entries(TYPES)) $('f-type').add(new Option(t.label, key));
  const saved = await listProducts();
  const builtin = await loadBuiltinCatalog();
  state.products = [...builtin, ...saved.sort((a, b) => a.created - b.created)];
  renderCatalog();
  if (state.products.length) select(state.products[0].id);
  setupAr();
}

// Productos incluidos con la web: catalogo/catalog.json
async function loadBuiltinCatalog() {
  try {
    const res = await fetch('catalogo/catalog.json', { cache: 'no-cache' });
    if (!res.ok) return [];
    const items = await res.json();
    const out = [];
    for (const [i, item] of items.entries()) {
      try {
        const work = toWorkCanvas(await loadImageFromUrl(`catalogo/${item.image}`));
        const cutout = makeCutout(work, computeMask(work, item.tolerance ?? 40));
        if (!cutout) continue;
        let glb = null;
        if (item.glb) glb = await (await fetch(`catalogo/${item.glb}`)).blob();
        out.push({
          ...TYPES[item.type], ...item,
          id: `builtin-${i}`, builtin: true, cutout: await canvasToBlob(cutout), glb,
        });
      } catch (err) {
        console.warn('Producto del catálogo omitido:', item.name, err);
      }
    }
    return out;
  } catch {
    return [];
  }
}

function thumbUrl(p) {
  if (!state.thumbs.has(p.id)) state.thumbs.set(p.id, URL.createObjectURL(p.cutout));
  return state.thumbs.get(p.id);
}

function renderCatalog() {
  const list = $('catalog-list');
  list.replaceChildren();
  for (const p of state.products) {
    const card = document.createElement('button');
    card.className = 'card' + (p.id === state.selectedId ? ' active' : '');
    card.innerHTML = `<img alt=""><span class="card-name"></span><span class="card-dims muted"></span>`;
    card.querySelector('img').src = thumbUrl(p);
    card.querySelector('.card-name').textContent = p.name;
    card.querySelector('.card-dims').textContent = `${TYPES[p.type]?.label ?? ''} · ${dimsText(p)}`;
    card.onclick = () => select(p.id);
    list.appendChild(card);
  }
  $('catalog-empty').hidden = state.products.length > 0;
  renderArProducts();
}

const fmt = (v) => (v ? `${Math.round(v)}` : '?');
const dimsText = (p) => `${fmt(p.width)}×${fmt(p.height)}×${fmt(p.depth)} cm`;

function getModel(id) {
  if (!state.models.has(id)) {
    const p = state.products.find((x) => x.id === id);
    state.models.set(id, (async () => {
      const canvas = toWorkCanvas(await loadImageFromBlob(p.cutout));
      return buildProductObject(p, canvas);
    })());
  }
  return state.models.get(id);
}

async function select(id) {
  state.selectedId = id;
  renderCatalog();
  const p = state.products.find((x) => x.id === id);
  $('viewer-empty').hidden = Boolean(p);
  $('sel-name').textContent = p ? p.name : '—';
  $('sel-dims').textContent = '';
  for (const b of ['btn-edit', 'btn-delete']) $(b).disabled = !p || p.builtin;
  $('btn-capture').disabled = !p;
  updateArButton();
  if (!p) { viewer.show(null); return; }
  try {
    const object = await getModel(id);
    if (state.selectedId !== id) return;
    viewer.show(object);
    const s = object.meta.size;
    const elev = object.meta.elevation ? ` · a ${Math.round(object.meta.elevation * 100)} cm del suelo` : '';
    $('sel-dims').textContent = ` ${Math.round(s.x * 100)}×${Math.round(s.y * 100)}×${Math.round(s.z * 100)} cm${elev}`;
  } catch (err) {
    console.error(err);
    $('sel-dims').textContent = ' No se pudo generar el modelo 3D';
  }
}

$('btn-delete').onclick = async () => {
  const p = state.products.find((x) => x.id === state.selectedId);
  if (!p || !confirm(`¿Eliminar "${p.name}"?`)) return;
  await deleteProduct(p.id);
  state.products = state.products.filter((x) => x.id !== p.id);
  state.models.delete(p.id);
  select(state.products[0]?.id ?? null);
};

// ---------- Foto del baño y captura ----------

$('photo-input').onchange = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  if (viewer.photo) URL.revokeObjectURL(viewer.photo);
  viewer.setPhoto(URL.createObjectURL(file));
  $('btn-photo-clear').hidden = false;
  e.target.value = '';
};
$('btn-photo-clear').onclick = () => {
  URL.revokeObjectURL(viewer.photo);
  viewer.setPhoto(null);
  $('btn-photo-clear').hidden = true;
};
$('btn-capture').onclick = async () => {
  const a = document.createElement('a');
  a.href = await viewer.capture();
  a.download = 'mi-bano.png';
  a.click();
};

// ---------- Editor ----------

const editor = {
  product: null,   // producto existente que se edita (o null)
  work: null,      // canvas de trabajo con la foto
  autoMask: null,
  edits: null,     // retoques de pincel: 0 nada, 1 conservar, 2 borrar
  mask: null,
  imageChanged: false,
};

$('btn-add').onclick = () => openEditor(null);
$('btn-edit').onclick = () => openEditor(state.products.find((x) => x.id === state.selectedId));

function openEditor(product) {
  editor.product = product;
  editor.work = null;
  editor.imageChanged = false;
  $('editor-title').textContent = product ? 'Editar sanitario' : 'Nuevo sanitario';
  $('editor-error').textContent = '';
  $('f-glb').value = '';
  const p = product ?? { name: '', type: 'inodoro', ...TYPES.inodoro, relief: 0.6 };
  $('f-name').value = p.name;
  $('f-type').value = p.type;
  for (const k of ['width', 'height', 'depth', 'elevation']) $(`f-${k}`).value = p[k] ?? '';
  $('f-relief').value = p.relief ?? 0.6;
  $('editor-preview').textContent = product?.glb ? 'Usando modelo GLB guardado.' : '';
  $('mask-canvas').hidden = true;
  $('mask-tools').hidden = true;
  $('drop').hidden = false;
  if (product) loadEditorImage(product.cutout, false);
  $('editor').showModal();
}

$('f-type').onchange = () => {
  const t = TYPES[$('f-type').value];
  for (const k of ['width', 'height', 'depth', 'elevation']) $(`f-${k}`).value = t[k];
};

$('image-input').onchange = (e) => {
  if (e.target.files[0]) loadEditorImage(e.target.files[0], true);
  e.target.value = '';
};
$('mask-canvas').addEventListener('dblclick', () => $('image-input').click());

async function loadEditorImage(blob, isNew) {
  try {
    editor.work = toWorkCanvas(await loadImageFromBlob(blob));
  } catch (err) {
    $('editor-error').textContent = err.message;
    return;
  }
  editor.imageChanged = isNew;
  editor.edits = new Uint8Array(editor.work.width * editor.work.height);
  if (isNew && !$('f-name').value) $('f-name').value = (blob.name || '').replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
  recomputeMask();
  $('drop').hidden = true;
  $('mask-canvas').hidden = false;
  $('mask-tools').hidden = false;
}

function recomputeMask() {
  editor.autoMask = computeMask(editor.work, Number($('tolerance').value));
  refreshMask();
}

function refreshMask() {
  editor.mask = applyEdits(editor.autoMask, editor.edits);
  drawMaskPreview($('mask-canvas'), editor.work, editor.mask);
}

$('tolerance').oninput = () => { if (editor.work) { editor.imageChanged = true; recomputeMask(); } };

// Pincel sobre la vista previa
let painting = false;
const maskCanvas = $('mask-canvas');
maskCanvas.addEventListener('pointerdown', (e) => {
  if (brushMode() === 0) return;
  painting = true;
  maskCanvas.setPointerCapture(e.pointerId);
  paint(e);
});
maskCanvas.addEventListener('pointermove', (e) => { if (painting) paint(e); });
maskCanvas.addEventListener('pointerup', () => { painting = false; });
maskCanvas.addEventListener('pointercancel', () => { painting = false; });

function brushMode() {
  return Number(document.querySelector('input[name="brush"]:checked').value);
}

function paint(e) {
  const rect = maskCanvas.getBoundingClientRect();
  const { width: w, height: h } = editor.work;
  const cx = ((e.clientX - rect.left) / rect.width) * w;
  const cy = ((e.clientY - rect.top) / rect.height) * h;
  const r = (Number($('brush-size').value) / rect.width) * w;
  const mode = brushMode();
  for (let y = Math.max(0, Math.floor(cy - r)); y < Math.min(h, cy + r); y++) {
    for (let x = Math.max(0, Math.floor(cx - r)); x < Math.min(w, cx + r); x++) {
      if ((x - cx) ** 2 + (y - cy) ** 2 <= r * r) editor.edits[y * w + x] = mode;
    }
  }
  editor.imageChanged = true;
  refreshMask();
}

$('editor-form').addEventListener('submit', async (e) => {
  if (e.submitter?.value !== 'save') return;
  e.preventDefault();
  if (!editor.work) { $('editor-error').textContent = 'Elige una foto del sanitario.'; return; }
  let cutout = editor.product?.cutout;
  if (editor.imageChanged || !cutout) {
    const canvas = makeCutout(editor.work, editor.mask);
    if (!canvas) { $('editor-error').textContent = 'No queda nada del producto: baja la tolerancia.'; return; }
    cutout = await canvasToBlob(canvas);
  }
  const num = (id) => (($(id).value === '') ? null : Number($(id).value));
  const product = {
    id: editor.product?.id ?? `p-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    created: editor.product?.created ?? Date.now(),
    name: $('f-name').value.trim() || 'Sanitario',
    type: $('f-type').value,
    width: num('f-width'), height: num('f-height'), depth: num('f-depth'),
    elevation: num('f-elevation') ?? 0,
    relief: Number($('f-relief').value),
    cutout,
    glb: $('f-glb').files[0] ?? editor.product?.glb ?? null,
  };
  try {
    await saveProduct(product);
  } catch (err) {
    $('editor-error').textContent = `No se pudo guardar: ${err.message}`;
    return;
  }
  const idx = state.products.findIndex((x) => x.id === product.id);
  if (idx >= 0) state.products[idx] = product; else state.products.push(product);
  state.models.delete(product.id);
  if (state.thumbs.has(product.id)) { URL.revokeObjectURL(state.thumbs.get(product.id)); state.thumbs.delete(product.id); }
  $('editor').close();
  select(product.id);
});

// ---------- Realidad aumentada ----------

let arMode = 'none';

async function setupAr() {
  if (await isWebXRArSupported()) arMode = 'webxr';
  else if (isQuickLookSupported()) arMode = 'quicklook';
  const hints = {
    webxr: 'Pulsa «Ver en mi baño», apunta al suelo junto a la pared y toca para colocar. Puedes colocar varias piezas.',
    quicklook: 'Se abrirá AR Quick Look de iOS: apunta al suelo (o a la pared para espejos y piezas suspendidas).',
    none: window.isSecureContext
      ? 'Este navegador no admite AR. Ábrelo en un móvil Android con Chrome o en un iPhone/iPad. Mientras tanto, usa «Foto de mi baño».'
      : 'La AR necesita HTTPS. Publica la web (p. ej. en GitHub Pages) y ábrela desde el móvil.',
  };
  $('ar-hint').textContent = hints[arMode];
  updateArButton();
}

function updateArButton() {
  $('btn-ar').disabled = arMode === 'none' || !state.selectedId;
}

$('btn-ar').onclick = async () => {
  const id = state.selectedId;
  if (!id) return;
  try {
    if (arMode === 'quicklook') {
      const object = await getModel(id);
      const p = state.products.find((x) => x.id === id);
      await openQuickLook(object, { wall: Number(p.elevation) > 0 || p.type === 'espejo' });
    } else if (arMode === 'webxr') {
      await startWebXr(id);
    }
  } catch (err) {
    console.error(err);
    alert(`No se pudo iniciar la AR: ${err.message}`);
  }
};

async function startWebXr(id) {
  const overlay = $('ar-overlay');
  overlay.hidden = false;
  document.body.classList.add('in-ar');
  state.ar = new ArSession({
    overlay,
    getTemplate: getModel,
    onStatus: (text) => { $('ar-status').textContent = text; },
    onSelectionChange: (object) => {
      $('ar-selection').hidden = !object;
      if (object) {
        const e = Math.round(object.meta.elevation * 100);
        $('ar-sel-label').textContent = `${object.meta.product.name}${e ? ` · ${e} cm` : ''}`;
      }
    },
    onEnd: () => {
      overlay.hidden = true;
      document.body.classList.remove('in-ar');
      state.ar = null;
    },
  });
  renderArProducts();
  try {
    await state.ar.start(id);
  } catch (err) {
    state.ar = null;
    overlay.hidden = true;
    document.body.classList.remove('in-ar');
    throw err;
  }
}

function renderArProducts() {
  const strip = $('ar-products');
  strip.replaceChildren();
  for (const p of state.products) {
    const b = document.createElement('button');
    b.className = 'ar-product' + (p.id === (state.ar?.productId ?? state.selectedId) ? ' active' : '');
    b.innerHTML = '<img alt=""><span></span>';
    b.querySelector('img').src = thumbUrl(p);
    b.querySelector('span').textContent = p.name;
    b.onclick = () => {
      state.ar?.setProduct(p.id);
      getModel(p.id); // precarga
      for (const x of strip.children) x.classList.toggle('active', x === b);
      $('ar-status').textContent = `Toca el suelo para colocar: ${p.name}`;
    };
    strip.appendChild(b);
  }
}

$('ar-exit').onclick = () => state.ar?.end();
document.querySelector('.ar-controls').addEventListener('click', (e) => {
  const act = e.target.closest('[data-act]')?.dataset.act;
  const ar = state.ar;
  if (!ar) return;
  if (act === 'rot-l') ar.rotateSelected(15);
  if (act === 'rot-r') ar.rotateSelected(-15);
  if (act === 'up') ar.raiseSelected(0.05);
  if (act === 'down') ar.raiseSelected(-0.05);
  if (act === 'del') ar.removeSelected();
});

init();
