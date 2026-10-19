// Stylised VC-25A ("Air Force One") and a tanker, drawn as flat SVG.
const LIGHT_BLUE = "#7fb6e8", DARK_BLUE = "#1f4f9c", NAVY = "#14306b", GOLD = "#d4a72c";

const SIDE_BODY = "M6,160 C4,140 20,112 60,98 C90,88 120,80 160,78 L300,78 C340,78 370,104 410,110 L860,110 L925,18 L975,14 L968,112 L995,128 L998,140 C960,160 900,186 820,188 L140,188 C70,188 10,182 6,160 Z";

// side view, nose to the left, in a 1000×240 box
export const PlaneSide = ({ id = "afo", outline = false, stroke = "#fff" }) => {
  if (outline) {
    return (
      <g fill="none" stroke={stroke} strokeWidth={3} strokeLinejoin="round">
        <path d={SIDE_BODY} />
        <path d="M430,160 L470,158 L640,212 L612,214 Z" />
        <rect x={452} y={176} width={64} height={24} rx={11} />
        <rect x={540} y={198} width={64} height={24} rx={11} />
        <path d="M880,130 L905,128 L985,150 L970,152 Z" />
        <path d="M48,100 L80,92 L84,100 L52,108 Z" />
      </g>
    );
  }
  return (
    <g>
      <defs><clipPath id={`${id}-body`}><path d={SIDE_BODY} /></clipPath></defs>
      <path d={SIDE_BODY} fill="#ffffff" />
      <g clipPath={`url(#${id}-body)`}>
        <path d="M0,0 L340,0 C300,95 200,124 0,132 Z" fill={LIGHT_BLUE} />
        <rect x={0} y={157} width={1000} height={40} fill={DARK_BLUE} />
        <rect x={0} y={153} width={1000} height={4} fill={GOLD} />
        {Array.from({ length: 13 }, (_, i) => <circle key={"u" + i} cx={120 + i * 14} cy={95} r={2.6} fill={NAVY} />)}
        {Array.from({ length: 56 }, (_, i) => <circle key={"m" + i} cx={120 + i * 13} cy={140} r={2.6} fill={NAVY} />)}
        <rect x={925} y={24} width={40} height={22} fill="#b22234" />
        {[0, 1, 2].map((i) => <rect key={i} x={925} y={27 + i * 7} width={40} height={3} fill="#fff" />)}
        <rect x={925} y={24} width={16} height={11} fill="#3c3b6e" />
      </g>
      <text x={450} y={128} fontSize={17} fontWeight={800} fill={NAVY} letterSpacing="2" fontFamily="Montserrat, sans-serif">UNITED STATES OF AMERICA</text>
      <path d="M48,100 L80,92 L84,100 L52,108 Z" fill="#1a1a2e" />
      <path d="M430,160 L470,158 L640,212 L612,214 Z" fill="#cfd8e0" stroke="#8a99a8" strokeWidth={1.5} />
      <rect x={452} y={176} width={64} height={24} rx={11} fill="#e8edf2" stroke="#8a99a8" strokeWidth={1.5} />
      <rect x={540} y={198} width={64} height={24} rx={11} fill="#e8edf2" stroke="#8a99a8" strokeWidth={1.5} />
      <path d="M880,130 L905,128 L985,150 L970,152 Z" fill="#e8edf2" stroke="#8a99a8" strokeWidth={1.5} />
    </g>
  );
};

// top view, nose up, in a 600×700 box
export const TOP_WINGS = ["M272,250 L20,470 L30,490 L272,370 Z", "M328,250 L580,470 L570,490 L328,370 Z"];
export const TOP_TANKS = ["M272,268 L70,446 L78,458 L272,352 Z", "M328,268 L530,446 L522,458 L328,352 Z", "M276,262 L324,262 L324,362 L276,362 Z"];
const TOP_BODY = "M300,0 C322,10 330,40 330,80 L330,600 C330,640 315,690 300,700 C285,690 270,640 270,600 L270,80 C270,40 278,10 300,0 Z";
export const PlaneTop = ({ id = "top", wingFill = "#d5dde5" }) => (
  <g>
    {TOP_WINGS.map((d, i) => <path key={i} d={d} fill={wingFill} stroke="#8a99a8" strokeWidth={2} />)}
    {[[190, 340], [100, 420], [410, 340], [500, 420]].map(([x, y], i) => <rect key={i} x={x - 11} y={y - 34} width={22} height={60} rx={9} fill="#eef2f6" stroke="#8a99a8" strokeWidth={2} />)}
    <path d="M275,590 L170,670 L178,685 L278,650 Z M325,590 L430,670 L422,685 L322,650 Z" fill={wingFill} stroke="#8a99a8" strokeWidth={2} />
    <defs><clipPath id={`${id}-b`}><path d={TOP_BODY} /></clipPath></defs>
    <path d={TOP_BODY} fill="#fff" stroke="#8a99a8" strokeWidth={2} />
    <g clipPath={`url(#${id}-b)`}><rect x={260} y={0} width={80} height={150} fill={LIGHT_BLUE} /><rect x={296} y={150} width={8} height={450} fill={LIGHT_BLUE} /></g>
    <path d="M288,40 L312,40 L308,52 L292,52 Z" fill="#1a1a2e" />
  </g>
);

// tanker side view, nose to the left, in a 1000×220 box; boom pivots at (885,140)
export const Tanker = ({ boom = 0, boomAngle = 62 }) => (
  <g>
    <path d="M0,130 C0,105 40,92 100,90 L860,90 L920,10 L965,8 L955,95 L1000,110 L1000,120 C950,140 900,150 840,150 L100,150 C40,150 0,145 0,130 Z" fill="#9aa6b2" stroke="#5f6b77" strokeWidth={2} />
    {Array.from({ length: 50 }, (_, i) => <circle key={i} cx={110 + i * 14} cy={112} r={2.4} fill="#3b4652" />)}
    <path d="M38,104 L64,98 L68,106 L42,112 Z" fill="#1a1a2e" />
    <path d="M400,135 L440,133 L600,185 L575,187 Z" fill="#87939f" stroke="#5f6b77" strokeWidth={1.5} />
    <rect x={430} y={150} width={56} height={22} rx={10} fill="#a9b4bf" stroke="#5f6b77" strokeWidth={1.5} />
    <path d="M870,118 L895,116 L975,135 L960,138 Z" fill="#87939f" stroke="#5f6b77" strokeWidth={1.5} />
    <g transform={`translate(885 140) rotate(${boomAngle})`}>
      <rect x={0} y={-5} width={150 + boom} height={10} rx={5} fill="#b9c3cc" stroke="#5f6b77" strokeWidth={1.5} />
      <path d="M40,-5 L60,-30 L75,-30 L70,-5 Z M40,5 L60,30 L75,30 L70,5 Z" fill="#87939f" />
    </g>
  </g>
);
