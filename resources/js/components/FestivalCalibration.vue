<template>
	<div class="festival-calibration">
		<div class="festival-calibration__header">
			<div>
				<h3>Calibrate Festival Map</h3>
				<p>
					Match points on the festival artwork with the same locations
					on the real-world map.
				</p>
			</div>
		</div>

		<div class="festival-calibration__instructions">
			<span>1</span>
			<p>
				Click a location on the festival map, then click the same
				location on the geographic map.
			</p>
		</div>

		<div class="festival-calibration__workspace">
			<div class="festival-calibration__panel">
				<div class="festival-calibration__panel-header">
					<div>
						<strong>Festival Map</strong>
						<small>Click a point on the artwork</small>
					</div>
				</div>

				<div class="festival-calibration__image-container">
					<img
						v-if="festival?.map_image_url"
						:src="festival.map_image_url"
						:alt="`${festival.name} map`"
						draggable="false" />

					<div
						v-for="point in calibrationPoints"
						:key="`festival-${point.id}`"
						class="festival-calibration__marker"
						:style="festivalMarkerStyle(point)">
						{{ point.label || point.id }}
					</div>
				</div>
			</div>

			<div class="festival-calibration__panel">
				<div class="festival-calibration__panel-header">
					<div>
						<strong>Geographic Map</strong>
						<small>Click the matching real-world location</small>
					</div>
				</div>

				<div
					ref="mapElement"
					class="festival-calibration__leaflet-map"></div>
			</div>
		</div>

		<div v-if="pendingPoint" class="festival-calibration__pending">
			<div>
				<strong>Calibration point selected</strong>

				<p>Now click the matching location on the geographic map.</p>
			</div>

			<button
				type="button"
				class="festival-calibration__cancel"
				@click="cancelPendingPoint">
				Cancel
			</button>
		</div>

		<ul
			v-if="calibrationPoints.length"
			class="festival-calibration__points">
			<li v-for="point in calibrationPoints" :key="point.id">
				<div>
					<strong>
						{{ point.label || `Point ${point.id}` }}
					</strong>

					<small>
						Pixel {{ point.pixel_x }}, {{ point.pixel_y }}
						→
						{{ point.latitude }}, {{ point.longitude }}
					</small>
				</div>

				<button type="button" @click="deletePoint(point.id)">
					Delete
				</button>
			</li>
		</ul>

		<p v-else class="festival-calibration__empty">
			No calibration points have been created yet.
		</p>
	</div>
</template>

<script setup>
import {nextTick, onBeforeUnmount, onMounted, ref, watch} from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const props = defineProps({
	festival: {
		type: Object,
		default: null,
	},

	calibrationPoints: {
		type: Array,
		default: () => [],
	},

	apiBase: {
		type: String,
		default: "/api/festival-mapper",
	},
});

const emit = defineEmits(["calibration-created", "calibration-deleted"]);

const mapElement = ref(null);
const map = ref(null);

const pendingPoint = ref(null);

function festivalMarkerStyle(point) {
	if (!props.festival?.map_width || !props.festival?.map_height) {
		return {};
	}

	const left =
		(Number(point.pixel_x) / Number(props.festival.map_width)) * 100;

	const top =
		(Number(point.pixel_y) / Number(props.festival.map_height)) * 100;

	return {
		left: `${left}%`,
		top: `${top}%`,
	};
}

function handleFestivalMapClick(event) {
	if (!props.festival?.map_width || !props.festival?.map_height) {
		return;
	}

	const rect = event.currentTarget.getBoundingClientRect();

	const x =
		((event.clientX - rect.left) / rect.width) *
		Number(props.festival.map_width);

	const y =
		((event.clientY - rect.top) / rect.height) *
		Number(props.festival.map_height);

	pendingPoint.value = {
		pixel_x: x,
		pixel_y: y,
	};
}

async function handleGeographicMapClick(event) {
	if (!pendingPoint.value || !props.festival?.id) {
		return;
	}

	const label = window.prompt("Give this calibration point a name:", "");

	if (label === null) {
		return;
	}

	try {
		const response = await fetch(
			`${props.apiBase}/festivals/${props.festival.id}/calibration`,
			{
				method: "POST",
				headers: {
					Accept: "application/json",
					"Content-Type": "application/json",
				},
				body: JSON.stringify({
					pixel_x: pendingPoint.value.pixel_x,
					pixel_y: pendingPoint.value.pixel_y,
					latitude: event.latlng.lat,
					longitude: event.latlng.lng,
					label: label.trim() || null,
				}),
			},
		);

		if (!response.ok) {
			throw new Error(`API error ${response.status}`);
		}

		const point = await response.json();

		emit("calibration-created", point);

		pendingPoint.value = null;
	} catch (error) {
		console.error(error);
	}
}

function cancelPendingPoint() {
	pendingPoint.value = null;
}

