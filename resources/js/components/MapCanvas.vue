<template>
	<div
		ref="containerRef"
		class="map-canvas"
		:style="containerStyle"
		@click="onCanvasClick">
		<!-- Map image -->
		<img
			v-if="festival?.map_image_path"
			:src="imageUrl"
			:width="festival.map_width"
			:height="festival.map_height"
			class="map-canvas__image"
			draggable="false"
			alt="Festival map" />

		<!-- Pins -->
		<PinLayer
			:pins="pins"
			:festival="festival"
			:api-base="apiBase"
			@move="
				(pin, px, py) =>
					emit('pin-moved', {pin, pixelX: px, pixelY: py})
			"
			@delete="(pin) => emit('pin-deleted', pin)" />
	</div>
</template>

<script setup>
import {ref, computed} from "vue";
import PinLayer from "./PinLayer.vue";

const props = defineProps({
	festival: {type: Object, default: null},
	pins: {type: Array, default: () => []},
	activeLayer: {type: Object, default: null},
	apiBase: {type: String, default: "/api/festival-mapper"},
});

const emit = defineEmits(["pin-dropped", "pin-moved", "pin-deleted"]);

const containerRef = ref(null);

const imageUrl = computed(() => {
	if (!props.festival?.map_image_path) return null;
	// When the path is already a full URL leave it alone; otherwise prefix /storage/.
	return props.festival.map_image_path.startsWith("http")
		? props.festival.map_image_path
		: `/storage/${props.festival.map_image_path}`;
});

const containerStyle = computed(() => ({
	width: props.festival ? `${props.festival.map_width}px` : "100%",
	height: props.festival ? `${props.festival.map_height}px` : "auto",
	position: "relative",
}));

function onCanvasClick(event) {
	if (!props.festival) return;

	const rect = containerRef.value.getBoundingClientRect();
	const pixelX = event.clientX - rect.left;
	const pixelY = event.clientY - rect.top;

	emit("pin-dropped", {pixelX, pixelY});
}
</script>

<style scoped>
.map-canvas {
	user-select: none;
	cursor: crosshair;
}

.map-canvas__image {
	display: block;
	max-width: 100%;
	pointer-events: none;
}
</style>
