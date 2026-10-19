import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig, Easing, staticFile } from "remotion";
import { loadFont } from "@remotion/fonts";
import { PlaneSide, PlaneTop, Tanker, TOP_TANKS } from "./Planes";
import { Globe, arcPath, ringPath } from "./Globe";

loadFont({ family: "Montserrat", url: staticFile("fonts/Montserrat.woff2"), weight: "500 900" });
const FONT = '"Montserrat", "Segoe UI", sans-serif';
export const FPS = 30;
const clamp = { extrapolateLeft: "clamp", extrapolateRight: "clamp" };
const ease = { ...clamp, easing: Easing.inOut(Easing.cubic) };

// ── script: each scene has its narration (shown as captions) and an optional on-screen figure ──
const SCRIPT = [
  { id: "hook", text: ["Este es el avión más protegido del planeta."] },
  { id: "name", text: ["Air Force One no es un avión concreto:", "es el indicativo de cualquier avión de la Fuerza Aérea que lleve al presidente de Estados Unidos."] },
  { id: "pair", stat: ["1990", "en servicio desde"], text: ["Hoy son dos Boeing 747 modificados, los VC-25A,", "con matrículas 28000 y 29000,", "y vuelan desde 1990."] },
  { id: "size", stat: ["70,7 m", "de largo · 59,6 m de envergadura"], text: ["Mide 70,7 metros de largo", "y 59,6 metros de punta a punta de las alas."] },
  { id: "range", stat: ["12.550 km", "sin repostar (7.800 mi)"], text: ["Puede volar unos 12.550 kilómetros sin repostar:", "de Washington a Tokio de un tirón."] },
  { id: "refuel", stat: ["≈ 3.400 L/min", "de combustible en pleno vuelo"], text: ["Y además puede repostar en el aire,", "así que su autonomía es prácticamente ilimitada."] },
  { id: "fuel", stat: ["160 t", "de combustible a bordo"], text: ["Sus tanques cargan más de 160 toneladas de combustible."] },
  { id: "emp", stat: ["50.000 V/m", "pico del pulso, en nanosegundos"], text: ["Su secreto mejor guardado:", "está blindado contra el pulso electromagnético de una explosión nuclear."] },
  { id: "hawaii", stat: ["≈ 1.400 km", "de la explosión a Honolulu"], text: ["En 1962, una prueba nuclear sobre el Pacífico", "apagó farolas en Hawái, a unos 1.400 kilómetros."] },
  { id: "cable", text: ["Por eso su cableado va envuelto en una malla metálica", "que desvía esa energía antes de que llegue a los equipos."] },
  { id: "inside", stat: ["370 m²", "repartidos en tres niveles"], text: ["Dentro hay 370 metros cuadrados en tres niveles:", "suite presidencial, despacho, sala de reuniones…", "y hasta un quirófano, con un médico siempre a bordo."] },
  { id: "outro", text: ["Por algo lo llaman la Casa Blanca volante."] },
];

const WORD = 9, HOLD = 16, PAD = 14, FADE = 12;
const chunk = (sentence) => {
  const w = sentence.split(" "), out = [];
  for (let i = 0; i < w.length; i += 6) out.push(w.slice(i, i + 6));
  // avoid a lonely last word
  if (out.length > 1 && out[out.length - 1].length < 3) { const last = out.pop(); out[out.length - 1].push(...last); }
  return out;
};
const SCENES = [];
{
  let t = 0;
  for (const s of SCRIPT) {
    let local = PAD;
    const caps = s.text.flatMap(chunk).map((words) => { const c = { words, start: local, len: words.length * WORD + HOLD }; local += c.len; return c; });
    const dur = local + PAD;
    SCENES.push({ ...s, caps, start: t, dur });
    t += dur;
  }
}
export const TOTAL_FRAMES = SCENES[SCENES.length - 1].start + SCENES[SCENES.length - 1].dur;

