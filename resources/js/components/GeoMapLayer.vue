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
import {geographicScale, geographicZoom, validScale} from '../utils/mapScale';
import {drawPins} from "../utils/mapPins";
import {terrainOverlay, terrainContours} from '../utils/terrainOverlay';
import {artworkOverlay} from "../utils/artworkOverlay";
import "leaflet/dist/leaflet.css";

const props = defineProps({
 active: {type:Boolean,default:true},
 viewScale:{type:Number,default:null},
 resetView:{type:Number,default:0},
 pins: {type:Array,default:()=>[]},
 selectable:{type:Boolean,default:false},
 selectionGeo:{type:Object,default:null},
 topography:{type:Boolean,default:false},
 terrain:{type:Object,default:null},
 terrainOpacity:{type:Number,default:.65},
 tileUrl:{type:String,default:'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png'},
 tileMaxZoom:{type:Number,default:17},
 apiBase:{type:String,default:'/api/festival-mapper'},
 what3words:{type:Boolean,default:false},
 festival:{type:Object,default:null},
 corners:{type:Array,default:()=>[]},
 artworkOpacity:{type:Number,default:0},
 streetOpacity:{type:Number,default:1},
	currentGeo: {
		type: Object,
		default: null,
	},
});

const emit = defineEmits(["position-changed", "pin-selected", "location-picked", "grid-status", "scale-changed"]);

const mapElement = ref(null);
const map = shallowRef(null);
let contourLines; let terrainImage; let tiles; let image; let pinGroup; let selectionMarker; let grid; let gridTimer; let gridRequest=0;

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
        zoomSnap: .01,
        zoomDelta: .5,
	}).setView(initialPosition, props.currentGeo ? (props.what3words ? 18 : 16) : 6);

	tiles = L.tileLayer(props.topography ? props.tileUrl : "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxNativeZoom:props.topography ? props.tileMaxZoom : 19,
        maxZoom:22,
        opacity:props.streetOpacity,
	}).addTo(map.value);

	map.value.on("moveend", handleMapMoved);
    map.value.on('click', event => { if(props.selectable && props.active) emit('location-picked',{latitude:event.latlng.lat,longitude:event.latlng.lng}); });
    syncScale();
    renderArtwork(); renderTerrain(); renderPins(); renderSelection(); scheduleGrid();
    handleMapMoved();
}

function handleMapMoved() {
    scheduleGrid();
	if (!map.value || !props.active || isSyncing.value) return;


	const centre = map.value.getCenter();
    emit("scale-changed", geographicScale(centre.lat, map.value.getZoom()));
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
    syncScale();
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

function syncScale() {
    if (!props.active || !map.value || !validScale(props.viewScale)) return;
    const zoom = Math.min(map.value.getMaxZoom(), Math.max(map.value.getMinZoom(), geographicZoom(map.value.getCenter().lat, props.viewScale)));
    if (Math.abs(map.value.getZoom() - zoom) < .011) return;
    isSyncing.value = true;
    map.value.setZoom(zoom, {animate:false});
    isSyncing.value = false;
}
watch(() => props.viewScale, syncScale);
watch(() => props.resetView, () => {
    if (!props.active || !map.value || props.corners.length !== 3) return;
    const [a,b,c] = props.corners;
    const d = {latitude:b.latitude+c.latitude-a.latitude,longitude:b.longitude+c.longitude-a.longitude};
    map.value.fitBounds([a,b,c,d].map(point=>[point.latitude,point.longitude]), {padding:[12,12],animate:false});
    handleMapMoved();
});

function renderArtwork() {
    if (!map.value) return;
    image?.remove(); image=null;
    if(props.festival && props.corners.length===3) {
        image=artworkOverlay(map.value,props.festival,props.corners);
        image.setOpacity(props.artworkOpacity);
    }
    renderTerrain();
    // Pins are rendered last so the artwork never covers them.
    renderPins();
}
function renderTerrain() {
    contourLines?.remove(); contourLines=null;
    terrainImage?.remove(); terrainImage=null;
    if(map.value && props.terrain){terrainImage=terrainOverlay(map.value,props.terrain,props.terrainOpacity);contourLines=terrainContours(map.value,props.terrain);}
}
watch(()=>props.terrain,renderTerrain);
watch(()=>props.terrainOpacity,value=>terrainImage?.setOpacity(value));
function renderPins() {
    if(!map.value) return;
    pinGroup?.remove();
    const color=getComputedStyle(mapElement.value).getPropertyValue('--system-color-primary').trim() || '#6366f1';
    pinGroup=drawPins(map.value,props.pins,pin=>[Number(pin.latitude),Number(pin.longitude)],pin=>emit('pin-selected',pin),color);
}
function renderSelection(){
 selectionMarker?.remove(); selectionMarker=null;
 if(map.value && props.selectionGeo && props.active && props.what3words && map.value.getZoom()<18)map.value.setView([props.selectionGeo.latitude,props.selectionGeo.longitude],18,{animate:false});
 if(map.value && props.selectionGeo) selectionMarker=L.circleMarker([props.selectionGeo.latitude,props.selectionGeo.longitude],{radius:12,color:'#fff',weight:3,fillColor:getComputedStyle(mapElement.value).getPropertyValue('--system-color-primary').trim() || '#6366f1',fillOpacity:1,className:'mapper-selection-marker'}).addTo(map.value).bindTooltip('Selected location',{permanent:true,direction:'top'});
}
function scheduleGrid(){clearTimeout(gridTimer);gridRequest++;grid?.remove();grid=null;if(props.active && props.what3words){if(map.value?.getZoom()>=18)gridTimer=setTimeout(renderGrid,400);else emit('grid-status','Zoom in to show the what3words three-metre grid.');}}
async function renderGrid(){
 if(!map.value || !props.active || !props.what3words)return;
 const request=gridRequest,bounds=map.value.getBounds();
 if(map.value.distance(bounds.getSouthWest(),bounds.getNorthEast())>3900)return;
 const query=new URLSearchParams({south:bounds.getSouth(),west:bounds.getWest(),north:bounds.getNorth(),east:bounds.getEast()});
 try{const response=await fetch(`${props.apiBase}/location-info/grid?${query}`);const payload=await response.json();if(!response.ok){if(request===gridRequest)emit('grid-status',payload.message || 'The what3words grid is unavailable.');return;}if(request!==gridRequest || !map.value)return;
  emit('grid-status',payload.lines.length ? '' : 'No grid is available for this area.');
  grid=L.layerGroup(payload.lines.map(line=>L.polyline([[line.start.lat,line.start.lng],[line.end.lat,line.end.lng]],{color:'#555',weight:1,opacity:.5,interactive:false}))).addTo(map.value);
 }catch{if(request===gridRequest)emit('grid-status','The what3words grid could not be loaded.');}
}
watch(()=>props.selectionGeo,renderSelection,{deep:true});
watch(()=>props.what3words,scheduleGrid);
watch(() => props.pins,renderPins,{deep:true});
watch(() => props.corners,renderArtwork,{deep:true});
watch(() => props.artworkOpacity,opacity=>image?.setOpacity(opacity));
watch(() => props.streetOpacity,opacity=>tiles?.setOpacity(opacity));
watch(() => props.active,async active=>{
    if(!active) {scheduleGrid();return;}
    await initialiseMap(); await nextTick(); map.value?.invalidateSize({pan:false});
    if(props.currentGeo) moveToGeo(props.currentGeo);
    syncScale();
    scheduleGrid();
});
onMounted(initialiseMap);

onBeforeUnmount(() => {
 clearTimeout(gridTimer);gridRequest++;
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

