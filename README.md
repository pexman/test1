# Sanitarios AR

Web app (sin instalación ni servidor propio) para convertir **fotos de sanitarios** en modelos 3D a escala real y **colocarlos en tu baño con realidad aumentada**.

## Qué hace

1. **Añadir sanitario**: subes una foto (inodoro, lavabo, bidé, mueble, espejo…).
   - Se elimina el fondo automáticamente (mejor con foto sobre fondo liso, o un PNG ya sin fondo).
   - Ajustas la *tolerancia* y corriges con el pincel *Conservar / Borrar* (útil con piezas blancas sobre fondo blanco).
   - Indicas las medidas reales (cm) y la altura de montaje (p. ej. 85 cm para un lavabo suspendido).
2. **Modelo 3D**: a partir de la silueta se genera un volumen con relieve, con la foto como textura y a tamaño real.
   Si tienes el modelo 3D del fabricante (`.glb`), puedes subirlo y se usará en su lugar.
3. **Ver en mi baño (AR)**:
   - **Android (Chrome)**: WebXR. Apunta al suelo junto a la pared, toca para colocar, arrastra para mover y usa los botones para girar, subir/bajar o eliminar. Puedes colocar varias piezas a la vez y cambiar de producto desde la barra inferior.
   - **iPhone / iPad (Safari)**: se exporta a USDZ y se abre en AR Quick Look (en suelo, o en pared para espejos y piezas suspendidas).
   - **Sin AR (ordenador)**: *Foto de mi baño* pone una foto tuya de fondo para encajar el modelo y *Captura* descarga la imagen.

Los productos que añades se guardan en el navegador (IndexedDB).

## Publicarla

La AR exige **HTTPS**. La forma más sencilla es GitHub Pages:
*Settings → Pages → Deploy from a branch →* elige la rama y la carpeta `/ (root)`.
Después abre la URL desde el móvil.

En local: `python3 -m http.server 8000` y abre `http://localhost:8000` (la AR solo funcionará en `localhost` o por HTTPS).

## Incluir tus fotos en el catálogo

Copia las imágenes en `catalogo/` y añádelas a `catalogo/catalog.json`:

```json
[
  {
    "name": "Inodoro Modelo X",
    "type": "inodoro",
    "image": "inodoro-modelo-x.png",
    "width": 37, "height": 78, "depth": 65,
    "elevation": 0,
    "relief": 0.6,
    "tolerance": 40,
    "glb": "inodoro-modelo-x.glb"
  }
]
```

`type`: `inodoro`, `inodoro-suspendido`, `lavabo`, `lavabo-pedestal`, `bide`, `mueble`, `banera`, `espejo`, `otro`.
`glb` y `tolerance` son opcionales. Usa fotos **frontales** y, si puedes, PNG sin fondo.

El inodoro de ejemplo (`catalogo/ejemplo-inodoro.svg`) se puede borrar del JSON.

## Limitaciones

Una sola foto no contiene la forma trasera del objeto: el volumen generado es una aproximación que se ve bien de frente y en ángulos moderados.
Para un 3D fiel desde cualquier ángulo, usa el `.glb` del fabricante o genera uno con fotogrametría o un servicio de imagen→3D y súbelo en el campo *Modelo 3D*.

## Estructura

```
index.html              interfaz
css/styles.css
js/app.js               catálogo, editor y flujo de AR
js/imageProcessing.js   eliminación de fondo y recorte
js/modelBuilder.js      foto → malla 3D a escala; carga de GLB
js/viewer.js            visor 3D y modo «foto de mi baño»
js/ar.js                WebXR (hit-test) y AR Quick Look (USDZ)
js/storage.js           IndexedDB
catalogo/               productos incluidos con la web
```

Usa [three.js](https://threejs.org) 0.169 desde CDN; no hay paso de compilación.
