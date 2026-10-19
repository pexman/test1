import { Composition } from "remotion";
import { EuropePopulation, TOTAL_FRAMES, FPS } from "./EuropePopulation";
import { Top10, TOTAL_FRAMES as TOP10_FRAMES, FPS as TOP10_FPS } from "./Top10";
import { AirForceOne, TOTAL_FRAMES as AFO_FRAMES, FPS as AFO_FPS } from "./afo/AirForceOne";

export const Root = () => (
  <>
    <Composition id="AirForceOne" component={AirForceOne} durationInFrames={AFO_FRAMES} fps={AFO_FPS} width={1080} height={1920} />
    <Composition id="Top10Europe" component={Top10} durationInFrames={TOP10_FRAMES} fps={TOP10_FPS} width={1080} height={1920} />
    <Composition id="EuropePopulation" component={EuropePopulation} durationInFrames={TOTAL_FRAMES} fps={FPS} width={1080} height={1920} />
  </>
);
