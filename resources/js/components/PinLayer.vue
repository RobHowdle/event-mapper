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

			<span v-if="pin.label" class="pin-layer__label">
				{{ pin.label }}
			</span>
		</div>
	</div>
</template>

<script setup>
import {ref, computed, watch, onUnmounted} from "vue";

const props = defineProps({
	pins: {
		type: Array,
		default: () => [],
	},
	festival: {
		type: Object,
		default: null,
	},
	apiBase: {
		type: String,
		default: "/api/festival-mapper",
	},
});

const emit = defineEmits(["move", "delete"]);

/**
 * Pixel positions resolved from the pin's geographic coordinates.
 *
 * The database stores latitude/longitude.
 * The map needs pixel x/y for rendering.
 *
 * Keys are pin IDs.
 */
const pixelPositions = ref({});

/**
 * Prevent an older batch of coordinate resolutions from overwriting
 * a newer batch if the pins change while requests are in flight.
 */
let resolveGeneration = 0;

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

/**
 * Resolve every pin's geographic coordinate into a pixel position.
 */
async function resolvePinPositions() {
	if (!props.festival || !props.pins.length) {
		pixelPositions.value = {};
		return;
	}

	const generation = ++resolveGeneration;

	const results = await Promise.all(
		props.pins.map(async (pin) => {
			if (
				typeof pin.latitude !== "number" ||
				typeof pin.longitude !== "number"
			) {
				return null;
			}

			try {
				const result = await apiFetch(
					`/festivals/${props.festival.id}/coordinates/to-pixel`,
					{
						method: "POST",
						body: JSON.stringify({
							latitude: pin.latitude,
							longitude: pin.longitude,
						}),
					},
				);

				return {
					id: pin.id,
					pixel: result.pixel,
				};
			} catch (error) {
				console.error(
					`Failed to resolve pixel position for pin ${pin.id}`,
					error,
				);

				return null;
			}
		}),
	);

	if (generation !== resolveGeneration) {
		return;
	}

	const positions = {};

	for (const result of results) {
		if (!result?.pixel) continue;

		positions[result.id] = {
			x: result.pixel.x,
			y: result.pixel.y,
		};
	}

	pixelPositions.value = positions;
}

/**
 * Pins are stored as geographic coordinates.
 *
 * Their pixel positions are resolved through CoordinateController.
 */
const pinsWithPixels = computed(() => {
	return props.pins
		.map((pin) => {
			const position = pixelPositions.value[pin.id];

			if (!position) {
				return null;
			}

			return {
				...pin,
				pixelX: position.x,
				pixelY: position.y,
			};
		})
		.filter(Boolean);
});

watch(
	() => [
		props.festival?.id,
		...props.pins.map(
			(pin) => `${pin.id}:${pin.latitude}:${pin.longitude}`,
		),
	],
	() => {
		resolvePinPositions();
	},
	{immediate: true},
);

// ─── Drag handling ─────────────────────────────────────────────────────────────

const dragging = ref(null);

function startDrag(event, pin) {
	dragging.value = {
		pin,
		startX: event.clientX,
		startY: event.clientY,
	};

	window.addEventListener("mousemove", onMouseMove);
	window.addEventListener("mouseup", onMouseUp);
}

function onMouseMove(event) {
	if (!dragging.value) return;

	// Real-time visual feedback can be added here later.
}

function onMouseUp(event) {
	if (!dragging.value || !props.festival) return;

	const {pin} = dragging.value;

	const canvas = document.querySelector(".map-canvas");

	if (!canvas) {
		cleanupDragging();
		return;
	}

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

	cleanupDragging();
}

function cleanupDragging() {
	dragging.value = null;

	window.removeEventListener("mousemove", onMouseMove);
	window.removeEventListener("mouseup", onMouseUp);
}

onUnmounted(() => {
	resolveGeneration++;

	cleanupDragging();
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
