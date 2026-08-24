<template>
	<div class="geo-map-layer">
		<div ref="mapElement" class="geo-map-layer__map"></div>

		<div class="geo-map-layer__crosshair">
			<span></span>
		</div>
	</div>
</template>

<script setup>
import {nextTick, onBeforeUnmount, onMounted, ref, watch} from "vue";

import L from "leaflet";
import "leaflet/dist/leaflet.css";

const props = defineProps({
	currentGeo: {
		type: Object,
		default: null,
	},
});

const emit = defineEmits(["position-changed"]);

const mapElement = ref(null);
const map = ref(null);

const isSyncing = ref(false);

async function initialiseMap() {
	await nextTick();

	if (!mapElement.value || map.value) {
		return;
	}

	const initialPosition = props.currentGeo
		? [props.currentGeo.latitude, props.currentGeo.longitude]
		: [54.5, -1.5];

	map.value = L.map(mapElement.value, {
		zoomControl: true,
	}).setView(initialPosition, props.currentGeo ? 16 : 6);

	console.log("[GeoMapLayer] initialised", {
		currentGeo: props.currentGeo,
		centre: map.value.getCenter(),
		zoom: map.value.getZoom(),
	});

	L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		maxZoom: 19,
	}).addTo(map.value);

	map.value.on("moveend", handleMapMoved);
}

function handleMapMoved() {
	if (!map.value) {
		return;
	}

	if (isSyncing.value) {
		isSyncing.value = false;
		return;
	}

	const centre = map.value.getCenter();
	console.log("[GeoMapLayer] user moved map", {
		centre,
		zoom: map.value.getZoom(),
	});
	emit("position-changed", {
		latitude: centre.lat,
		longitude: centre.lng,
	});
}

function moveToGeo(geo) {
	if (!map.value || !geo) {
		return;
	}

	const centre = map.value.getCenter();

	const alreadyCentred =
		Math.abs(centre.lat - Number(geo.latitude)) < 0.0000001 &&
		Math.abs(centre.lng - Number(geo.longitude)) < 0.0000001;

	if (alreadyCentred) {
		return;
	}

	isSyncing.value = true;
	console.log("[GeoMapLayer] user moved map", {
		centre,
		zoom: map.value.getZoom(),
	});
	map.value.panTo([Number(geo.latitude), Number(geo.longitude)]);
}

watch(
	() => props.currentGeo,
	(geo) => {
		if (!geo || !map.value) {
			return;
		}

		moveToGeo(geo);
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
});
</script>

<style scoped>
.geo-map-layer {
	position: relative;
	width: 100%;
	height: 650px;
	overflow: hidden;
	border-radius: 18px;
	background: rgba(0, 0, 0, 0.25);
}

.geo-map-layer__map {
	width: 100%;
	height: 100%;
}

.geo-map-layer__crosshair {
	position: absolute;
	z-index: 1000;
	top: 50%;
	left: 50%;
	width: 28px;
	height: 28px;
	transform: translate(-50%, -50%);
	pointer-events: none;
}

.geo-map-layer__crosshair::before,
.geo-map-layer__crosshair::after {
	content: "";
	position: absolute;
	background: white;
	box-shadow: 0 0 3px black;
}

.geo-map-layer__crosshair::before {
	top: 50%;
	left: 0;
	width: 100%;
	height: 2px;
	transform: translateY(-50%);
}

.geo-map-layer__crosshair::after {
	top: 0;
	left: 50%;
	width: 2px;
	height: 100%;
	transform: translateX(-50%);
}

.geo-map-layer__crosshair span {
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
