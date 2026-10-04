<template>
    <section class="festival-map mapper-ui liquid-glass" :aria-busy="loading">
        <header class="festival-map__header">
            <div><h2>{{ festival?.name || 'Festival' }} map</h2><p>Explore the site and find saved locations.</p></div>
            <div class="festival-map__buttons">
                <LayerSwitcher :layers="activeLayers" :active-layer-id="activeLayerId" @switch="switchLayer" />
                <button v-if="canCompare" class="mapper-button" type="button" :aria-pressed="comparing" @click="toggleCompare">Compare layers</button>
            </div>
        </header>
        <p v-if="loading" class="festival-map__message" role="status">Loading map…</p>
        <div v-else-if="error" class="festival-map__message" role="alert">{{ error }} <button type="button" class="mapper-button" @click="load">Try again</button></div>
        <p v-else-if="!activeLayers.length" class="festival-map__message">No map layers are enabled for this festival.</p>
        <template v-else>
            <div v-if="comparing" class="festival-map__opacity">
                <label>Festival artwork <span>{{ artworkOpacity }}%</span><input v-model.number="artworkOpacity" type="range" min="0" max="100" aria-label="Festival artwork opacity" /></label>
                <label>Street map <span>{{ streetOpacity }}%</span><input v-model.number="streetOpacity" type="range" min="0" max="100" aria-label="Street map opacity" /></label>
                <p v-if="overlayError" role="status">{{ overlayError }}</p>
            </div>
            <div class="festival-map__canvas-wrapper">
                <!-- v-show retains Leaflet instances and their independent zoom levels. -->
                <FestivalImageMapLayer v-if="hasImage && festival" v-show="activeLayerId === 'festival-image'"
                    :key="`image-${festivalId}`" :active="activeLayerId === 'festival-image'" :festival="festival" :current-geo="currentGeo" :api-base="apiBase" :pins="filteredPins" :selectable="true" :selection-geo="selectionGeo"
                    @position-changed="onPositionChanged" @pin-selected="selectPin" @location-picked="pickLocation" />
                <GeoMapLayer v-if="hasGeo" v-show="activeLayerId === 'geo-map'" :key="`geo-${festivalId}`"
                    :api-base="apiBase" :what3words="settings.what3words_enabled" :active="activeLayerId === 'geo-map'" :festival="festival" :current-geo="currentGeo" :pins="filteredPins" :selectable="true" :selection-geo="selectionGeo" :corners="corners"
                    :artwork-opacity="comparing ? artworkOpacity / 100 : 0" :street-opacity="comparing ? streetOpacity / 100 : 1"
                    @position-changed="onPositionChanged" @pin-selected="selectPin" @location-picked="pickLocation" />
                <GeoMapLayer v-if="hasTopography" v-show="activeLayerId === 'topography'" :key="`topography-${festivalId}`" :active="activeLayerId === 'topography'" topography :tile-url="settings.topography_tiles" :tile-max-zoom="settings.topography_max_zoom" :current-geo="currentGeo" :pins="filteredPins" selectable :selection-geo="selectionGeo" @position-changed="onPositionChanged" @pin-selected="selectPin" @location-picked="pickLocation" />
            </div>
            <p class="festival-map__hint">{{ selectable ? 'Click a map to place your location pin. Drag the map to move around.' : 'Click a point to see its what3words address and elevation.' }}</p>
            <div v-if="selectionGeo" class="festival-map__point" aria-label="Selected point" role="region">
                <strong>Selected location</strong><p>{{ Number(selectionGeo.latitude).toFixed(6) }}, {{ Number(selectionGeo.longitude).toFixed(6) }}</p>
                <p v-if="pointLoading" role="status">Looking up location…</p>
                <template v-else>
                    <p v-if="pointInfo?.what3words"><a :href="pointInfo.what3words.url" target="_blank" rel="noopener noreferrer">///{{ pointInfo.what3words.words }}</a></p>
                    <p v-else>{{ pointInfo?.errors?.what3words }}</p>
                    <p v-if="pointInfo?.elevation">Elevation: {{ pointInfo.elevation.metres }} m above sea level <small>(terrain estimate)</small></p>
                    <p v-else>{{ pointInfo?.errors?.elevation }}</p>
                    <div class="festival-map__buttons" v-if="pointInfo?.what3words"><button class="mapper-button" type="button" @click="copyAddress">{{ copyStatus || 'Copy what3words' }}</button><button class="mapper-button" type="button" @click="shareAddress">Share location</button></div>
                </template>
            </div>
            <div v-if="showLocations" class="festival-map__locations">
                <div class="festival-map__search">
                    <label>Find a location<input v-model="search" class="mapper-input" type="search" placeholder="Search locations" /></label>
                    <label>Category<select v-model="category" class="mapper-input"><option value="">All locations</option><option v-for="item in categories" :key="item" :value="item">{{ categoryLabel(item) }}</option></select></label>
                </div>
                <div v-if="selectedPin" class="festival-map__detail" role="region" aria-label="Location details">
                    <div><h3>{{ selectedPin.label || 'Unnamed location' }}</h3><p>{{ categoryLabel(selectedPin.metadata?.category || 'other') }}</p></div>
                    <button class="mapper-button" type="button" aria-label="Close location details" @click="selectedPin=null">×</button>
                    <p v-if="selectedPin.metadata?.description" class="festival-map__description">{{ selectedPin.metadata.description }}</p>
                    <div class="festival-map__buttons">
                        <a v-if="pointInfo?.what3words" class="mapper-button" :href="pointInfo.what3words.url" target="_blank" rel="noopener noreferrer">Open what3words</a>
                        <a v-if="safeLink(selectedPin.metadata?.url)" class="mapper-button" :href="safeLink(selectedPin.metadata.url)" target="_blank" rel="noopener noreferrer">More information</a>
                    </div>
                </div>
                <div class="festival-map__pin-list"><button v-for="pin in filteredPins" :key="pin.id" class="festival-map__pin" type="button" :aria-pressed="selectedPin?.id===pin.id" @click="selectPin(pin)"><strong>{{ pin.label || 'Unnamed location' }}</strong><span>{{ categoryLabel(pin.metadata?.category || 'other') }}</span></button></div>
                <p v-if="!filteredPins.length" class="festival-map__hint">{{ pins.length ? 'No locations match your search.' : 'No locations have been added yet.' }}</p>
            </div>
        </template>
    </section>
