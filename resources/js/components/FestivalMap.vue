<template>
    <section class="festival-map mapper-ui liquid-glass" :aria-busy="loading">
        <header class="festival-map__header">
            <div><h2>{{ festival?.name || 'Festival' }} map</h2><p>Explore the site and find saved locations.</p></div>
            <div class="festival-map__buttons">
                <LayerSwitcher :layers="activeLayers" :active-layer-id="activeLayerId" @switch="switchLayer" />
            </div>
        </header>
        <p v-if="loading" class="festival-map__message" role="status">Loading map…</p>
        <div v-else-if="error" class="festival-map__message" role="alert">{{ error }} <button type="button" class="mapper-button" @click="load">Try again</button></div>
        <p v-else-if="!activeLayers.length" class="festival-map__message">No map layers are enabled for this festival.</p>
        <template v-else>
            <div class="festival-map__canvas-wrapper">
                <!-- v-show retains Leaflet instances; the shared ground scale keeps layers aligned. -->
                <FestivalImageMapLayer v-if="hasImage && festival" v-show="activeLayerId === 'festival-image'"
                    :key="`image-${festivalId}`" :active="activeLayerId === 'festival-image'" :festival="festival" :view-scale="viewScale" :reset-view="resetView" @scale-changed="viewScale=$event" :current-geo="currentGeo" :api-base="apiBase" :calibration="calibration" :pins="filteredPins" :selectable="true" :selection-geo="selectionGeo"
                    @position-changed="onPositionChanged" @pin-selected="selectPin" @location-picked="pickLocation" />
                <GeoMapLayer v-if="hasGeo" v-show="activeLayerId === 'geo-map'" :key="`geo-${festivalId}`"
                    :api-base="apiBase" :what3words="settings.what3words_enabled" @grid-status="gridMessage=$event" :active="activeLayerId === 'geo-map'" :festival="festival" :view-scale="viewScale" :reset-view="resetView" @scale-changed="viewScale=$event" :current-geo="currentGeo" :pins="filteredPins" :selectable="true" :selection-geo="selectionGeo" :corners="corners"
                    :artwork-opacity="comparing ? artworkOpacity / 100 : 0" :street-opacity="comparing ? streetOpacity / 100 : 1"
                    @position-changed="onPositionChanged" @pin-selected="selectPin" @location-picked="pickLocation" />
                <GeoMapLayer v-if="hasTopography" v-show="activeLayerId === 'topography'" :key="`topography-${festivalId}`" :active="activeLayerId === 'topography'" topography :terrain="terrain" :terrain-opacity="terrainOpacity / 100" :festival="festival" :corners="corners" :artwork-opacity="terrainArtwork ? 1 : 0" :tile-url="settings.topography_tiles" :tile-max-zoom="settings.topography_max_zoom" :view-scale="viewScale" :reset-view="resetView" @scale-changed="viewScale=$event" :current-geo="currentGeo" :pins="filteredPins" selectable :selection-geo="selectionGeo" @position-changed="onPositionChanged" @pin-selected="selectPin" @location-picked="pickLocation" />
            </div>
            <div class="festival-map__quick-actions"><button v-if="corners.length === 3" class="mapper-button" type="button" @click="resetView++">Show whole site</button></div>
            <details v-if="canCompare || activeLayerId === 'topography'" class="festival-map__panel" :open="!isMobile">
                <summary>Layer options</summary>
                <button v-if="canCompare" class="mapper-button" type="button" :aria-pressed="comparing" @click="toggleCompare">Compare layers</button>
            <div v-if="comparing" class="festival-map__opacity">
                <label>Festival artwork <span>{{ artworkOpacity }}%</span><input v-model.number="artworkOpacity" type="range" min="0" max="100" aria-label="Festival artwork opacity" /></label>
                <label>Street map <span>{{ streetOpacity }}%</span><input v-model.number="streetOpacity" type="range" min="0" max="100" aria-label="Street map opacity" /></label>
                <p v-if="overlayError" role="status">{{ overlayError }}</p>
            </div>
            <div v-if="activeLayerId === 'topography'" class="festival-map__terrain-controls">
                <p v-if="terrainLoading" role="status">Loading elevation contours…</p>
                <p v-else-if="terrainError" role="status">{{ terrainError }} <button class="mapper-button" type="button" @click="loadTerrain">Try again</button></p>
                <template v-else-if="terrain">
                    <div class="festival-map__legend" role="img" :aria-label="`Elevation from ${terrain.minimum} to ${terrain.maximum} metres above sea level`">
                        <strong>Estimated elevation · metres above sea level</strong>
                        <div v-if="terrainOpacity > 0" class="festival-map__gradient" :style="{background:elevationGradient}"></div>
                        <div class="festival-map__legend-labels"><span>{{ terrain.minimum }} m</span><span>{{ ((terrain.minimum + terrain.maximum) / 2).toFixed(1) }} m</span><span>{{ terrain.maximum }} m</span></div>
                    </div>
                    <label class="festival-map__terrain-opacity">Colour shading (optional) <span>{{ terrainOpacity }}%</span><input v-model.number="terrainOpacity" type="range" min="0" max="100" aria-label="Elevation opacity" /></label>
                    <label v-if="festival?.map_image_url" class="festival-map__artwork-toggle"><input v-model="terrainArtwork" type="checkbox" /> Show festival artwork underneath</label>
                    <p class="festival-map__hint">Contour labels show estimated height. Samples are approximately {{ terrain.sample_spacing_metres }} m apart; small slopes and paths may not be represented.</p>
                </template>
            </div>
            </details>
            <p v-if="activeLayerId === 'geo-map'" class="festival-map__hint" role="status">{{ settings.what3words_enabled ? gridMessage : settings.what3words_message }}</p>
            <p class="festival-map__hint">{{ selectable ? 'Click a map to place your location pin. Drag the map to move around.' : (settings.what3words_enabled ? 'Click a point to see its what3words address and elevation.' : 'Select a pin or click the map to explore a location.') }}</p>
            <details v-if="shareGeo" class="festival-map__point festival-map__panel" aria-label="Selected point" role="region" :open="!isMobile || selectable || !!selectionGeo">
                <summary>{{ selectionGeo ? 'Selected location' : 'Share map centre' }}</summary>
                <p v-if="selectable">{{ coordinatesText(shareGeo) }}</p>
                <div class="festival-map__buttons festival-map__sharing">
                    <button class="mapper-button" type="button" @click="copyLocation('coordinates')">Copy coordinates</button>
                    <button class="mapper-button" type="button" @click="copyLocation('link')">Copy location link</button>
                    <button class="mapper-button" type="button" @click="shareLocation">Share location</button>
                    <a class="mapper-button" :href="mapLinks.open" target="_blank" rel="noopener noreferrer">Open in Maps</a>
                    <button v-if="selectionGeo && validLocation(currentGeo)" class="mapper-button" type="button" @click="pickLocation(currentGeo)">Use map centre</button>
                </div>
                <details class="festival-map__map-options"><summary>Other maps</summary><div class="festival-map__buttons"><a class="mapper-button" :href="mapLinks.apple" target="_blank" rel="noopener noreferrer">Apple Maps</a><a class="mapper-button" :href="mapLinks.google" target="_blank" rel="noopener noreferrer">Google Maps</a></div></details>
                <p v-if="locationNotice" role="status">{{ locationNotice }}</p>
                <label v-if="manualCopy">Copy this text<input class="mapper-input" readonly :value="manualCopy" aria-label="Location text to copy" @focus="$event.target.select()" /></label>
                <p class="festival-map__hint">This link opens the location directly in a maps service.</p>
                <p v-if="selectionGeo && pointLoading" role="status">Looking up elevation…</p>
                <template v-else-if="selectionGeo">
                    <p v-if="pointInfo?.what3words"><a :href="pointInfo.what3words.url" target="_blank" rel="noopener noreferrer">///{{ pointInfo.what3words.words }}</a></p>
                    <p v-else-if="pointInfo?.errors?.what3words">{{ pointInfo.errors.what3words }}</p><button v-if="pointInfo?.errors?.what3words" class="mapper-button" type="button" @click="lookupPoint(selectionGeo, true)">Retry location lookup</button>
                    <p v-if="pointInfo?.elevation">Elevation: {{ pointInfo.elevation.metres }} m above sea level <small>(terrain estimate)</small></p>
                    <p v-else>{{ pointInfo?.errors?.elevation }}</p>
                    <div class="festival-map__buttons" v-if="pointInfo?.what3words"><button class="mapper-button" type="button" @click="copyAddress">{{ copyStatus || 'Copy what3words' }}</button><button class="mapper-button" type="button" @click="shareAddress">Share what3words</button></div>
                </template>
            </details>
            <details v-if="showLocations" class="festival-map__locations festival-map__panel" :open="!isMobile || selectable || !!selectedPin">
                <summary>Find a location</summary>
                <div class="festival-map__search">
                    <label>Find a location<input v-model="search" class="mapper-input" type="search" placeholder="Search locations" /></label>
                    <label>Category<select v-model="category" class="mapper-input"><option value="">All locations</option><option v-for="item in categories" :key="item" :value="item">{{ categoryLabel(item) }}</option></select></label>
                </div>
                <div v-if="selectedPin" class="festival-map__detail" role="region" aria-label="Location details">
                    <div><h3>{{ selectedPin.label || 'Unnamed location' }}</h3><p>{{ categoryLabel(selectedPin.metadata?.category || 'other') }}</p></div>
                    <button class="mapper-button" type="button" aria-label="Close location details" @click="selectedPin=null">×</button>
                    <p v-if="selectedPin.metadata?.description" class="festival-map__description">{{ selectedPin.metadata.description }}</p>
                    <div class="festival-map__buttons">
                        <a class="mapper-button" :href="directionsUrl(selectedPin)" target="_blank" rel="noopener noreferrer">Get directions</a>
                        <a v-if="pointInfo?.what3words" class="mapper-button" :href="pointInfo.what3words.url" target="_blank" rel="noopener noreferrer">Open what3words</a>
                        <a v-if="safeLink(selectedPin.metadata?.url)" class="mapper-button" :href="safeLink(selectedPin.metadata.url)" target="_blank" rel="noopener noreferrer">More information</a>
                    </div>
                </div>
                <div class="festival-map__pin-list"><button v-for="pin in filteredPins" :key="pin.id" class="festival-map__pin" type="button" :aria-pressed="selectedPin?.id===pin.id" @click="selectPin(pin)"><strong>{{ pin.label || 'Unnamed location' }}</strong><span>{{ categoryLabel(pin.metadata?.category || 'other') }}</span></button></div>
                <p v-if="!filteredPins.length" class="festival-map__hint">{{ pins.length ? 'No locations match your search.' : 'No locations have been added yet.' }}</p>
            </details>
        </template>
    </section>
