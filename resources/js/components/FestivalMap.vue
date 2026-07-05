<template>
	<div class="festival-map">
		<LayerSwitcher
			:layers="layers"
			:active-layer-id="activeLayerId"
			@switch="onLayerSwitch" />

		<div class="festival-map__canvas-wrapper">
			<MapCanvas
				:festival="festival"
				:pins="pins"
				:active-layer="activeLayer"
				@pin-dropped="onPinDropped"
				@pin-moved="onPinMoved"
				@pin-deleted="onPinDeleted" />
		</div>
	</div>
</template>

<script setup>
import {ref, computed, onMounted} from "vue";
import MapCanvas from "./MapCanvas.vue";
import LayerSwitcher from "./LayerSwitcher.vue";

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
const pins = ref([]);
const activeLayerId = ref(null);

const activeLayer = computed(
	() => layers.value.find((l) => l.id === activeLayerId.value) ?? null,
);

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
}

async function loadLayers() {
	layers.value = await apiFetch(`/festivals/${props.festivalId}/layers`);
	if (!activeLayerId.value && layers.value.length) {
		activeLayerId.value =
			layers.value.find((l) => l.is_active)?.id ?? layers.value[0].id;
	}
}

async function loadPins() {
	pins.value = await apiFetch(`/festivals/${props.festivalId}/pins`);
}

async function onLayerSwitch(layerId) {
	activeLayerId.value = layerId;
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
	await Promise.all([loadFestival(), loadLayers(), loadPins()]);
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