</template>
<script setup>
import {computed,onMounted,ref,watch} from 'vue';
import LayerSwitcher from './LayerSwitcher.vue';
import FestivalImageMapLayer from './FestivalImageMapLayer.vue';
import GeoMapLayer from './GeoMapLayer.vue';
import {safeLink} from '../utils/mapPins';
import '../styles/mapper.css';
const props=defineProps({festivalId:{type:Number,required:true},apiBase:{type:String,default:'/api/festival-mapper'},selectable:{type:Boolean,default:false},showLocations:{type:Boolean,default:true},pinsOverride:{type:Array,default:null},selectedLocation:{type:Object,default:null}});
const emit=defineEmits(['location-picked']);
const festival=ref(null),layers=ref([]),loadedPins=ref([]),activeLayerId=ref(null),currentGeo=ref(null);
const loading=ref(true),error=ref(''),search=ref(''),category=ref(''),selectedPin=ref(null),comparing=ref(false),artworkOpacity=ref(50),streetOpacity=ref(100),corners=ref([]),overlayError=ref('');
let loadRequest=0,pointRequest=0,lastPointKey="";
const pickedPoint=ref(null),pointInfo=ref(null),pointLoading=ref(false),copyStatus=ref('');
const settings=ref({what3words_enabled:false,topography_tiles:'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',topography_max_zoom:17});
const selectionGeo=computed(()=>props.selectedLocation || pickedPoint.value);
const pins=computed(()=>(props.pinsOverride ?? loadedPins.value).filter(pin=>pin.latitude != null && pin.longitude != null));
const activeLayers=computed(()=>layers.value.filter(layer=>layer.is_active && ['festival-image','geo-map','topography'].includes(layer.id)));
const hasImage=computed(()=>activeLayers.value.some(layer=>layer.id==='festival-image'));
const hasGeo=computed(()=>activeLayers.value.some(layer=>layer.id==='geo-map'));
const hasTopography=computed(()=>activeLayers.value.some(layer=>layer.id==='topography'));
const canCompare=computed(()=>hasImage.value && hasGeo.value && festival.value?.map_image_url);
const categories=computed(()=>[...new Set(pins.value.map(pin=>pin.metadata?.category || 'other'))].sort());
const filteredPins=computed(()=>pins.value.filter(pin=>(!category.value || (pin.metadata?.category || 'other')===category.value) && `${pin.label || ''} ${pin.metadata?.description || ''}`.toLowerCase().includes(search.value.toLowerCase())));
const categoryLabel=value=>String(value).replaceAll('_',' ').replace(/\b\w/g,letter=>letter.toUpperCase());
async function apiFetch(path,body){const response=await fetch(`${props.apiBase}${path}`,{headers:{Accept:'application/json',...(body?{'Content-Type':'application/json'}:{})},...(body?{method:'POST',body:JSON.stringify(body)}:{})});if(!response.ok)throw new Error('The map could not be loaded. Please try again.');return response.json();}
async function load(){const request=++loadRequest;loading.value=true;error.value='';selectedPin.value=null;pickedPoint.value=null;pointInfo.value=null;pointRequest++;currentGeo.value=null;corners.value=[];comparing.value=false;activeLayerId.value=null;search.value='';category.value='';overlayError.value='';
    try{const [data,enabled,locations,options]=await Promise.all([apiFetch(`/festivals/${props.festivalId}`),apiFetch(`/festivals/${props.festivalId}/layers`),apiFetch(`/festivals/${props.festivalId}/pins`),apiFetch(`/location-info/settings`).catch(()=>settings.value)]);if(request!==loadRequest)return;festival.value=data;settings.value=options;layers.value=enabled;loadedPins.value=locations;
        if(data.map_width && data.map_height){try{const centre=await apiFetch(`/festivals/${props.festivalId}/coordinates/to-geo`,{x:data.map_width/2,y:data.map_height/2});if(request!==loadRequest)return;currentGeo.value=centre.geo;}catch{/* Artwork remains available before calibration. */}}
        activeLayerId.value=activeLayers.value[0]?.id || null;
    }catch(problem){if(request===loadRequest)error.value=problem.message;}finally{if(request===loadRequest)loading.value=false;}}
