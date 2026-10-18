import { AbsoluteFill, interpolate, interpolateColors, spring, useCurrentFrame, useVideoConfig, Easing } from "remotion";
import { loadFont } from "@remotion/fonts";
import { staticFile } from "remotion";
import GEO from "./geo.json";
import { COUNTRIES, ALIAS, classColor, short, full, whole } from "./data";

// variable fonts (latin subset) bundled in public/fonts so rendering works offline
loadFont({ family: "Bricolage Grotesque", url: staticFile("fonts/BricolageGrotesque.woff2"), weight: "200 800" });
loadFont({ family: "Source Sans 3", url: staticFile("fonts/SourceSans3.woff2"), weight: "200 900" });
const DISPLAY = '"Bricolage Grotesque", sans-serif';
const BODY = '"Source Sans 3", sans-serif';

export const FPS = 30;
const C = { paper: "#13112b", sea: "#1c1a42", other: "#2a2852", pending: "#45427a", border: "#13112b", fg: "#ffffff", muted: "#a5a3d6", accent: "#f472b6", hot: "#facc15" };
const GRADIENT = "linear-gradient(90deg, #5eead4, #818cf8 45%, #f472b6)";
const gradientText = { backgroundImage: GRADIENT, WebkitBackgroundClip: "text", backgroundClip: "text", color: "transparent" };

// Countdown from the smallest country to the largest; the biggest ones get more screen time.
const ranked = Object.entries(COUNTRIES).sort((a, b) => b[1][2] - a[1][2]).map(([key, d], i) => ({ key, name: d[0], code: d[1], pop: d[2], rank: i + 1 }));
const INTRO = 90;
const stepLength = (rank) => (rank <= 3 ? 90 : rank <= 10 ? 54 : rank <= 20 ? 30 : 17);
const steps = [];
{
  let t = INTRO;
  for (const c of [...ranked].reverse()) { steps.push({ ...c, start: t, len: stepLength(c.rank) }); t += stepLength(c.rank); }
}
const COUNTDOWN_END = steps[steps.length - 1].start + steps[steps.length - 1].len + 15;
const LIST_IN = 30;
// exactly one minute: whatever the countdown leaves is spent holding the final ranking
export const TOTAL_FRAMES = 60 * FPS;
const byKey = Object.fromEntries(steps.map((s) => [s.key, s]));

const clamp = { extrapolateLeft: "clamp", extrapolateRight: "clamp" };

export const EuropePopulation = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const current = [...steps].reverse().find((s) => frame >= s.start);
  const total = steps.reduce((sum, s) => sum + s.pop * interpolate(frame, [s.start, s.start + s.len], [0, 1], clamp), 0);

  const headIn = spring({ frame, fps, config: { damping: 200 } });
  const listT = interpolate(frame, [COUNTDOWN_END, COUNTDOWN_END + LIST_IN], [0, 1], { ...clamp, easing: Easing.out(Easing.cubic) });

  return (
    <AbsoluteFill style={{ background: C.paper, fontFamily: BODY, color: C.fg }}>
      {/* header */}
      <div style={{ position: "absolute", left: 60, top: 36, right: 60, opacity: headIn, transform: `translateY(${(1 - headIn) * -30}px)` }}>
        <div style={{ fontFamily: DISPLAY, fontWeight: 800, fontSize: 104, letterSpacing: "-0.02em", lineHeight: 1, ...gradientText }}>EUROPE</div>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline", marginTop: 8 }}>
          <div style={{ fontFamily: DISPLAY, fontWeight: 600, fontSize: 40, color: C.fg }}>Population by country</div>
          <div style={{ fontSize: 26, color: C.muted, fontVariantNumeric: "tabular-nums" }}>
            Total <b style={{ color: C.hot }}>{whole(total)}</b> million
          </div>
        </div>
      </div>

      <MapLayer frame={frame} fps={fps} current={current} />

      {/* bottom: current country during the countdown, full ranking at the end */}
      <div style={{ position: "absolute", left: 0, right: 0, top: GEO.Y0 + GEO.H, bottom: 0 }}>
        {current && listT < 1 && <CurrentCard step={current} frame={frame} fps={fps} opacity={1 - listT} />}
        {listT > 0 && <RankList t={listT} frame={frame - COUNTDOWN_END} />}
      </div>
    </AbsoluteFill>
  );
};

