// Visor 3D de escritorio/móvil sin AR. Permite usar una foto del baño como fondo.
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

export class Viewer {
  constructor(container) {
    this.container = container;
    this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    container.appendChild(this.renderer.domElement);

    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(45, 1, 0.01, 50);
    this.camera.position.set(0.9, 1.1, 1.8);
    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controls.enableDamping = true;
    this.controls.target.set(0, 0.4, 0);

    this.scene.add(new THREE.HemisphereLight(0xffffff, 0x8a8f99, 2.2));
    const sun = new THREE.DirectionalLight(0xffffff, 1.6);
    sun.position.set(1.5, 3, 2.5);
    this.scene.add(sun);

    this.floor = new THREE.GridHelper(4, 40, 0x9aa3b0, 0xd5dae1);
    this.scene.add(this.floor);

    this.object = null;
    this.photo = null;
    new ResizeObserver(() => this.resize()).observe(container);
    this.resize();
    this.renderer.setAnimationLoop(() => {
      this.controls.update();
      this.renderer.render(this.scene, this.camera);
    });
  }

  resize() {
    const w = this.container.clientWidth || 1, h = this.container.clientHeight || 1;
    this.renderer.setSize(w, h, false);
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
  }

  show(object) {
    if (this.object) this.scene.remove(this.object);
    this.object = object;
    if (!object) return;
    this.scene.add(object);
    const box = new THREE.Box3().setFromObject(object);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    const r = Math.max(size.x, size.y, size.z, 0.2);
    this.controls.target.copy(center);
    this.camera.position.copy(center).add(new THREE.Vector3(0.7, 0.45, 1.6).multiplyScalar(r * 1.4));
    this.camera.near = r / 100;
    this.camera.far = r * 100;
    this.camera.updateProjectionMatrix();
  }

  /** Foto del baño de fondo (o null para quitarla). */
  setPhoto(url) {
    this.photo = url;
    this.container.style.backgroundImage = url ? `url("${url}")` : '';
    this.container.classList.toggle('has-photo', Boolean(url));
    this.floor.visible = !url;
  }

  /** Captura PNG combinando la foto de fondo con el render. */
  async capture() {
    const canvas = this.renderer.domElement;
    const out = document.createElement('canvas');
    out.width = canvas.width; out.height = canvas.height;
    const ctx = out.getContext('2d');
    if (this.photo) {
      const img = await new Promise((res, rej) => {
        const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = this.photo;
      });
      // misma lógica que background-size: cover
      const k = Math.max(out.width / img.width, out.height / img.height);
      const w = img.width * k, h = img.height * k;
      ctx.drawImage(img, (out.width - w) / 2, (out.height - h) / 2, w, h);
    } else {
      ctx.fillStyle = '#f4f6f9';
      ctx.fillRect(0, 0, out.width, out.height);
    }
    this.renderer.render(this.scene, this.camera);
    ctx.drawImage(canvas, 0, 0);
    return out.toDataURL('image/png');
  }
}