</template>
<script setup>
import {computed,onMounted,onBeforeUnmount,ref,watch} from 'vue';
import LayerSwitcher from './LayerSwitcher.vue';
import FestivalImageMapLayer from './FestivalImageMapLayer.vue';
import GeoMapLayer from './GeoMapLayer.vue';
import {elevationGradient} from '../utils/elevationColors';
import {imageCalibration, pixelToGeo} from '../utils/imageCalibration';
import {coordinatesText, locationLinks, validLocation} from '../utils/locationLinks';
import {safeLink} from '../utils/mapPins';
import '../styles/mapper.css';
const props=defineProps({festivalId:{type:Number,required:true},apiBase:{type:String,default:'/api/festival-mapper'},selectable:{type:Boolean,default:false},showLocations:{type:Boolean,default:true},pinsOverride:{type:Array,default:null},selectedLocation:{type:Object,default:null}});
const emit=defineEmits(['location-picked']);
const isMobile=ref(false),viewScale=ref(null),resetView=ref(0);
let mobileQuery;
function updateMobile(){isMobile.value=mobileQuery.matches;}
onMounted(()=>{mobileQuery=window.matchMedia('(max-width:767px)');updateMobile();mobileQuery.addEventListener('change',updateMobile);});
onBeforeUnmount(()=>mobileQuery?.removeEventListener('change',updateMobile));
const festival=ref(null),layers=ref([]),loadedPins=ref([]),activeLayerId=ref(null),currentGeo=ref(null);
const loading=ref(true),error=ref(''),search=ref(''),category=ref(''),selectedPin=ref(null),comparing=ref(false),artworkOpacity=ref(50),streetOpacity=ref(100),corners=ref([]),overlayError=ref('');
let loadRequest=0,pointRequest=0,terrainRequest=0,lastPointKey="";
const gridMessage=ref(''),calibration=ref(null),locationNotice=ref(''),manualCopy=ref('');
const terrain=ref(null),terrainLoading=ref(false),terrainError=ref(''),terrainOpacity=ref(0),terrainArtwork=ref(false),terrainCorners=ref([]);
const pickedPoint=ref(null),pointInfo=ref(null),pointLoading=ref(false),copyStatus=ref('');
const settings=ref({what3words_enabled:false,topography_tiles:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',topography_max_zoom:19});
const selectionGeo=computed(()=>props.selectedLocation || pickedPoint.value);
const shareGeo=computed(()=>validLocation(selectionGeo.value) ? selectionGeo.value : validLocation(currentGeo.value) ? currentGeo.value : null);
const mapLinks=computed(()=>shareGeo.value ? locationLinks(shareGeo.value,typeof navigator === 'undefined' ? '' : navigator.userAgent,typeof navigator === 'undefined' ? '' : navigator.platform,typeof navigator === 'undefined' ? 0 : navigator.maxTouchPoints) : null);
watch(shareGeo,()=>{locationNotice.value='';manualCopy.value='';},{deep:true});
async function copyLocation(kind){
 const value=kind==='coordinates' ? coordinatesText(shareGeo.value) : mapLinks.value.share;
 try{await navigator.clipboard.writeText(value);locationNotice.value=kind==='coordinates' ? 'Coordinates copied' : 'Location link copied';manualCopy.value='';}
 catch{manualCopy.value=value;locationNotice.value='Select the text below to copy it.';}
}
async function shareLocation(){
 if(!shareGeo.value)return;
 if(navigator.share){try{await navigator.share({title:selectedPin.value?.label || 'Festival meeting point',text:selectedPin.value?.label || 'Meet here',url:mapLinks.value.share});return;}catch(error){if(error.name==='AbortError')return;}}
 await copyLocation('link');
}
const pins=computed(()=>(props.pinsOverride ?? loadedPins.value).filter(pin=>pin.latitude != null && pin.longitude != null));
const activeLayers=computed(()=>layers.value.filter(layer=>layer.is_active && ['festival-image','geo-map','topography'].includes(layer.id)).map(layer=>layer.id === 'geo-map' ? {...layer,name:settings.value.what3words_enabled ? 'what3words' : 'Map'} : layer));
const hasImage=computed(()=>activeLayers.value.some(layer=>layer.id==='festival-image'));
const hasGeo=computed(()=>activeLayers.value.some(layer=>layer.id==='geo-map'));
const hasTopography=computed(()=>activeLayers.value.some(layer=>layer.id==='topography'));
const canCompare=computed(()=>hasImage.value && hasGeo.value && festival.value?.map_image_url);
const categories=computed(()=>[...new Set(pins.value.map(pin=>pin.metadata?.category || 'other'))].sort());
const filteredPins=computed(()=>pins.value.filter(pin=>(!category.value || (pin.metadata?.category || 'other')===category.value) && `${pin.label || ''} ${pin.metadata?.description || ''}`.toLowerCase().includes(search.value.toLowerCase())));
const categoryLabel=value=>String(value).replaceAll('_',' ').replace(/\b\w/g,letter=>letter.toUpperCase());
async function apiFetch(path,body){const response=await fetch(`${props.apiBase}${path}`,{headers:{Accept:'application/json',...(body?{'Content-Type':'application/json'}:{})},...(body?{method:'POST',body:JSON.stringify(body)}:{})});if(!response.ok)throw new Error('The map could not be loaded. Please try again.');return response.json();}
async function load(){viewScale.value=null;calibration.value=null;terrainRequest++;terrain.value=null;terrainLoading.value=false;terrainError.value='';terrainCorners.value=[];const request=++loadRequest;loading.value=true;error.value='';selectedPin.value=null;pickedPoint.value=null;pointInfo.value=null;pointRequest++;currentGeo.value=null;corners.value=[];comparing.value=false;activeLayerId.value=null;search.value='';category.value='';overlayError.value='';
    try{const [data,enabled,locations,options]=await Promise.all([apiFetch(`/festivals/${props.festivalId}`),apiFetch(`/festivals/${props.festivalId}/layers`),apiFetch(`/festivals/${props.festivalId}/pins`),apiFetch(`/location-info/settings`).catch(()=>settings.value)]);if(request!==loadRequest)return;festival.value=data;settings.value=options;layers.value=enabled;loadedPins.value=locations;
        if(data.map_width && data.map_height){try{const points=await Promise.all([[0,0],[data.map_width,0],[0,data.map_height]].map(([x,y])=>apiFetch(`/festivals/${props.festivalId}/coordinates/to-geo`,{x,y})));if(request!==loadRequest)return;corners.value=points.map(point=>point.geo);calibration.value=options.local_affine_calibration === true ? imageCalibration(data,corners.value) : null;if(calibration.value)currentGeo.value=pixelToGeo(calibration.value,data.map_width/2,data.map_height/2);else{const centre=await apiFetch(`/festivals/${props.festivalId}/coordinates/to-geo`,{x:data.map_width/2,y:data.map_height/2});if(request!==loadRequest)return;currentGeo.value=centre.geo;}}catch{/* Artwork remains available before calibration. */}}
        activeLayerId.value=activeLayers.value[0]?.id || null;
    }catch(problem){if(request===loadRequest)error.value=problem.message;}finally{if(request===loadRequest)loading.value=false;}}
async function loadTerrain(){
 const request=++terrainRequest,id=props.festivalId;terrainLoading.value=true;terrainError.value='';
 try{
  const response=await fetch(`${props.apiBase}/festivals/${id}/terrain`,{headers:{Accept:'application/json'}});
  const data=await response.json();if(!response.ok)throw new Error(data.message || 'Terrain elevation is unavailable.');
  const points=await Promise.all([[0,0],[festival.value.map_width,0],[0,festival.value.map_height]].map(([x,y])=>apiFetch(`/festivals/${id}/coordinates/to-geo`,{x,y})));
  if(request!==terrainRequest)return;terrainCorners.value=points.map(point=>point.geo);terrain.value=data;
 }catch(error){if(request===terrainRequest)terrainError.value=error.message || 'Terrain elevation is temporarily unavailable.';}finally{if(request===terrainRequest)terrainLoading.value=false;}
}
watch(activeLayerId,id=>{if(id==='topography' && !terrain.value && !terrainLoading.value)loadTerrain();});
function switchLayer(id){comparing.value=false;activeLayerId.value=id;}
async function toggleCompare(){if(comparing.value){comparing.value=false;return;}comparing.value=true;activeLayerId.value='geo-map';if(corners.value.length)return;const id=props.festivalId;try{const points=await Promise.all([[0,0],[festival.value.map_width,0],[0,festival.value.map_height]].map(([x,y])=>apiFetch(`/festivals/${id}/coordinates/to-geo`,{x,y})));if(id!==props.festivalId)return;corners.value=points.map(point=>point.geo);}catch{overlayError.value='Compare layers needs at least three valid calibration points. The street map is still available.';}}
function onPositionChanged(geo){if(!geo){currentGeo.value=null;return;}if(currentGeo.value && Math.abs(currentGeo.value.latitude-geo.latitude)<1e-9 && Math.abs(currentGeo.value.longitude-geo.longitude)<1e-9)return;currentGeo.value={latitude:Number(geo.latitude),longitude:Number(geo.longitude)};}
function directionsUrl(pin){return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(`${pin.latitude},${pin.longitude}`)}`;}
function selectPin(pin){pickLocation({latitude:pin.latitude,longitude:pin.longitude});selectedPin.value=pin;}
function pickLocation(geo){selectedPin.value=null;pickedPoint.value={latitude:Number(geo.latitude),longitude:Number(geo.longitude)};onPositionChanged(geo);emit('location-picked',pickedPoint.value);}
async function lookupPoint(geo,force=false){const key=geo ? `${geo.latitude},${geo.longitude}` : "";if(!force && key && key===lastPointKey && (pointLoading.value || pointInfo.value))return;lastPointKey=key;const request=++pointRequest;pointInfo.value=null;copyStatus.value='';if(!geo){pointLoading.value=false;return;}pointLoading.value=true;
 try{const query=new URLSearchParams(geo);const response=await fetch(`${props.apiBase}/location-info/point?${query}`,{headers:{Accept:'application/json'}});if(!response.ok)throw Error();const data=await response.json();if(request===pointRequest)pointInfo.value=data;}catch{if(request===pointRequest)pointInfo.value={errors:{elevation:'Location information is temporarily unavailable.'}};}finally{if(request===pointRequest)pointLoading.value=false;}}
async function copyAddress(){try{await navigator.clipboard.writeText(`///${pointInfo.value.what3words.words} ${pointInfo.value.what3words.url}`);copyStatus.value='Copied';}catch{copyStatus.value='Select the address above to copy it';}}
async function shareAddress(){const address=pointInfo.value?.what3words;if(!address)return;if(navigator.share){try{await navigator.share({title:'Festival meeting point',text:`///${address.words}`,url:address.url});}catch(error){if(error.name!=='AbortError')await copyAddress();}}else await copyAddress();}
watch(selectionGeo,geo=>lookupPoint(geo),{deep:true});
watch(()=>props.selectedLocation,geo=>{if(props.selectable && !geo)pickedPoint.value=null;},{deep:true});
watch(filteredPins,list=>{if(selectedPin.value && !list.some(pin=>pin.id===selectedPin.value.id))selectedPin.value=null;});
watch(()=>props.festivalId,load);onMounted(load);
</script>
<style scoped>
.festival-map { padding:16px; border-radius:16px; --mapper-map-height: clamp(350px, 65vh, 650px); }
.festival-map__header { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:16px; }
h2,h3 { margin:0; font-weight:700; } h2 { font-size:1.25rem; } p { margin:6px 0 0; } .festival-map__header p,.festival-map__hint { font-size:.875rem; opacity:.75; }
.festival-map__buttons { display:flex; gap:8px; flex-wrap:wrap; }
.festival-map__quick-actions { display:flex; margin-top:10px; }
.festival-map__panel { margin-top:12px; }
.festival-map__panel > summary { cursor:pointer; font-weight:600; min-height:44px; display:list-item; align-content:center; }
.festival-map__panel[open] > summary { margin-bottom:10px; }
.festival-map__sharing { margin-top:10px; }
.festival-map__map-options { margin-top:10px; }
.festival-map__map-options summary {cursor:pointer;font-size:.875rem;}
.festival-map__map-options .festival-map__buttons {margin-top:8px;}
.festival-map__terrain-controls { margin-bottom:16px; }
.festival-map__gradient { height:14px;border-radius:7px;margin-top:8px; }
.festival-map__legend-labels { display:flex;justify-content:space-between;gap:8px;font-size:.8rem;margin-top:4px; }
.festival-map__terrain-opacity { display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;align-items:center; }
.festival-map__terrain-opacity span { margin-left:auto; }
.festival-map__terrain-opacity input { width:100%;accent-color:var(--mapper-accent); }
.festival-map__artwork-toggle { display:flex;gap:8px;align-items:center;margin-top:12px;font-size:.875rem; }
.festival-map__artwork-toggle input {accent-color:var(--mapper-accent);}
.festival-map__canvas-wrapper { position:relative; overflow:hidden; border-radius:12px; isolation:isolate; }
.festival-map__opacity,.festival-map__search { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-bottom:16px; }
.festival-map__opacity label,.festival-map__search label { font-size:.875rem; font-weight:600; display:flex; flex-direction:column; gap:6px; } .festival-map__opacity span { align-self:flex-end; }
.festival-map__message { padding:24px 0; } .festival-map__hint { margin:12px 0; }
.festival-map__locations { margin-top:16px; }
.festival-map__pin-list { display:flex; flex-wrap:wrap; gap:8px; }
.festival-map__pin { display:flex; flex-direction:column; align-items:flex-start; padding:10px 14px; min-height:44px; border:1px solid rgba(255,255,255,.2); border-radius:9px; background:rgba(255,255,255,.04); overflow-wrap:anywhere; text-align:left; }
.festival-map__pin span { font-size:.75rem; opacity:.7; } .festival-map__pin[aria-pressed="true"] { border-color:var(--mapper-accent); }
.festival-map__detail { display:grid; grid-template-columns:1fr auto; gap:12px; padding:16px; margin-bottom:16px; border:1px solid rgba(255,255,255,.2); border-radius:12px; background:rgba(0,0,0,.2); overflow-wrap:anywhere; }
.festival-map__description,.festival-map__detail .festival-map__buttons { grid-column:1/-1; white-space:pre-wrap; }
 .festival-map__point { margin:12px 0; padding:12px; border:1px solid rgba(255,255,255,.2);border-radius:9px; } .festival-map__point a {color:var(--mapper-accent);font-weight:600;}
a.mapper-button { display:inline-flex; align-items:center; text-decoration:none; }
@media(max-width:767px) { .festival-map__header { flex-direction:column; align-items:stretch; } .festival-map__search { grid-template-columns:1fr; } .festival-map { padding:12px; --mapper-map-height:clamp(280px,42svh,380px); }
.festival-map__header {gap:10px;margin-bottom:12px;}
.festival-map__header p {display:none;}
.festival-map__buttons :deep(.layer-switcher) {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%;gap:6px;}
.festival-map__buttons :deep(.layer-switcher__btn) {padding:8px 4px;font-size:.8rem;min-width:0;}
.festival-map__sharing {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));}
.festival-map__sharing .mapper-button {justify-content:center;padding:8px;font-size:.8rem;}
.festival-map__panel:not(.festival-map__point) {border-top:1px solid rgba(255,255,255,.15);}
.festival-map__opacity {grid-template-columns:1fr;gap:12px;margin-top:12px;}
.festival-map__hint {font-size:.8rem;}
.festival-map__canvas-wrapper :deep(.leaflet-control-zoom a) {width:44px;height:44px;line-height:44px;}
 }
</style>
