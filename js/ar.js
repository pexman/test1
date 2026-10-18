// Realidad aumentada: WebXR (Android/Chrome) y AR Quick Look (iPhone/iPad).
import * as THREE from 'three';
import { USDZExporter } from 'three/addons/exporters/USDZExporter.js';
import { cloneProductObject, setElevation } from './modelBuilder.js';

export async function isWebXRArSupported() {
  try {
    return Boolean(navigator.xr && await navigator.xr.isSessionSupported('immersive-ar'));
  } catch {
    return false;
  }
}

export function isQuickLookSupported() {
  const a = document.createElement('a');
  return Boolean(a.relList && a.relList.supports && a.relList.supports('ar'));
}

/** Exporta el objeto a USDZ y lo abre en AR Quick Look. */
export async function openQuickLook(object, { wall = false } = {}) {
  const exporter = new USDZExporter();
  const model = object.meta.lift.clone(true);
  // En Quick Look el anclaje a pared lo resuelve iOS: no se aplica la elevación.
  if (wall) model.position.y = 0;
  const scene = new THREE.Scene();
  scene.add(model);
  const usdz = await exporter.parseAsync(scene, {
    ar: { anchoring: { type: 'plane' }, planeAnchoring: { alignment: wall ? 'vertical' : 'horizontal' } },
    includeAnchoringProperties: true,
    quickLookCompatible: true,
    maxTextureSize: 1024,
  });
  const url = URL.createObjectURL(new Blob([usdz], { type: 'model/vnd.usdz+zip' }));
  const a = document.createElement('a');
  a.rel = 'ar';
  a.href = `${url}#allowsContentScaling=0`;
  a.appendChild(document.createElement('img')); // Quick Look exige un <img> dentro del enlace
  document.body.appendChild(a);
  a.click();
  setTimeout(() => { a.remove(); URL.revokeObjectURL(url); }, 60000);
}

/**
 * Sesión WebXR con hit-test: toca para colocar, arrastra un objeto para moverlo
 * y usa los botones para girar, subir/bajar o eliminar el seleccionado.
 *
 * `getTemplate(id)` devuelve (Promise) el objeto 3D del producto.
 */
export class ArSession {
  constructor({ overlay, getTemplate, onStatus, onSelectionChange, onEnd }) {
    this.overlay = overlay;
    this.getTemplate = getTemplate;
    this.onStatus = onStatus;
    this.onSelectionChange = onSelectionChange;
    this.onEnd = onEnd;
    this.placed = [];
    this.selected = null;
    this.productId = null;
    this.dragging = null;
  }

  async start(productId) {
    this.productId = productId;
    const session = await navigator.xr.requestSession('immersive-ar', {
      requiredFeatures: ['hit-test'],
      optionalFeatures: ['dom-overlay', 'light-estimation'],
      domOverlay: { root: this.overlay },
    });
    this.session = session;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.xr.enabled = true;
    renderer.domElement.classList.add('ar-canvas');
    document.body.appendChild(renderer.domElement);
    this.renderer = renderer;

    const scene = new THREE.Scene();
    this.scene = scene;
    this.camera = new THREE.PerspectiveCamera(70, window.innerWidth / window.innerHeight, 0.01, 30);
    scene.add(new THREE.HemisphereLight(0xffffff, 0x777777, 2.4));
    const sun = new THREE.DirectionalLight(0xffffff, 1.2);
    sun.position.set(0.5, 2, 1);
    scene.add(sun);

    this.reticle = makeReticle();
    scene.add(this.reticle);

    renderer.xr.setReferenceSpaceType('local');
    await renderer.xr.setSession(session);

    this.viewerSpace = await session.requestReferenceSpace('viewer');
    this.hitSource = await session.requestHitTestSource({ space: this.viewerSpace });
    this.touchHitSource = await session.requestHitTestSourceForTransientInput({ profile: 'generic-touchscreen' })
      .catch(() => null);

    // Los toques sobre los botones del overlay no deben colocar objetos.
    this.overlay.addEventListener('beforexrselect', this.blockUiSelect = (e) => {
      if (e.target.closest('[data-ui]')) e.preventDefault();
    });

    const controller = renderer.xr.getController(0);
    scene.add(controller);
    controller.addEventListener('selectstart', () => this.onSelectStart(controller));
    controller.addEventListener('selectend', () => { this.dragging = null; });
    controller.addEventListener('select', () => this.onSelect());

    session.addEventListener('end', () => this.cleanup());
    renderer.setAnimationLoop((t, frame) => this.onFrame(frame));
    this.onStatus('Mueve el móvil despacio para detectar el suelo…');
  }