const MapLayer = ({ frame, fps, current }) => {
  const mapIn = interpolate(frame, [0, INTRO - 10], [0, 1], clamp);
  return (
    <svg width={1080} height={1920} viewBox="0 0 1080 1920" style={{ position: "absolute", inset: 0 }}>
      <rect x={0} y={GEO.Y0} width={GEO.W} height={GEO.H} fill={C.sea} />
      <g opacity={mapIn}>
        {GEO.c.map((c, i) => {
          const s = byKey[ALIAS[c.n] || c.n];
          if (!s) return <path key={i} d={c.d} fill={C.other} stroke={C.border} strokeWidth={1} />;
          const fill = interpolateColors(frame, [s.start, s.start + 8], [C.pending, classColor(s.pop)]);
          return <path key={i} d={c.d} fill={fill} stroke={C.border} strokeWidth={1.2} strokeLinejoin="round" />;
        })}
        {/* outline of the country being counted */}
        {current && frame < COUNTDOWN_END &&
          GEO.c.filter((c) => (ALIAS[c.n] || c.n) === current.key).map((c, i) => (
            <path key={"o" + i} d={c.d} fill="none" stroke={C.hot} strokeWidth={4} strokeLinejoin="round" />
          ))}
        {/* capitals of small countries, with a pulse when they are counted */}
        {steps.filter((s) => s.pop < 1 || (GEO.l[s.key]?.w ?? 0) < 30).map((s) => {
          const [x, y] = GEO.p[s.key];
          const on = frame >= s.start;
          const pulse = interpolate(frame, [s.start, s.start + 20], [0, 1], clamp);
          return (
            <g key={s.key}>
              {on && pulse < 1 && <circle cx={x} cy={y} r={4 + pulse * 30} fill="none" stroke={C.hot} strokeWidth={3} opacity={1 - pulse} />}
              <circle cx={x} cy={y} r={3.5} fill={on ? classColor(s.pop) : C.muted} stroke={C.paper} strokeWidth={1.2} />
            </g>
          );
        })}
      </g>
      {/* labels pop in as each country is counted */}
      {steps.map((s) => {
        const L = GEO.l[s.key];
        if (!L || L.w < 40) return null;
        const k = spring({ frame: frame - s.start, fps, config: { damping: 14, stiffness: 180 } });
        if (k <= 0.001) return null;
        const big = L.w >= 115 && L.h >= 55 && s.key !== "Croatia";
        const halo = { paintOrder: "stroke", stroke: C.paper, strokeWidth: 5, strokeLinejoin: "round" };
        return (
          <g key={s.key} transform={`translate(${L.x} ${L.y}) scale(${k})`} style={{ fontFamily: BODY, fill: C.fg }} textAnchor="middle">
            {big ? (
              <text y={-4} style={halo}>
                <tspan x={0} fontSize={15} fontWeight={600} letterSpacing="0.06em">{s.name.toUpperCase()}</tspan>
                <tspan x={0} dy={24} fontSize={22} fontWeight={700}>{short(s.pop)}</tspan>
              </text>
            ) : (
              <text y={6} fontSize={16} fontWeight={700} style={halo}>{`${s.code} ${short(s.pop)}`}</text>
            )}
          </g>
        );
      })}
    </svg>
  );
};

const CurrentCard = ({ step, frame, fps, opacity }) => {
  const k = spring({ frame: frame - step.start, fps, config: { damping: 18, stiffness: 160 } });
  const count = step.pop * interpolate(frame, [step.start, step.start + Math.min(Math.max(step.len - 2, 5), 40)], [0, 1], { ...clamp, easing: Easing.out(Easing.cubic) });
  return (
    <div style={{ position: "absolute", inset: 0, padding: "70px 60px", opacity, display: "flex", flexDirection: "column", gap: 12 }}>
      <div style={{ fontSize: 34, fontWeight: 600, color: C.muted, letterSpacing: "0.04em" }}>
        #{step.rank} <span style={{ fontSize: 26 }}>of {ranked.length}</span>
      </div>
      <div style={{ display: "flex", alignItems: "center", gap: 24, transform: `translateX(${(1 - k) * 80}px)`, opacity: k }}>
        <div style={{ width: 34, height: 34, borderRadius: 17, background: classColor(step.pop), boxShadow: `0 0 24px ${classColor(step.pop)}`, flexShrink: 0 }} />
        <div style={{ fontFamily: DISPLAY, fontWeight: 800, fontSize: step.name.length > 16 ? 70 : 92, lineHeight: 1.05 }}>{step.name}</div>
      </div>
      <div style={{ fontFamily: DISPLAY, fontWeight: 600, fontSize: 120, ...gradientText, fontVariantNumeric: "tabular-nums", lineHeight: 1, marginTop: 10 }}>
        {full(count)}
      </div>
      <div style={{ fontSize: 34, color: C.muted }}>people</div>
    </div>
  );
};

const RankList = ({ t, frame }) => {
  const PER = 16, ROW = 30, COLW = 315;
  return (
    <div style={{ position: "absolute", inset: 0, opacity: t }}>
      {ranked.map((c, i) => {
        const col = Math.floor(i / PER), row = i % PER;
        const a = interpolate(frame - i * 0.6, [0, 12], [0, 1], clamp);
        return (
          <div key={c.key} style={{ position: "absolute", left: 60 + col * (COLW + 15), top: 38 + row * ROW, width: COLW - 8, display: "flex", alignItems: "center", gap: 8, fontSize: 20, fontVariantNumeric: "tabular-nums", opacity: a, transform: `translateY(${(1 - a) * 10}px)` }}>
            <div style={{ width: 12, height: 12, borderRadius: 6, background: classColor(c.pop) }} />
            <span style={{ color: C.muted }}>{c.rank}.</span>
            <span style={{ flex: 1 }}>{c.name.replace("Bosnia and Herzegovina", "Bosnia & Herz.")}</span>
            <b>{short(c.pop)}</b>
          </div>
        );
      })}
      <div style={{ position: "absolute", left: 60, bottom: 26, fontSize: 17, color: C.muted }}>
        M = million · K = thousand · Approximate population (2024) · Borders: Natural Earth · LAEA projection
      </div>
    </div>
  );
};