export const AirForceOne = () => (
  <AbsoluteFill style={{ background: "#050a18", fontFamily: FONT }}>
    {SCENES.map((s, i) => (
      <Sequence key={s.id} from={s.start} durationInFrames={s.dur + (i < SCENES.length - 1 ? FADE : 0)}>
        <SceneShell s={s} last={i === SCENES.length - 1} />
      </Sequence>
    ))}
  </AbsoluteFill>
);

const VISUALS = {};
const SceneShell = ({ s }) => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const V = VISUALS[s.id];
  const opacity = interpolate(f, [0, FADE], [0, 1], clamp);
  return (
    <AbsoluteFill style={{ opacity }}>
      <V f={f} dur={s.dur} fps={fps} s={s} />
      {s.stat && <Stat stat={s.stat} f={f} fps={fps} />}
      <Captions caps={s.caps} f={f} fps={fps} />
    </AbsoluteFill>
  );
};

// ── overlays ──
const shadow = "0 4px 0 rgba(0,0,0,.55), 0 0 18px rgba(0,0,0,.6)";
const Stat = ({ stat: [big, small], f, fps }) => {
  const k = spring({ frame: f - 6, fps, config: { damping: 14 } });
  return (
    <div style={{ position: "absolute", left: 60, top: 110, opacity: k, transform: `translateX(${(1 - k) * -80}px)` }}>
      <div style={{ fontSize: 104, fontWeight: 900, color: "#fff", textShadow: shadow, lineHeight: 1 }}>{big}</div>
      <div style={{ display: "inline-block", marginTop: 14, background: "#fff", color: "#0b1020", fontSize: 34, fontWeight: 700, padding: "8px 22px", letterSpacing: "0.04em" }}>{small}</div>
    </div>
  );
};

const Captions = ({ caps, f, fps }) => {
  const c = caps.find((c) => f >= c.start && f < c.start + c.len);
  if (!c) return null;
  const lf = f - c.start;
  const shown = Math.min(c.words.length, Math.floor(lf / WORD) + 1);
  return (
    <div style={{ position: "absolute", left: 70, right: 70, top: 1430, textAlign: "center", fontSize: 66, fontWeight: 800, lineHeight: 1.2, color: "#fff", textShadow: shadow }}>
      {c.words.slice(0, shown).map((w, i) => {
        const k = spring({ frame: lf - i * WORD, fps, config: { damping: 12, stiffness: 220 } });
        return (
          <span key={i} style={{ display: "inline-block", marginRight: 18, color: i === shown - 1 ? "#ffd400" : "#fff", transform: `scale(${0.6 + 0.4 * k})` }}>{w}</span>
        );
      })}
    </div>
  );
};

// ── backgrounds ──
const Sky = ({ f, children, top = "#2a6fc4", bottom = "#a9d4f5" }) => (
  <AbsoluteFill style={{ background: `linear-gradient(180deg, ${top}, ${bottom})`, overflow: "hidden" }}>
    {[[200, 400, 500], [800, 700, 420], [400, 1150, 600], [900, 1500, 520], [100, 1750, 480]].map(([x, y, w], i) => (
      <div key={i} style={{ position: "absolute", left: ((x - f * (1.5 + i * 0.6)) % 1600 + 1600) % 1600 - 300, top: y, width: w, height: w * 0.32, borderRadius: "50%", background: "rgba(255,255,255,.75)", filter: "blur(28px)" }} />
    ))}
    {children}
  </AbsoluteFill>
);
const Space = ({ children }) => (
  <AbsoluteFill style={{ background: "radial-gradient(ellipse at 50% 40%, #0d1b3d, #02040c 70%)" }}>
    {Array.from({ length: 70 }, (_, i) => <div key={i} style={{ position: "absolute", left: (i * 397) % 1080, top: (i * 731) % 1920, width: 3, height: 3, borderRadius: 2, background: "#fff", opacity: 0.25 + ((i * 37) % 10) / 15 }} />)}
    {children}
  </AbsoluteFill>
);
const Blueprint = ({ children }) => (
  <AbsoluteFill style={{ background: "#0a2a5e", backgroundImage: "linear-gradient(rgba(255,255,255,.08) 2px, transparent 2px), linear-gradient(90deg, rgba(255,255,255,.08) 2px, transparent 2px)", backgroundSize: "60px 60px" }}>{children}</AbsoluteFill>
);

