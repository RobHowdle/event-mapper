<template>
	<div class="pin-layer">
		<div
			v-for="pin in pinsWithPixels"
			:key="pin.id"
			class="pin-layer__pin"
			:style="{left: `${pin.pixelX}px`, top: `${pin.pixelY}px`}"
			:title="pin.label || `Pin #${pin.id}`"
			@mousedown.stop="startDrag($event, pin)"
			@dblclick.stop="emit('delete', pin)">
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="28"
				height="28"
				fill="#e53e3e">
				<path
					d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z" />
			</svg>
			<span v-if="pin.label" class="pin-layer__label">{{
				pin.label
			}}</span>
		</div>
	</div>
</template>

<script setup>
import {ref, computed, onUnmounted} from "vue";

const props = defineProps({
	pins: {type: Array, default: () => []},
	festival: {type: Object, default: null},
	apiBase: {type: String, default: "/api/festival-mapper"},
});

const emit = defineEmits(["move", "delete"]);

/**
 * Pins are stored as internal coordinates [0,1].
 * Convert them back to pixel positions for display.
 */
const pinsWithPixels = computed(() => {
	if (!props.festival) return [];

	return props.pins.map((pin) => ({
		...pin,
		pixelX: pin.internal_x * props.festival.map_width,
		pixelY: pin.internal_y * props.festival.map_height,
	}));
});

// ─── Drag handling ─────────────────────────────────────────────────────────────

const dragging = ref(null);

function startDrag(event, pin) {
	dragging.value = {pin, startX: event.clientX, startY: event.clientY};
	window.addEventListener("mousemove", onMouseMove);
	window.addEventListener("mouseup", onMouseUp);
}

function onMouseMove(event) {
	if (!dragging.value) return;
	// Real-time visual feedback would go here if needed.
}

function onMouseUp(event) {
	if (!dragging.value || !props.festival) return;

	const {pin} = dragging.value;

	// Get the canvas bounding rect from the parent (pin-layer sits inside map-canvas).
	const canvas = document.querySelector(".map-canvas");
	if (!canvas) return;

	const rect = canvas.getBoundingClientRect();
	const pixelX = Math.max(
		0,
		Math.min(event.clientX - rect.left, props.festival.map_width),
	);
	const pixelY = Math.max(
		0,
		Math.min(event.clientY - rect.top, props.festival.map_height),
	);

	emit("move", pin, pixelX, pixelY);

	dragging.value = null;
	window.removeEventListener("mousemove", onMouseMove);
	window.removeEventListener("mouseup", onMouseUp);
}

onUnmounted(() => {
	window.removeEventListener("mousemove", onMouseMove);
	window.removeEventListener("mouseup", onMouseUp);
});
</script>

<style scoped>
.pin-layer {
	position: absolute;
	inset: 0;
	pointer-events: none;
}

.pin-layer__pin {
	position: absolute;
	transform: translate(-50%, -100%);
	cursor: grab;
	pointer-events: all;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.pin-layer__pin:active {
	cursor: grabbing;
}

.pin-layer__label {
	margin-top: 2px;
	background: rgba(0, 0, 0, 0.65);
	color: #fff;
	font-size: 0.7rem;
	padding: 1px 5px;
	border-radius: 3px;
	white-space: nowrap;
}
</style>
