// Generates the Spanish voice-over for the AirForceOne composition with ElevenLabs (via fal.ai).
// Usage: FAL_KEY=... node scripts/voice.mjs   → public/voice/*.mp3 and src/afo/voice.json
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { execFileSync } from "node:child_process";

const KEY = process.env.FAL_KEY;
if (!KEY) throw new Error("Set FAL_KEY");
const VOICE = process.env.VOICE || "Aria";
const script = JSON.parse(readFileSync("src/afo/script.json", "utf8"));
mkdirSync("public/voice", { recursive: true });
const durations = {};

for (const scene of script) {
  for (const [i, text] of scene.text.entries()) {
    const file = `public/voice/${scene.id}-${i}.mp3`;
    const res = await fetch("https://fal.run/elevenlabs/tts/eleven-v4/stream", {
      method: "POST",
      headers: { Authorization: `Key ${KEY}`, "Content-Type": "application/json" },
      body: JSON.stringify({ text, voice: VOICE, stability: 0.5, similarity_boost: 0.75, output_format: "mp3_44100_128", apply_text_normalization: "auto" }),
    });
    if (!res.ok) throw new Error(`${scene.id}/${i}: ${res.status} ${await res.text()}`);
    writeFileSync(file, Buffer.from(await res.arrayBuffer()));
    const secs = parseFloat(execFileSync("ffprobe", ["-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", file]).toString());
    durations[`${scene.id}/${i}`] = +secs.toFixed(3);
    console.log(`${scene.id}/${i}  ${secs.toFixed(2)}s  ${text}`);
  }
}
writeFileSync("src/afo/voice.json", JSON.stringify(durations, null, 1) + "\n");
