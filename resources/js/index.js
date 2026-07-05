import FestivalMap from "./components/FestivalMap.vue";
import MapCanvas from "./components/MapCanvas.vue";
import LayerSwitcher from "./components/LayerSwitcher.vue";
import PinLayer from "./components/PinLayer.vue";

export {FestivalMap, MapCanvas, LayerSwitcher, PinLayer};

/**
 * Vue plugin — registers all components globally.
 *
 * Usage in app.js:
 *   import FestivalMapperPlugin from './vendor/festival-mapper'
 *   app.use(FestivalMapperPlugin)
 */
export default {
	install(app) {
		app.component("FestivalMap", FestivalMap);
		app.component("MapCanvas", MapCanvas);
		app.component("LayerSwitcher", LayerSwitcher);
		app.component("PinLayer", PinLayer);
	},
};
