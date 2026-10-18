import { Composition } from "remotion";
import { EuropePopulation, TOTAL_FRAMES, FPS } from "./EuropePopulation";

export const Root = () => (
  <Composition
    id="EuropePopulation"
    component={EuropePopulation}
    durationInFrames={TOTAL_FRAMES}
    fps={FPS}
    width={1080}
    height={1920}
  />
);