  onFrame(frame) {
    if (!frame) return;
    const refSpace = this.renderer.xr.getReferenceSpace();
    const hits = this.hitSource ? frame.getHitTestResults(this.hitSource) : [];
    if (hits.length) {
      const pose = hits[0].getPose(refSpace);
      this.reticle.visible = true;
      this.reticle.matrix.fromArray(pose.transform.matrix);
      if (!this.surfaceFound) {
        this.surfaceFound = true;
        this.onStatus('Apunta al pie de la pared y toca para colocar.');
      }
    } else {
      this.reticle.visible = false;
    }

    if (this.dragging && this.touchHitSource) {
      for (const result of frame.getHitTestResultsForTransientInput(this.touchHitSource)) {
        if (!result.results.length) continue;
        const p = result.results[0].getPose(refSpace).transform.position;
        this.dragging.object.position.set(p.x, p.y, p.z).add(this.dragging.offset);
        this.dragging.moved = true;
      }
    }
    this.renderer.render(this.scene, this.camera);
  }

  onSelectStart(controller) {
    controller.updateMatrixWorld(true);
    const ray = new THREE.Raycaster();
    const m = new THREE.Matrix4().extractRotation(controller.matrixWorld);
    ray.ray.origin.setFromMatrixPosition(controller.matrixWorld);
    ray.ray.direction.set(0, 0, -1).applyMatrix4(m);
    const hit = ray.intersectObjects(this.placed, true)[0];
    this.pressedOnObject = Boolean(hit);
    if (!hit) return;
    let root = hit.object;
    while (root.parent && !this.placed.includes(root)) root = root.parent;
    this.select(root);
    // Desplazamiento entre el punto tocado (en el suelo) y el origen del objeto.
    const floorPoint = hit.point.clone();
    floorPoint.y = root.position.y;
    this.dragging = { object: root, offset: root.position.clone().sub(floorPoint), moved: false };
  }

  async onSelect() {
    if (this.pressedOnObject) { this.pressedOnObject = false; return; }
    if (!this.reticle.visible || !this.productId) return;
    const pose = new THREE.Vector3().setFromMatrixPosition(this.reticle.matrix);
    const template = await this.getTemplate(this.productId);
    const object = cloneProductObject(template);
    object.position.copy(pose);
    // De frente al usuario
    const cam = this.renderer.xr.getCamera().position;
    object.rotation.y = Math.atan2(cam.x - pose.x, cam.z - pose.z);
    this.scene.add(object);
    this.placed.push(object);
    this.select(object);
    this.onStatus('Arrastra para mover · usa los botones para girar o elevar.');
  }

  select(object) {
    if (this.selected) highlight(this.selected, false);
    this.selected = object;
    if (object) highlight(object, true);
    this.onSelectionChange(object);
  }

  setProduct(id) { this.productId = id; }

  rotateSelected(deg) {
    if (this.selected) this.selected.rotation.y += THREE.MathUtils.degToRad(deg);
  }

  raiseSelected(meters) {
    if (!this.selected) return;
    setElevation(this.selected, this.selected.meta.elevation + meters);
    this.onSelectionChange(this.selected);
  }

  removeSelected() {
    if (!this.selected) return;
    this.scene.remove(this.selected);
    this.placed = this.placed.filter((o) => o !== this.selected);
    this.select(this.placed[this.placed.length - 1] || null);
  }

  end() { this.session?.end(); }

  cleanup() {
    this.renderer.setAnimationLoop(null);
    this.hitSource?.cancel();
    this.touchHitSource?.cancel();
    this.overlay.removeEventListener('beforexrselect', this.blockUiSelect);
    this.renderer.domElement.remove();
    this.renderer.dispose();
    this.onEnd();
  }
}

function makeReticle() {
  const group = new THREE.Mesh(
    new THREE.RingGeometry(0.07, 0.09, 40).rotateX(-Math.PI / 2),
    new THREE.MeshBasicMaterial({ color: 0xffffff }),
  );
  const dot = new THREE.Mesh(
    new THREE.CircleGeometry(0.012, 20).rotateX(-Math.PI / 2),
    new THREE.MeshBasicMaterial({ color: 0x00a0ff }),
  );
  group.add(dot);
  group.matrixAutoUpdate = false;
  group.visible = false;
  return group;
}

function highlight(object, on) {
  const shadow = object.meta.shadow;
  shadow.material.color.set(on ? 0x0070ff : 0x000000);
}
