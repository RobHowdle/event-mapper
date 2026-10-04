<template>
	<div class="geo-map-layer">
		<div ref="mapElement" class="geo-map-layer__map"></div>

		<div class="geo-map-layer__crosshair">
			<span></span>
		</div>
	</div>
</template>

<script setup>
import {nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch} from "vue";

import L from "leaflet";
import {drawPins} from "../utils/mapPins";
import {artworkOverlay} from "../utils/artworkOverlay";
import "leaflet/dist/leaflet.css";

const props = defineProps({
 active: {type:Boolean,default:true},
 pins: {type:Array,default:()=>[]},
 selectable:{type:Boolean,default:false},
 festival:{type:Object,default:null},
 corners:{type:Array,default:()=>[]},
 artworkOpacity:{type:Number,default:0},
 streetOpacity:{type:Number,default:1},
	currentGeo: {
		type: Object,
		default: null,
	},
});

const emit = defineEmits(["position-changed", "pin-selected", "location-picked"]);

const mapElement = ref(null);
const map = shallowRef(null);
let tiles; let image; let pinGroup;

const isSyncing = ref(false);

async function initialiseMap() {
	await nextTick();

	if (!props.active || !mapElement.value || map.value) {
		return;
	}

	const initialPosition = props.currentGeo
		? [props.currentGeo.latitude, props.currentGeo.longitude]
		: [54.5, -1.5];

	map.value = L.map(mapElement.value, {
		zoomControl: true,
	}).setView(initialPosition, props.currentGeo ? 16 : 6);

	tiles = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		maxZoom: 19,
        opacity:props.streetOpacity,
	}).addTo(map.value);

	map.value.on("moveend", handleMapMoved);
    map.value.on('click', event => { if(props.selectable && props.active) emit('location-picked',{latitude:event.latlng.lat,longitude:event.latlng.lng}); });
    renderArtwork(); renderPins();
}

function handleMapMoved() {
	if (!map.value || !props.active || isSyncing.value) return;


	const centre = map.value.getCenter();
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
	map.value.panTo([Number(geo.latitude), Number(geo.longitude)], {animate:false});
    isSyncing.value = false;
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

function renderArtwork() {
    if (!map.value) return;
    image?.remove(); image=null;
    if(props.festival && props.corners.length===3) {
        image=artworkOverlay(map.value,props.festival,props.corners);
        image.setOpacity(props.artworkOpacity);
    }
    // Pins are rendered last so the artwork never covers them.
    renderPins();
}
function renderPins() {
    if(!map.value) return;
    pinGroup?.remove();
    const color=getComputedStyle(mapElement.value).getPropertyValue('--system-color-primary').trim() || '#6366f1';
    pinGroup=drawPins(map.value,props.pins,pin=>[Number(pin.latitude),Number(pin.longitude)],pin=>emit('pin-selected',pin),color);
}
watch(() => props.pins,renderPins,{deep:true});
watch(() => props.corners,renderArtwork,{deep:true});
watch(() => props.artworkOpacity,opacity=>image?.setOpacity(opacity));
watch(() => props.streetOpacity,opacity=>tiles?.setOpacity(opacity));
watch(() => props.active,async active=>{
    if(!active) return;
    await initialiseMap(); await nextTick(); map.value?.invalidateSize({pan:false});
    if(props.currentGeo) moveToGeo(props.currentGeo);
});
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
	height: var(--mapper-map-height, 650px);
	overflow: hidden;
	border-radius: 12px;
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

