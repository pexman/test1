import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig, Easing, staticFile } from "remotion";
import { loadFont } from "@remotion/fonts";
import SHAPES from "./shapes.json";
import { COUNTRIES, full } from "./data";
import { Flag } from "./Flag";

loadFont({ family: "Anton", url: staticFile("fonts/Anton.woff2") });
const ANTON = '"Anton", Impact, sans-serif';

export const FPS = 30;
export const TOTAL_FRAMES = 60 * FPS;

const C = { white: "#ffffff", ink: "#0b0820", cyan: "#22d3ee", gold: "#facc15" };
const outline = (px) => ({ WebkitTextStroke: `${px}px ${C.ink}`, paintOrder: "stroke fill" });

// Top 10 by population, shown from #10 to #1; the podium gets more time.
const TOP = Object.entries(COUNTRIES).sort((a, b) => b[1][2] - a[1][2]).slice(0, 10)
  .map(([key, d], i) => ({ key, name: d[0], pop: d[2], rank: i + 1 }));
const INTRO = 90;
const lengthFor = (rank) => (rank === 1 ? 210 : rank <= 3 ? 180 : 140);
const SCENES = [];
{
  let t = INTRO;
  for (const c of [...TOP].reverse()) { SCENES.push({ ...c, start: t, len: lengthFor(c.rank) }); t += lengthFor(c.rank); }
}
const RECAP = SCENES[SCENES.length - 1].start + SCENES[SCENES.length - 1].len;
const clamp = { extrapolateLeft: "clamp", extrapolateRight: "clamp" };

export const Top10 = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const scene = SCENES.find((s) => frame >= s.start && frame < s.start + s.len);

  // the title starts large in the middle and settles at the top
  const settle = interpolate(frame, [INTRO - 40, INTRO - 5], [0, 1], { ...clamp, easing: Easing.inOut(Easing.cubic) });
  const titleIn = spring({ frame, fps, config: { damping: 12 } });

  return (
    <AbsoluteFill style={{ fontFamily: ANTON, color: C.white }}>
      <Sky frame={frame} />
      <div style={{
        position: "absolute", left: 0, right: 0, top: interpolate(settle, [0, 1], [700, 90]), textAlign: "center",
        fontSize: 112, lineHeight: 1.05, letterSpacing: "0.01em", ...outline(14),
        transform: `scale(${interpolate(settle, [0, 1], [1, 1]) * titleIn})`,
      }}>
        <div>TOP <span style={{ color: C.cyan }}>10</span> MOST POPULATED</div>
        <div>COUNTRIES IN EUROPE</div>
      </div>
      {scene && <Scene s={scene} frame={frame - scene.start} fps={fps} />}
      {frame >= RECAP && <Recap frame={frame - RECAP} fps={fps} />}
    </AbsoluteFill>
  );
};

// animated sunset-style gradient with drifting glows
const Sky = ({ frame }) => {
  const t = frame / 30;
  const blob = (x, y, r, color, sp, ph) => (
    <div style={{
      position: "absolute", width: r, height: r, borderRadius: "50%", background: color, filter: "blur(90px)", opacity: 0.75,
      left: x + Math.sin(t * sp + ph) * 120 - r / 2, top: y + Math.cos(t * sp * 0.8 + ph) * 90 - r / 2,
    }} />
  );
  return (
    <AbsoluteFill style={{ background: "linear-gradient(180deg, #1e0b4b 0%, #5b1a7a 38%, #d9406e 70%, #ff8a3d 100%)", overflow: "hidden" }}>
      {blob(200, 300, 700, "#7c3aed", 0.5, 0)}
      {blob(900, 900, 800, "#ec4899", 0.4, 2)}
      {blob(300, 1600, 900, "#fb923c", 0.35, 4)}
      {blob(950, 250, 500, "#22d3ee", 0.3, 1)}
      <AbsoluteFill style={{ background: "radial-gradient(ellipse at center, transparent 55%, rgba(11,8,32,.55) 100%)" }} />
    </AbsoluteFill>
  );
};

