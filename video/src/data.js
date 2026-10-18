// map key → [name, code, population (millions, approx. 2024)]
export const COUNTRIES = {
  "Albania": ["Albania", "AL", 2.8], "Germany": ["Germany", "DE", 84.4], "Andorra": ["Andorra", "AD", 0.08], "Austria": ["Austria", "AT", 9.1],
  "Belgium": ["Belgium", "BE", 11.8], "Belarus": ["Belarus", "BY", 9.2], "Bosnia and Herz.": ["Bosnia and Herzegovina", "BA", 3.2],
  "Bulgaria": ["Bulgaria", "BG", 6.4], "Cyprus": ["Cyprus", "CY", 1.3], "Croatia": ["Croatia", "HR", 3.9], "Denmark": ["Denmark", "DK", 5.9],
  "Slovakia": ["Slovakia", "SK", 5.4], "Slovenia": ["Slovenia", "SI", 2.1], "Spain": ["Spain", "ES", 48.6], "Estonia": ["Estonia", "EE", 1.4],
  "Finland": ["Finland", "FI", 5.6], "France": ["France", "FR", 68.4], "Greece": ["Greece", "GR", 10.4], "Hungary": ["Hungary", "HU", 9.6],
  "Ireland": ["Ireland", "IE", 5.3], "Iceland": ["Iceland", "IS", 0.39], "Italy": ["Italy", "IT", 58.9], "Kosovo": ["Kosovo", "XK", 1.6],
  "Latvia": ["Latvia", "LV", 1.9], "Liechtenstein": ["Liechtenstein", "LI", 0.04], "Lithuania": ["Lithuania", "LT", 2.9], "Luxembourg": ["Luxembourg", "LU", 0.67],
  "Macedonia": ["North Macedonia", "MK", 1.8], "Malta": ["Malta", "MT", 0.55], "Moldova": ["Moldova", "MD", 2.4], "Monaco": ["Monaco", "MC", 0.04],
  "Montenegro": ["Montenegro", "ME", 0.62], "Norway": ["Norway", "NO", 5.5], "Netherlands": ["Netherlands", "NL", 17.9], "Poland": ["Poland", "PL", 36.7],
  "Portugal": ["Portugal", "PT", 10.6], "United Kingdom": ["United Kingdom", "GB", 68.3], "Czechia": ["Czechia", "CZ", 10.9], "Romania": ["Romania", "RO", 19.0],
  "Russia": ["Russia", "RU", 146.0], "San Marino": ["San Marino", "SM", 0.03], "Serbia": ["Serbia", "RS", 6.6], "Sweden": ["Sweden", "SE", 10.6],
  "Switzerland": ["Switzerland", "CH", 8.9], "Turkey": ["Türkiye", "TR", 85.3], "Ukraine": ["Ukraine", "UA", 37.0], "Vatican": ["Vatican City", "VA", 0.0008],
};

export const ALIAS = { "Åland": "Finland" };

// population classes (millions) → fill colour
export const CLASSES = [[1, "#5eead4"], [5, "#38bdf8"], [10, "#818cf8"], [40, "#c084fc"], [Infinity, "#f472b6"]];
export const classColor = (m) => CLASSES.find(([t]) => m < t)[1];

const nf = (d) => new Intl.NumberFormat("en-GB", { maximumFractionDigits: d, minimumFractionDigits: d });
export const short = (m) => (m >= 1 ? nf(1).format(m) + "M" : m >= 0.001 ? nf(0).format(m * 1000) + "K" : nf(0).format(m * 1e6));
export const full = (m) => nf(0).format(Math.round(m * 1e6));
export const whole = (n) => nf(0).format(n);
