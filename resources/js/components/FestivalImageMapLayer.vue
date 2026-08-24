<template>
	<div class="festival-image-layer">
		<div ref="mapElement" class="festival-image-layer__map"></div>

		<div class="festival-image-layer__crosshair">
			<span></span>
		</div>
	</div>
</template>

<script setup>
import {nextTick, onBeforeUnmount, onMounted, ref, watch} from "vue";

import L from "leaflet";
import "leaflet/dist/leaflet.css";

const props = defineProps({
	festival: {
		type: Object,
		required: true,
	},

	currentGeo: {
		type: Object,
		default: null,
	},

	apiBase: {
		type: String,
		default: "/api/festival-mapper",
	},
});

const emit = defineEmits(["position-changed"]);

const mapElement = ref(null);
const map = ref(null);
const imageOverlay = ref(null);

const isSyncing = ref(false);

function getImageUrl() {
	if (props.festival?.map_image_url) {
		return props.festival.map_image_url;
	}

	if (!props.festival?.map_image_path) {
		return null;
	}

	return props.festival.map_image_path.startsWith("http")
		? props.festival.map_image_path
		: `/storage/${props.festival.map_image_path}`;
}

async function apiFetch(path, options = {}) {
	const response = await fetch(`${props.apiBase}${path}`, {
		headers: {
			Accept: "application/json",
			"Content-Type": "application/json",
		},
		...options,
	});

	if (!response.ok) {
		const payload = await response.json().catch(() => null);

		throw new Error(
			payload?.message || `API error ${response.status}: ${path}`,
		);
	}

	return response.status === 204 ? null : response.json();
}

async function initialiseMap() {
	await nextTick();

	if (
		!mapElement.value ||
		map.value ||
		!props.festival?.map_width ||
		!props.festival?.map_height
	) {
		return;
	}

	const imageUrl = getImageUrl();

	if (!imageUrl) {
		return;
	}

	const width = Number(props.festival.map_width);

	const height = Number(props.festival.map_height);

	/*
	 * Festival image pixels:
	 *
	 * top-left     = (0, 0)
	 * bottom-right = (width, height)
	 *
	 * Leaflet CRS.Simple uses:
	 *
	 * x = lng
	 * y = lat
	 *
	 * and its Y axis runs upwards,
	 * so image Y becomes negative latitude.
	 */
	const bounds = L.latLngBounds([-height, 0], [0, width]);

	map.value = L.map(mapElement.value, {
		crs: L.CRS.Simple,
		minZoom: -4,
		maxZoom: 4,
		zoomSnap: 0.25,
		zoomDelta: 0.25,
		attributionControl: false,
	});

	imageOverlay.value = L.imageOverlay(imageUrl, bounds).addTo(map.value);

	map.value.fitBounds(bounds);
	console.log("[FestivalImageMapLayer] initialised", {
		currentGeo: props.currentGeo,
		centre: map.value.getCenter(),
		zoom: map.value.getZoom(),
		imageWidth: width,
		imageHeight: height,
	});
	map.value.on("moveend", handleMapMoved);

	/*
	 * If another layer has already
	 * established currentGeo, centre
	 * this layer on that same place.
	 */
	if (props.currentGeo) {
		await moveToGeo(props.currentGeo);
	}
}

async function handleMapMoved() {
	if (!map.value) {
		return;
	}

	/*
	 * If we're moving because another
	 * layer changed currentGeo, don't
	 * immediately emit the same change
	 * back again.
	 */
	if (isSyncing.value) {
		isSyncing.value = false;
		return;
	}

	const centre = map.value.getCenter();

	const pixelX = centre.lng;
	const pixelY = -centre.lat;

	const width = Number(props.festival.map_width);

	const height = Number(props.festival.map_height);

	/*
	 * Don't resolve coordinates when
	 * the centre crosshair is outside
	 * the actual festival image.
	 */
	if (pixelX < 0 || pixelY < 0 || pixelX > width || pixelY > height) {
		return;
	}
	console.log("[FestivalImageMapLayer] user moved map", {
		leafletCentre: centre,
		pixelX,
		pixelY,
		zoom: map.value.getZoom(),
	});
	try {
		const result = await apiFetch(
			`/festivals/${props.festival.id}/coordinates/to-geo`,
			{
				method: "POST",
				body: JSON.stringify({
					x: pixelX,
					y: pixelY,
				}),
			},
		);

		emit("position-changed", result.geo);
	} catch (error) {
		console.error("Failed to resolve festival map position:", error);
	}
}

async function moveToGeo(geo) {
	if (!map.value || !geo) {
		return;
	}

	try {
		const result = await apiFetch(
			`/festivals/${props.festival.id}/coordinates/to-pixel`,
			{
				method: "POST",
				body: JSON.stringify({
					latitude: geo.latitude,
					longitude: geo.longitude,
				}),
			},
		);

		console.log("[FestivalImageMapLayer] syncing to currentGeo", {
			latitude: Number(geo.latitude),
			longitude: Number(geo.longitude),
			pixelX: Number(result.pixel.x),
			pixelY: Number(result.pixel.y),
			zoom: map.value.getZoom(),
		});

		isSyncing.value = true;

		map.value.panTo([-result.pixel.y, result.pixel.x]);
	} catch (error) {
		console.error(
			"Failed to move festival map to geographic position:",
			error,
		);
	}
}

watch(
	() => props.currentGeo,
	async (geo) => {
		if (!geo || !map.value) {
			return;
		}

		await moveToGeo(geo);
	},
	{
		deep: true,
	},
);

onMounted(initialiseMap);

onBeforeUnmount(() => {
	if (map.value) {
		map.value.remove();
		map.value = null;
	}

	imageOverlay.value = null;
});
</script>

<style scoped>
.festival-image-layer {
	position: relative;
	width: 100%;
	height: 650px;
	overflow: hidden;
	border-radius: 18px;
	background: rgba(0, 0, 0, 0.25);
}

.festival-image-layer__map {
	width: 100%;
	height: 100%;
}

.festival-image-layer__crosshair {
	position: absolute;
	z-index: 1000;
	top: 50%;
	left: 50%;
	width: 28px;
	height: 28px;
	transform: translate(-50%, -50%);
	pointer-events: none;
}

.festival-image-layer__crosshair::before,
.festival-image-layer__crosshair::after {
	content: "";
	position: absolute;
	background: white;
	box-shadow: 0 0 3px black;
}

.festival-image-layer__crosshair::before {
	top: 50%;
	left: 0;
	width: 100%;
	height: 2px;
	transform: translateY(-50%);
}

.festival-image-layer__crosshair::after {
	top: 0;
	left: 50%;
	width: 2px;
	height: 100%;
	transform: translateX(-50%);
}

.festival-image-layer__crosshair span {
	position: absolute;
	top: 50%;
	left: 50%;
	width: 8px;
	height: 8px;
	border: 2px solid white;
	border-radius: 50%;
	transform: translate(-50%, -50%);
	box-shadow: 0 0 3px black;
}
</style>