const Scene = ({ s, frame, fps }) => {
  const shape = SHAPES[s.key];
  const [[x0, y0], [x1, y1]] = shape.b;
  const enter = spring({ frame, fps, config: { damping: 11, stiffness: 140 } });
  const exit = interpolate(frame, [s.len - 10, s.len], [0, 1], { ...clamp, easing: Easing.in(Easing.cubic) });
  const drift = interpolate(frame, [0, s.len], [1, 1.07]);
  const scale = (0.25 + 0.75 * enter) * drift * (1 + exit * 0.6);
  const rot = interpolate(enter, [0, 1], [-10, 0]);
  const numPop = spring({ frame: frame - 8, fps, config: { damping: 9, stiffness: 180 } });
  const textIn = spring({ frame: frame - 14, fps, config: { damping: 14 } });
  const count = s.pop * interpolate(frame, [14, 60], [0, 1], { ...clamp, easing: Easing.out(Easing.cubic) });
  const flash = interpolate(frame, [0, 6], [0.7, 0], clamp);
  const crown = s.rank === 1 ? spring({ frame: frame - 40, fps, config: { damping: 8, stiffness: 120 } }) : 0;
  const cx = (x0 + x1) / 2, cy = (y0 + y1) / 2;
  const id = s.key.replace(/\W/g, "");

  return (
    <AbsoluteFill style={{ opacity: 1 - exit, filter: `blur(${exit * 14}px)` }}>
      {/* silhouette filled with the flag, centred in a 900×900 box */}
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0, overflow: "visible" }}>
        <defs><clipPath id={`clip-${id}`}><path d={shape.d} /></clipPath></defs>
        <g transform={`translate(540 ${960 - 40}) rotate(${rot}) scale(${scale}) translate(${-cx} ${-cy})`}>
          <path d={shape.d} fill={C.ink} transform="translate(10 18)" opacity={0.45} />
          <g clipPath={`url(#clip-${id})`}>
            <Flag country={s.key} id={`f-${id}`} x={x0} y={y0} w={x1 - x0} h={y1 - y0} />
          </g>
          <path d={shape.d} fill="none" stroke={C.white} strokeWidth={4 / scale} strokeLinejoin="round" opacity={0.9} />
          {crown > 0 && <Crown x={cx} y={y0 - 20} k={crown} />}
        </g>
      </svg>
      {/* big rank number */}
      <div style={{ position: "absolute", left: 0, right: 0, top: 960 - 40 - 190, textAlign: "center", fontSize: 340, lineHeight: 1, ...outline(22), transform: `scale(${numPop})` }}>
        {s.rank}
      </div>
      {/* name and population */}
      <div style={{ position: "absolute", left: 0, right: 0, top: 1440, textAlign: "center", opacity: textIn, transform: `translateY(${(1 - textIn) * 60}px)` }}>
        <div style={{ fontSize: s.name.length > 12 ? 104 : 130, lineHeight: 1.1, ...outline(14) }}>{s.name.toUpperCase()}</div>
        <div style={{ fontSize: 112, lineHeight: 1.1, color: C.gold, fontVariantNumeric: "tabular-nums", ...outline(14) }}>{full(count)}</div>
        <div style={{ fontSize: 54, letterSpacing: "0.12em", ...outline(10) }}>PEOPLE</div>
      </div>
      <AbsoluteFill style={{ background: C.white, opacity: flash }} />
    </AbsoluteFill>
  );
};

const Crown = ({ x, y, k }) => (
  <g transform={`translate(${x} ${y - (1 - k) * 400}) scale(${1.4})`} opacity={Math.min(1, k * 1.5)}>
    <path d="M-120,0 L-140,-120 L-70,-60 L0,-150 L70,-60 L140,-120 L120,0 Z" fill={C.gold} stroke={C.ink} strokeWidth={8} strokeLinejoin="round" />
    {[-140, 0, 140].map((cx, i) => <circle key={i} cx={cx} cy={i === 1 ? -150 : -120} r={16} fill={C.gold} stroke={C.ink} strokeWidth={6} />)}
    <rect x={-120} y={-14} width={240} height={22} rx={6} fill="#eab308" stroke={C.ink} strokeWidth={6} />
  </g>
);

const Recap = ({ frame, fps }) => {
  const ROW = 104;
  return (
    <AbsoluteFill>
      {TOP.map((c, i) => {
        const k = spring({ frame: frame - (9 - i) * 4, fps, config: { damping: 14 } });
        const y = 500 + i * ROW;
        return (
          <div key={c.key} style={{ position: "absolute", left: 70, right: 70, top: y, height: ROW - 16, display: "flex", alignItems: "center", gap: 28,
            opacity: k, transform: `translateX(${(1 - k) * (i % 2 ? 300 : -300)}px)`, background: "rgba(11,8,32,.45)", borderRadius: 18, padding: "0 28px" }}>
            <div style={{ width: 70, fontSize: 64, textAlign: "center", color: i === 0 ? C.gold : C.white, ...outline(8) }}>{c.rank}</div>
            <svg width={96} height={64} style={{ borderRadius: 8, flexShrink: 0, boxShadow: "0 0 0 3px #0b0820" }}>
              <Flag country={c.key} id={`r-${c.key.replace(/\W/g, "")}`} x={0} y={0} w={96} h={64} />
            </svg>
            <div style={{ flex: 1, fontSize: 58, ...outline(8) }}>{c.name.toUpperCase()}</div>
            <div style={{ fontSize: 58, color: C.gold, ...outline(8) }}>{(c.pop).toFixed(1)}M</div>
          </div>
        );
      })}
    </AbsoluteFill>
  );
};
