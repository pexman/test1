# Europe population videos (Remotion)

Two 9:16 (1080×1920, 30 fps, 60 s) compositions:

- `Top10Europe`: countdown of the 10 most populated countries in Europe, each shown as a flag-filled silhouette with its rank and population, ending on a recap list.
- `EuropePopulation`: the map below, animated.

Animated 9:16 (1080×1920, 30 fps, 60 s) version of `mapa-europa.html`: countries are counted from the smallest to the largest population, then the full ranking appears.

```sh
npm install
npm run studio   # preview and edit in the browser
npm run render   # writes out/europe-population-9x16.mp4
npx remotion render Top10Europe out/top10-europe-population.mp4
```

Map geometry is in `src/geo.json` (Natural Earth borders via world-atlas, LAEA projection) and the population figures are in `src/data.js`.
