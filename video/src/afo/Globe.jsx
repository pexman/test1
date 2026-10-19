import { geoOrthographic, geoPath, geoGraticule10, geoInterpolate, geoCircle } from "d3-geo";
import { feature } from "topojson-client";
import LAND_TOPO from "../land-110m.json";

const LAND = feature(LAND_TOPO, LAND_TOPO.objects.land);
const GRATICULE = geoGraticule10();

// Orthographic globe. `center` is [lon, lat] facing the viewer; overlays get the projection.
export const Globe = ({ center, cx = 540, cy = 960, r = 460, night = false, children }) => {
  const proj = geoOrthographic().rotate([-center[0], -center[1]]).translate([cx, cy]).scale(r).clipAngle(90);
  const path = geoPath(proj);
  return (
    <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
      <defs>
        <radialGradient id="ocean" cx="40%" cy="35%" r="75%">
          <stop offset="0%" stopColor={night ? "#12305e" : "#2f7fd1"} />
          <stop offset="100%" stopColor={night ? "#050d22" : "#0b2a66"} />
        </radialGradient>
        <radialGradient id="glow" cx="50%" cy="50%" r="50%">
          <stop offset="86%" stopColor="#5cc8ff" stopOpacity="0.45" />
          <stop offset="100%" stopColor="#5cc8ff" stopOpacity="0" />
        </radialGradient>
      </defs>
      <circle cx={cx} cy={cy} r={r * 1.16} fill="url(#glow)" />
      <path d={path({ type: "Sphere" })} fill="url(#ocean)" />
      <path d={path(GRATICULE)} fill="none" stroke="#ffffff" strokeOpacity={0.08} strokeWidth={1} />
      <path d={path(LAND)} fill={night ? "#1f4d3a" : "#3f9d5c"} stroke={night ? "#2f6b52" : "#7fd39a"} strokeWidth={1} />
      {children && children(proj, path)}
    </svg>
  );
};

export const arcPath = (path, a, b, t) => {
  const interp = geoInterpolate(a, b);
  const n = Math.max(2, Math.round(80 * t));
  return path({ type: "LineString", coordinates: Array.from({ length: n }, (_, i) => interp((i / (n - 1)) * t)) });
};

export const ringPath = (path, center, radius) => path(geoCircle().center(center).radius(Math.max(0.01, radius))());