// ── scenes ──
VISUALS.hook = ({ f, dur, fps }) => {
  const lon = interpolate(f, [0, dur], [-40, -80]);
  const t = interpolate(f, [0, dur], [0, 1]);
  const title = spring({ frame: f - 10, fps, config: { damping: 10 } });
  return (
    <Space>
      <Globe center={[lon, 30]} r={interpolate(f, [0, dur], [420, 520])} />
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <g transform={`translate(${interpolate(t, [0, 1], [150, 700])} ${interpolate(t, [0, 1], [1400, 520])}) rotate(40) scale(0.45) translate(-300 -350)`}><PlaneTop id="hook" /></g>
      </svg>
      <div style={{ position: "absolute", top: 250, left: 0, right: 0, textAlign: "center", fontSize: 100, fontWeight: 900, color: "#fff", letterSpacing: "0.02em", textShadow: shadow, transform: `scale(${title})` }}>AIR FORCE ONE</div>
    </Space>
  );
};

VISUALS.name = ({ f, dur }) => (
  <Sky f={f}>
    <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
      <g transform={`translate(${interpolate(f, [0, dur], [1100, -120])} ${880 + Math.sin(f / 20) * 10}) rotate(-4) scale(1.15)`}><PlaneSide id="name" /></g>
    </svg>
  </Sky>
);

VISUALS.pair = ({ f, fps }) => {
  const a = spring({ frame: f - 10, fps, config: { damping: 16 } }), b = spring({ frame: f - 30, fps, config: { damping: 16 } });
  const tag = (txt, x, y, k) => (
    <div style={{ position: "absolute", left: x, top: y, fontSize: 44, fontWeight: 800, color: "#ffd400", textShadow: shadow, opacity: k }}>{txt}</div>
  );
  return (
    <Sky f={f} top="#0f3c7a" bottom="#5aa0e0">
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <g transform={`translate(${60 + (1 - a) * -900} 640) scale(0.95)`}><PlaneSide id="p1" /></g>
        <g transform={`translate(${100 + (1 - b) * 1100} 1010) scale(0.95)`}><PlaneSide id="p2" /></g>
      </svg>
      {tag("Nº 28000", 640, 600, a)}
      {tag("Nº 29000", 680, 970, b)}
    </Sky>
  );
};

VISUALS.size = ({ f }) => {
  const d1 = interpolate(f, [20, 60], [0, 1], ease), d2 = interpolate(f, [70 , 110], [0, 1], ease);
  const S = 1.0, X = 40, Y = 470;
  return (
    <Blueprint>
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <g transform={`translate(${X} ${Y}) scale(${S})`}><PlaneSide outline /></g>
        {/* length */}
        <g stroke="#ffd400" strokeWidth={4} opacity={d1}>
          <line x1={X + 6} y1={Y + 260} x2={X + 6 + 992 * d1} y2={Y + 260} />
          <line x1={X + 6} y1={Y + 240} x2={X + 6} y2={Y + 280} /><line x1={X + 998} y1={Y + 240} x2={X + 998} y2={Y + 280} opacity={d1 >= 1 ? 1 : 0} />
        </g>
        <text x={540} y={Y + 320} textAnchor="middle" fontSize={56} fontWeight={900} fill="#ffd400" opacity={d1} fontFamily={FONT}>70,7 m</text>
        {/* wingspan from above */}
        <g transform="translate(540 1040) scale(0.62) translate(-300 -350)" opacity={d2}>
          <PlaneTop id="sz" wingFill="none" />
        </g>
        <g stroke="#ffd400" strokeWidth={4} opacity={d2}>
          <line x1={540 - 174 * d2} y1={1170} x2={540 + 174 * d2} y2={1170} />
          <line x1={366} y1={1150} x2={366} y2={1190} /><line x1={714} y1={1150} x2={714} y2={1190} />
        </g>
        <text x={540} y={1250} textAnchor="middle" fontSize={56} fontWeight={900} fill="#ffd400" opacity={d2} fontFamily={FONT} style={{ paintOrder: "stroke", stroke: "#0a2a5e", strokeWidth: 10 }}>59,6 m</text>
      </svg>
    </Blueprint>
  );
};