async function deletePoint(pointId) {
	if (!props.festival?.id) {
		return;
	}

	const confirmed = window.confirm("Delete this calibration point?");

	if (!confirmed) {
		return;
	}

	try {
		const response = await fetch(
			`${props.apiBase}/festivals/${props.festival.id}/calibration/${pointId}`,
			{
				method: "DELETE",
				headers: {
					Accept: "application/json",
				},
			},
		);

		if (!response.ok) {
			throw new Error(`API error ${response.status}`);
		}

		emit("calibration-deleted", pointId);
	} catch (error) {
		console.error(error);
	}
}

async function initialiseMap() {
	if (!mapElement.value || map.value) {
		return;
	}

	map.value = L.map(mapElement.value).setView([54.5, -3.5], 6);

	L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
		attribution: "&copy; OpenStreetMap contributors",
		maxZoom: 20,
	}).addTo(map.value);

	map.value.on("click", handleGeographicMapClick);
}

async function refreshMap() {
	await nextTick();

	if (!map.value) {
		await initialiseMap();
	}

	if (map.value) {
		map.value.invalidateSize();
	}
}

onMounted(async () => {
	await refreshMap();
});

watch(
	() => props.festival?.id,
	async () => {
		pendingPoint.value = null;

		await refreshMap();
	},
);

onBeforeUnmount(() => {
	if (map.value) {
		map.value.remove();
		map.value = null;
	}
});
</script>

<style scoped>
.festival-calibration {
	display: flex;
	flex-direction: column;
	gap: 1.25rem;
}

.festival-calibration__header h3 {
	margin: 0;
	font-size: 1.25rem;
}

.festival-calibration__header p {
	margin: 0.4rem 0 0;
	color: var(--festival-admin-text-muted);
}

.festival-calibration__instructions {
	display: flex;
	align-items: center;
	gap: 0.85rem;
	padding: 1rem;
	border-radius: 16px;
	background: var(--festival-admin-accent-soft);
}

.festival-calibration__instructions span {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 2rem;
	height: 2rem;
	border-radius: 50%;
	background: var(--festival-admin-accent);
	color: #1a120f;
	font-weight: 700;
}

.festival-calibration__instructions p {
	margin: 0;
}

.festival-calibration__workspace {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 1rem;
}

.festival-calibration__panel {
	min-width: 0;
	overflow: hidden;
	border: 1px solid var(--festival-admin-border);
	border-radius: 20px;
	background: rgba(255, 255, 255, 0.04);
}

.festival-calibration__panel-header {
	padding: 1rem;
	border-bottom: 1px solid var(--festival-admin-border);
}

.festival-calibration__panel-header strong,
.festival-calibration__panel-header small {
	display: block;
}

.festival-calibration__panel-header small {
	margin-top: 0.25rem;
	color: var(--festival-admin-text-muted);
}

.festival-calibration__image-container {
	position: relative;
	width: 100%;
	cursor: crosshair;
}

.festival-calibration__image-container img {
	display: block;
	width: 100%;
	height: auto;
	user-select: none;
}

.festival-calibration__marker {
	position: absolute;
	width: 28px;
	height: 28px;
	transform: translate(-50%, -50%);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: var(--festival-admin-accent);
	color: #1a120f;
	font-size: 0.75rem;
	font-weight: 700;
	border: 3px solid white;
	pointer-events: none;
}

.festival-calibration__leaflet-map {
	width: 100%;
	min-height: 500px;
}

.festival-calibration__pending {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
	padding: 1rem;
	border-radius: 16px;
	border: 1px solid var(--festival-admin-border);
	background: rgba(255, 255, 255, 0.04);
}

.festival-calibration__pending p {
	margin: 0.25rem 0 0;
	color: var(--festival-admin-text-muted);
}

.festival-calibration__cancel {
	border: 1px solid var(--festival-admin-border);
	border-radius: 12px;
	padding: 0.7rem 1rem;
	background: transparent;
	color: inherit;
	cursor: pointer;
}

.festival-calibration__points {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	margin: 0;
	padding: 0;
	list-style: none;
}

.festival-calibration__points li {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
	padding: 1rem;
	border: 1px solid var(--festival-admin-border);
	border-radius: 16px;
	background: rgba(255, 255, 255, 0.04);
}

.festival-calibration__points strong,
.festival-calibration__points small {
	display: block;
}

.festival-calibration__points small {
	margin-top: 0.25rem;
	color: var(--festival-admin-text-muted);
}

.festival-calibration__points button {
	border: 0;
	background: transparent;
	color: #ffb1a0;
	cursor: pointer;
}

.festival-calibration__empty {
	margin: 0;
	padding: 1rem;
	border-radius: 16px;
	background: rgba(255, 255, 255, 0.04);
	color: var(--festival-admin-text-muted);
}

@media (max-width: 900px) {
	.festival-calibration__workspace {
		grid-template-columns: 1fr;
	}
}
</style>
