# Europe population video (Remotion)

Animated 9:16 (1080×1920, 30 fps, ~25 s) version of `mapa-europa.html`: countries are counted from the smallest to the largest population, then the full ranking appears.

```sh
npm install
npm run studio   # preview and edit in the browser
npm run render   # writes out/europe-population-9x16.mp4
```

Map geometry is in `src/geo.json` (Natural Earth borders via world-atlas, LAEA projection) and the population figures are in `src/data.js`.