VISUALS.range = ({ f, dur }) => {
  const DC = [-77.04, 38.9], TOKYO = [139.69, 35.68];
  const t = interpolate(f, [30, dur - 40], [0, 1], ease);
  const lon = interpolate(t, [0, 1], [-90, 150]);
  return (
    <Space>
      <Globe center={[lon, 50]} r={520}>
        {(proj, path) => {
          const pt = (p) => proj(p);
          const [dx, dy] = pt(DC) || [], [tx, ty] = pt(TOKYO) || [];
          return (
            <g>
              <path d={arcPath(path, DC, TOKYO, t)} fill="none" stroke="#ffd400" strokeWidth={6} strokeLinecap="round" />
              {dx != null && <circle cx={dx} cy={dy} r={12} fill="#ff4d4d" stroke="#fff" strokeWidth={3} />}
              {tx != null && t > 0.98 && <circle cx={tx} cy={ty} r={12} fill="#ff4d4d" stroke="#fff" strokeWidth={3} />}
              {dx != null && <text x={dx > 640 ? dx - 20 : dx + 20} textAnchor={dx > 640 ? "end" : "start"} y={dy - 18} fill="#fff" fontSize={40} fontWeight={800} fontFamily={FONT} style={{ paintOrder: "stroke", stroke: "#000", strokeWidth: 6 }}>Washington</text>}
              {tx != null && t > 0.98 && <text x={tx - 20} y={ty - 22} textAnchor="end" fill="#fff" fontSize={40} fontWeight={800} fontFamily={FONT} style={{ paintOrder: "stroke", stroke: "#000", strokeWidth: 6 }}>Tokio</text>}
            </g>
          );
        }}
      </Globe>
    </Space>
  );
};

VISUALS.refuel = ({ f, dur }) => {
  const ext = interpolate(f, [20, 60], [0, 160], ease);
  const flow = f > 60;
  const bob = Math.sin(f / 18) * 6;
  // boom tip lands on the receptacle behind the cockpit
  return (
    <Sky f={f} top="#3a78c9" bottom="#cfe6f8">
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <g transform={`translate(-560 ${700 + bob}) scale(0.85)`}><Tanker boom={ext} boomAngle={80} /></g>
        <g transform={`translate(40 ${1000 + bob * 0.8}) scale(1.0)`}><PlaneSide id="rf" /></g>
        {flow && Array.from({ length: 6 }, (_, i) => {
          const p = ((f * 0.04 + i / 6) % 1);
          const x = interpolate(p, [0, 1], [192, 240]), y = interpolate(p, [0, 1], [819 + bob, 1078 + bob * 0.8]);
          return <circle key={i} cx={x} cy={y} r={9} fill="#ffd400" />;
        })}
      </svg>
    </Sky>
  );
};

VISUALS.fuel = ({ f, dur }) => {
  const level = interpolate(f, [20, dur - 30], [0, 1], ease);
  return (
    <Blueprint>
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <defs><clipPath id="lvl"><rect x={300 - 300 * level} y={0} width={600 * level} height={700} /></clipPath></defs>
        <g transform="translate(540 940) scale(1.55) translate(-300 -350)">
          <PlaneTop id="fu" />
          <g clipPath="url(#lvl)">{TOP_TANKS.map((d, i) => <path key={i} d={d} fill="#f5b51b" opacity={0.92} />)}</g>
          {TOP_TANKS.map((d, i) => <path key={"o" + i} d={d} fill="none" stroke="#f5b51b" strokeWidth={2} strokeDasharray="6 5" />)}
        </g>
      </svg>
    </Blueprint>
  );
};

