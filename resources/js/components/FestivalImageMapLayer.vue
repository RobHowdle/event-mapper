<template>
	<div class="festival-image-layer">
		<div ref="mapElement" class="festival-image-layer__map"></div>

		<div class="festival-image-layer__crosshair">
			<span></span>
		</div>
	</div>
</template>

<script setup>
import {nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch} from "vue";

import L from "leaflet";
import {pixelToGeo, geoToPixel} from '../utils/imageCalibration';
import {drawPins} from "../utils/mapPins";
import "leaflet/dist/leaflet.css";

const props = defineProps({
 active: {type: Boolean, default: true},
 pins: {type: Array, default: () => []},
 selectable: {type: Boolean, default: false},
 selectionGeo:{type:Object,default:null},
 calibration:{type:Object,default:null},
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

const emit = defineEmits(["position-changed", "pin-selected", "location-picked"]);

const mapElement = ref(null);
const map = shallowRef(null);
const imageOverlay = shallowRef(null);
let pinGroup; let pinRequest = 0; let positionRequest = 0; let syncRequest = 0; let selectionMarker; let selectionRequest=0;

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

async function resolveGeo(x,y) {
 if(props.calibration)return {geo:pixelToGeo(props.calibration,x,y)};
 return apiFetch(`/festivals/${props.festival.id}/coordinates/to-geo`,{method:'POST',body:JSON.stringify({x,y})});
}
async function resolvePixel(location) {
 if(props.calibration)return {pixel:geoToPixel(props.calibration,location)};
 return apiFetch(`/festivals/${props.festival.id}/coordinates/to-pixel`,{method:'POST',body:JSON.stringify({latitude:location.latitude,longitude:location.longitude})});
}
async function initialiseMap() {
	await nextTick();

	if (!props.active) return;
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
	map.value.on("moveend", handleMapMoved);
    map.value.on("click", pickLocation);
    await renderPins(); await renderSelection();

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
	if (!map.value || !props.active || isSyncing.value) return;

	/*
	 * If we're moving because another
	 * layer changed currentGeo, don't
	 * immediately emit the same change
	 * back again.
	 */

	const request = ++positionRequest;
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
        emit("position-changed", null);
		return;
	}
	try {
        const result = await resolveGeo(pixelX,pixelY);

		if (request === positionRequest && props.active && map.value) emit("position-changed", result.geo);
	} catch (error) {
		console.error("Failed to resolve festival map position:", error);
	}
}

async function moveToGeo(geo) {
    const request = ++syncRequest;
	if (!map.value || !geo) {
		return;
	}

	try {
        const result = await resolvePixel(geo);

		if (!map.value || request !== syncRequest) return;
        isSyncing.value = true;
        map.value.panTo([-result.pixel.y, result.pixel.x], {animate: false});
        isSyncing.value = false;
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

async function renderPins() {
    if (!map.value) return;
    const request = ++pinRequest;
    const located = await Promise.all(props.pins.map(async pin => {
        try {
            const result = await resolvePixel(pin);
            return {...pin, position:[-Number(result.pixel.y),Number(result.pixel.x)]};
        } catch { return {...pin,position:null}; }
    }));
    if (request !== pinRequest || !map.value) return;
    pinGroup?.remove();
    const color = getComputedStyle(mapElement.value).getPropertyValue('--system-color-primary').trim() || '#6366f1';
    pinGroup = drawPins(map.value, located, pin => pin.position, pin => emit('pin-selected',pin), color);
}
async function pickLocation(event) {
    if (!props.selectable || !props.active) return;
    try {
        const result = await resolveGeo(event.latlng.lng,-event.latlng.lat);
        emit('location-picked', result.geo);
    } catch (error) { console.error('Could not select location', error); }
}
async function renderSelection() {
 const request=++selectionRequest;
 selectionMarker?.remove(); selectionMarker=null;
 if(!props.selectionGeo || !map.value) return;
 try {
  const result=await resolvePixel(props.selectionGeo);
  if(request!==selectionRequest || !map.value) return;
  selectionMarker=L.circleMarker([-Number(result.pixel.y),Number(result.pixel.x)],{radius:12,color:'#fff',weight:3,fillColor:getComputedStyle(mapElement.value).getPropertyValue('--system-color-primary').trim() || '#6366f1',fillOpacity:1,className:'mapper-selection-marker'}).addTo(map.value).bindTooltip('Selected location',{permanent:true,direction:'top'});
 }catch { /* Selection can still be used on the geographic map without calibration. */ }
}
watch(()=>props.calibration,()=>{renderPins();renderSelection();if(props.currentGeo)moveToGeo(props.currentGeo);});
watch(() => props.selectionGeo,renderSelection,{deep:true});
watch(() => props.pins, renderPins, {deep:true});
watch(() => props.active, async active => {
    if (!active) {positionRequest++; return;}
    await initialiseMap(); await nextTick();
    map.value?.invalidateSize({pan:false});
    if (props.currentGeo) await moveToGeo(props.currentGeo);
});
onMounted(initialiseMap);

onBeforeUnmount(() => {
	pinRequest++; positionRequest++; syncRequest++; selectionRequest++;
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
	height: var(--mapper-map-height, 650px);
	overflow: hidden;
	border-radius: 12px;
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

