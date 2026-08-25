<template>
	<div class="festival-map">
		<LayerSwitcher
			:layers="activeLayers"
			:active-layer-id="activeLayerId"
			@switch="onLayerSwitch" />

		<div class="festival-map__canvas-wrapper">
			<FestivalImageMapLayer
				v-if="isFestivalImageLayer && festival"
				:festival="festival"
				:current-geo="currentGeo"
				:api-base="apiBase"
				@position-changed="onPositionChanged" />

			<GeoMapLayer
				v-else-if="isGeoMapLayer"
				:current-geo="currentGeo"
				@position-changed="onPositionChanged" />
		</div>
	</div>
</template>

<script setup>
import {ref, computed, onMounted} from "vue";
import LayerSwitcher from "./LayerSwitcher.vue";
import FestivalImageMapLayer from "./FestivalImageMapLayer.vue";
import GeoMapLayer from "./GeoMapLayer.vue";
import {useMapPosition} from "../composables/useMapPosition";

const props = defineProps({
	festivalId: {
		type: Number,
		required: true,
	},
	apiBase: {
		type: String,
		default: "/api/festival-mapper",
	},
});

const festival = ref(null);
const layers = ref([]);
const activeLayers = computed(() =>
	layers.value.filter((layer) => layer.is_active),
);
const pins = ref([]);
const activeLayerId = ref(null);

const {currentGeo, setCurrentGeo} = useMapPosition();

const isFestivalImageLayer = computed(
	() => activeLayerId.value === "festival-image",
);

const isGeoMapLayer = computed(() => activeLayerId.value === "geo-map");

async function apiFetch(path, options = {}) {
	const response = await fetch(`${props.apiBase}${path}`, {
		headers: {
			"Content-Type": "application/json",
			Accept: "application/json",
		},
		...options,
	});
	if (!response.ok) {
		throw new Error(`API error ${response.status}: ${path}`);
	}
	return response.status === 204 ? null : response.json();
}

async function loadFestival() {
	festival.value = await apiFetch(`/festivals/${props.festivalId}`);

	if (
		!currentGeo.value &&
		festival.value?.map_width &&
		festival.value?.map_height
	) {
		try {
			const result = await apiFetch(
				`/festivals/${props.festivalId}/coordinates/to-geo`,
				{
					method: "POST",
					body: JSON.stringify({
						x: festival.value.map_width / 2,
						y: festival.value.map_height / 2,
					}),
				},
			);

			setCurrentGeo(result.geo.latitude, result.geo.longitude);

			console.log("[FestivalMap] initial position", {
				pixelX: festival.value.map_width / 2,
				pixelY: festival.value.map_height / 2,
				latitude: result.geo.latitude,
				longitude: result.geo.longitude,
			});
		} catch (error) {
			console.error(
				"[FestivalMap] failed to initialise map position",
				error,
			);
		}
	}
}

async function loadLayers() {
	layers.value = await apiFetch(`/festivals/${props.festivalId}/layers`);

	if (!activeLayerId.value && activeLayers.value.length) {
		activeLayerId.value = activeLayers.value[0].id;
	}
}

async function loadPins() {
	pins.value = await apiFetch(`/festivals/${props.festivalId}/pins`);
}

async function onLayerSwitch(layerId) {
	console.log("[FestivalMap] switching layer", {
		from: activeLayerId.value,
		to: layerId,
		currentGeo: currentGeo.value,
	});

	activeLayerId.value = layerId;
}

function onPositionChanged(geo) {
	function onPositionChanged(geo) {
		console.log("[FestivalMap] position changed", {
			activeLayer: activeLayerId.value,
			latitude: Number(geo.latitude),
			longitude: Number(geo.longitude),
		});

		setCurrentGeo(geo.latitude, geo.longitude);
	}

	setCurrentGeo(geo.latitude, geo.longitude);
}

async function onPinDropped({pixelX, pixelY}) {
	const pin = await apiFetch(`/festivals/${props.festivalId}/pins`, {
		method: "POST",
		body: JSON.stringify({pixel_x: pixelX, pixel_y: pixelY}),
	});
	pins.value.push(pin);
}

async function onPinMoved({pin, pixelX, pixelY}) {
	const updated = await apiFetch(
		`/festivals/${props.festivalId}/pins/${pin.id}`,
		{
			method: "PATCH",
			body: JSON.stringify({pixel_x: pixelX, pixel_y: pixelY}),
		},
	);
	const index = pins.value.findIndex((p) => p.id === pin.id);
	if (index !== -1) pins.value[index] = updated;
}

async function onPinDeleted(pin) {
	await apiFetch(`/festivals/${props.festivalId}/pins/${pin.id}`, {
		method: "DELETE",
	});
	pins.value = pins.value.filter((p) => p.id !== pin.id);
}

onMounted(async () => {
	await loadFestival();
	await loadLayers();
	await loadPins();
});
</script>

<style scoped>
.festival-map {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.festival-map__canvas-wrapper {
	position: relative;
	overflow: hidden;
}
</style>