VISUALS.emp = ({ f, dur }) => {
  const C = [-98, 39];
  const blast = interpolate(f, [25, 35], [0, 1], clamp);
  const ring = interpolate(f, [30, dur - 10], [0, 38], { ...clamp, easing: Easing.out(Easing.quad) });
  return (
    <Space>
      <Globe center={[-98, 30]} r={560} night>
        {(proj, path) => {
          const [x, y] = proj(C);
          return (
            <g>
              {[0, 0.5, 1].map((o, i) => <path key={i} d={ringPath(path, C, Math.max(0, ring - o * 8))} fill={i === 0 ? "rgba(255,170,60,.12)" : "none"} stroke="#ffb347" strokeWidth={6 - i * 2} opacity={1 - i * 0.3} />)}
              <circle cx={x} cy={y} r={20 + 120 * blast * (1 - interpolate(f, [35, 70], [0, 1], clamp))} fill="#fff6d0" opacity={blast * (1 - interpolate(f, [40, 90], [0, 0.8], clamp))} />
            </g>
          );
        }}
      </Globe>
      <AbsoluteFill style={{ background: "#fff", opacity: interpolate(f, [25, 28, 40], [0, 0.8, 0], clamp) }} />
    </Space>
  );
};

VISUALS.hawaii = ({ f }) => {
  const J = [-169.53, 16.73], H = [-157.86, 21.31];
  const t = interpolate(f, [30, 90], [0, 1], ease);
  return (
    <Space>
      <Globe center={[-163, 20]} r={1500} cy={1000} night>
        {(proj, path) => {
          const [jx, jy] = proj(J), [hx, hy] = proj(H);
          const lbl = { fill: "#fff", fontSize: 40, fontWeight: 800, fontFamily: FONT, style: { paintOrder: "stroke", stroke: "#000", strokeWidth: 6 } };
          return (
            <g>
              <path d={ringPath(path, J, interpolate(f, [10, 120], [0, 14], clamp))} fill="rgba(255,170,60,.10)" stroke="#ffb347" strokeWidth={5} />
              <circle cx={jx} cy={jy} r={16} fill="#fff6d0" />
              <path d={arcPath(path, J, H, t)} fill="none" stroke="#fff" strokeWidth={5} strokeDasharray="14 10" />
              <circle cx={hx} cy={hy} r={13} fill={t > 0.98 ? "#ffd400" : "#555"} stroke="#fff" strokeWidth={3} />
              <text x={jx} y={jy + 70} textAnchor="middle" {...lbl}>Isla Johnston</text>
              <text x={hx} y={hy - 34} textAnchor="middle" {...lbl}>Honolulu</text>
            </g>
          );
        }}
      </Globe>
    </Space>
  );
};

