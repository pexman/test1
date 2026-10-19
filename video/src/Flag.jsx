// Simplified national flags, drawn to cover a box (x, y, w, h).
const stripes = (colors, x, y, w, h, vertical, weights = colors.map(() => 1)) => {
  const sum = weights.reduce((a, b) => a + b, 0);
  let acc = 0;
  return colors.map((c, i) => {
    const from = acc / sum, size = weights[i] / sum;
    acc += weights[i];
    return vertical
      ? <rect key={i} x={x + from * w} y={y} width={size * w + 0.5} height={h} fill={c} />
      : <rect key={i} x={x} y={y + from * h} width={w} height={size * h + 0.5} fill={c} />;
  });
};

const star = (cx, cy, r, rot) =>
  Array.from({ length: 10 }, (_, i) => {
    const a = rot + (i * Math.PI) / 5, rr = i % 2 ? r * 0.4 : r;
    return `${cx + rr * Math.cos(a)},${cy + rr * Math.sin(a)}`;
  }).join(" ");

const UnionJack = ({ id }) => (
  <>
    <clipPath id={`${id}-t`}><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z" /></clipPath>
    <rect width={60} height={30} fill="#012169" />
    <path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" strokeWidth={6} />
    <path d="M0,0 L60,30 M60,0 L0,30" clipPath={`url(#${id}-t)`} stroke="#C8102E" strokeWidth={4} />
    <path d="M30,0 v30 M0,15 h60" stroke="#fff" strokeWidth={10} />
    <path d="M30,0 v30 M0,15 h60" stroke="#C8102E" strokeWidth={6} />
  </>
);

export const Flag = ({ country, x, y, w, h, id }) => {
  switch (country) {
    case "Russia": return stripes(["#ffffff", "#0039a6", "#d52b1e"], x, y, w, h, false);
    case "Germany": return stripes(["#000000", "#dd0000", "#ffce00"], x, y, w, h, false);
    case "France": return stripes(["#0055a4", "#ffffff", "#ef4135"], x, y, w, h, true);
    case "Italy": return stripes(["#009246", "#ffffff", "#ce2b37"], x, y, w, h, true);
    case "Spain": return stripes(["#aa151b", "#f1bf00", "#aa151b"], x, y, w, h, false, [1, 2, 1]);
    case "Ukraine": return stripes(["#0057b7", "#ffd700"], x, y, w, h, false);
    case "Poland": return stripes(["#ffffff", "#dc143c"], x, y, w, h, false);
    case "Romania": return stripes(["#002b7f", "#fcd116", "#ce1126"], x, y, w, h, true);
    case "United Kingdom":
      return (
        <svg x={x} y={y} width={w} height={h} viewBox="0 0 60 30" preserveAspectRatio="xMidYMid slice">
          <UnionJack id={id} />
        </svg>
      );
    case "Turkey":
      return (
        <svg x={x} y={y} width={w} height={h} viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
          <rect width={1200} height={800} fill="#e30a17" />
          <circle cx={425} cy={400} r={200} fill="#fff" />
          <circle cx={475} cy={400} r={160} fill="#e30a17" />
          <polygon points={star(583, 400, 100, Math.PI)} fill="#fff" />
        </svg>
      );
    default: return <rect x={x} y={y} width={w} height={h} fill="#888" />;
  }
};