function switchLayer(id){comparing.value=false;activeLayerId.value=id;}
async function toggleCompare(){if(comparing.value){comparing.value=false;return;}comparing.value=true;activeLayerId.value='geo-map';if(corners.value.length)return;const id=props.festivalId;try{const points=await Promise.all([[0,0],[festival.value.map_width,0],[0,festival.value.map_height]].map(([x,y])=>apiFetch(`/festivals/${id}/coordinates/to-geo`,{x,y})));if(id!==props.festivalId)return;corners.value=points.map(point=>point.geo);}catch{overlayError.value='Compare layers needs at least three valid calibration points. The street map is still available.';}}
function onPositionChanged(geo){if(currentGeo.value && Math.abs(currentGeo.value.latitude-geo.latitude)<1e-9 && Math.abs(currentGeo.value.longitude-geo.longitude)<1e-9)return;currentGeo.value={latitude:Number(geo.latitude),longitude:Number(geo.longitude)};}
function selectPin(pin){pickLocation({latitude:pin.latitude,longitude:pin.longitude});selectedPin.value=pin;}
function pickLocation(geo){selectedPin.value=null;pickedPoint.value={latitude:Number(geo.latitude),longitude:Number(geo.longitude)};onPositionChanged(geo);emit('location-picked',pickedPoint.value);}
async function lookupPoint(geo){const key=geo ? `${geo.latitude},${geo.longitude}` : "";if(key && key===lastPointKey && (pointLoading.value || pointInfo.value))return;lastPointKey=key;const request=++pointRequest;pointInfo.value=null;copyStatus.value='';if(!geo){pointLoading.value=false;return;}pointLoading.value=true;
 try{const query=new URLSearchParams(geo);const response=await fetch(`${props.apiBase}/location-info/point?${query}`,{headers:{Accept:'application/json'}});if(!response.ok)throw Error();const data=await response.json();if(request===pointRequest)pointInfo.value=data;}catch{if(request===pointRequest)pointInfo.value={errors:{what3words:'Location information is temporarily unavailable.'}};}finally{if(request===pointRequest)pointLoading.value=false;}}
async function copyAddress(){try{await navigator.clipboard.writeText(`///${pointInfo.value.what3words.words} ${pointInfo.value.what3words.url}`);copyStatus.value='Copied';}catch{copyStatus.value='Select the address above to copy it';}}
async function shareAddress(){const address=pointInfo.value?.what3words;if(!address)return;if(navigator.share){try{await navigator.share({title:'Festival meeting point',text:`///${address.words}`,url:address.url});}catch(error){if(error.name!=='AbortError')await copyAddress();}}else await copyAddress();}
watch(selectionGeo,lookupPoint,{deep:true});
watch(()=>props.selectedLocation,geo=>{if(props.selectable && !geo)pickedPoint.value=null;},{deep:true});
watch(filteredPins,list=>{if(selectedPin.value && !list.some(pin=>pin.id===selectedPin.value.id))selectedPin.value=null;});
watch(()=>props.festivalId,load);onMounted(load);
</script>
<style scoped>
.festival-map { padding:16px; border-radius:16px; --mapper-map-height: clamp(350px, 65vh, 650px); }
.festival-map__header { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:16px; }
h2,h3 { margin:0; font-weight:700; } h2 { font-size:1.25rem; } p { margin:6px 0 0; } .festival-map__header p,.festival-map__hint { font-size:.875rem; opacity:.75; }
.festival-map__buttons { display:flex; gap:8px; flex-wrap:wrap; }
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
@media(max-width:767px) { .festival-map__header { flex-direction:column; align-items:stretch; } .festival-map__search { grid-template-columns:1fr; } .festival-map { padding:12px; } }
</style>