VISUALS.cable = ({ f }) => {
  const WIRES = ["#e63946", "#f4a261", "#2a9d8f", "#457b9d", "#e9c46a", "#8338ec"];
  return (
    <AbsoluteFill style={{ background: "radial-gradient(ellipse at 50% 45%, #1a2b4f, #050a18 75%)" }}>
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <defs>
          <pattern id="braid" width={24} height={24} patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <rect width={24} height={24} fill="#9aa5b1" /><rect width={12} height={24} fill="#c8d0d8" />
          </pattern>
        </defs>
        <g transform="rotate(-20 540 960)">
          {WIRES.map((c, i) => <rect key={i} x={640} y={890 + i * 24} width={360} height={18} rx={9} fill={c} />)}
          {WIRES.map((_, i) => <rect key={"cu" + i} x={980} y={892 + i * 24} width={40} height={14} rx={7} fill="#d08a3a" />)}
          <rect x={-100} y={870} width={760} height={180} rx={90} fill="url(#braid)" stroke="#5f6b77" strokeWidth={3} />
          <rect x={620} y={870} width={40} height={180} rx={12} fill="#c0c8d0" stroke="#5f6b77" strokeWidth={3} />
        </g>
        {/* bolts that hit the shield and slide off */}
        {Array.from({ length: 5 }, (_, i) => {
          const p = ((f + i * 22) % 110) / 110;
          const x = 140 + i * 110, y = interpolate(p, [0, 0.5], [500, 880], clamp);
          const slide = interpolate(p, [0.5, 1], [0, 1], clamp);
          return (
            <g key={i} opacity={1 - slide}>
              <path d={`M${x - slide * 300},${y - 120} l20,40 l-26,10 l30,60`} fill="none" stroke="#ffe066" strokeWidth={7} strokeLinejoin="round" />
            </g>
          );
        })}
      </svg>
    </AbsoluteFill>
  );
};

VISUALS.inside = ({ f, fps, s }) => {
  const ROOMS = [["Suite presidencial", "#2f6fd6"], ["Despacho", "#3d8bfd"], ["Sala de reuniones", "#5aa9ff"], ["Quirófano", "#e5484d"], ["Personal", "#6c7a89"], ["Invitados y prensa", "#7c8a99"], ["Cocinas", "#9aa6b2"]];
  const third = s.caps[s.caps.length - 1].start;
  return (
    <Blueprint>
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <path d="M540,330 C640,360 690,440 690,560 L690,1300 C690,1360 640,1390 540,1395 C440,1390 390,1360 390,1300 L390,560 C390,440 440,360 540,330 Z" fill="#0d3672" stroke="#fff" strokeWidth={5} />
        {ROOMS.map(([name, c], i) => {
          const k = spring({ frame: f - 30 - i * 14, fps, config: { damping: 14 } });
          const y = 470 + i * 118;
          const hot = name === "Quirófano" && f > third;
          return (
            <g key={name} opacity={k}>
              <rect x={410} y={y} width={260} height={104} rx={10} fill={c} stroke={hot ? "#ffd400" : "#fff"} strokeWidth={hot ? 8 : 2} />
              <text x={hot ? 380 : 380} y={y + 64} textAnchor="end" fontSize={36} fontWeight={800} fill={hot ? "#ffd400" : "#fff"} fontFamily={FONT}>{name}</text>
            </g>
          );
        })}
      </svg>
    </Blueprint>
  );
};

VISUALS.outro = ({ f, dur, fps }) => {
  const title = spring({ frame: f - dur + 70, fps, config: { damping: 12 } });
  return (
    <Sky f={f} top="#1d4f9a" bottom="#f6c7a0">
      <svg width={1080} height={1920} style={{ position: "absolute", inset: 0 }}>
        <path d="M-50,1500 L180,1180 L300,1300 L520,980 L700,1240 L860,1100 L1130,1500 Z" fill="#6f8aa6" />
        <path d="M520,980 L590,1080 L560,1070 L530,1100 L490,1060 L460,1070 Z M180,1180 L220,1235 L190,1230 L160,1210 Z" fill="#fff" />
        <path d="M-50,1920 L-50,1420 L250,1330 L560,1450 L860,1350 L1130,1430 L1130,1920 Z" fill="#3e5a76" />
        <g transform={`translate(${interpolate(f, [0, dur], [1100, -300])} ${760 - f * 0.4}) rotate(-6) scale(0.9)`}><PlaneSide id="out" /></g>
      </svg>
      <div style={{ position: "absolute", top: 330, left: 0, right: 0, textAlign: "center", fontSize: 100, fontWeight: 900, color: "#fff", textShadow: shadow, transform: `scale(${title})` }}>AIR FORCE ONE</div>
    </Sky>
  );
};
