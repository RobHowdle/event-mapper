<template>
	<section class="festival-admin" :style="themeStyles">
		<header class="festival-admin__hero">
			<div>
				<p class="festival-admin__eyebrow">Festival Mapper</p>
				<h1>{{ title }}</h1>
				<p class="festival-admin__subtitle">{{ subtitle }}</p>
			</div>

			<div class="festival-admin__hero-actions">
				<label class="festival-admin__field">
					<span>Festival</span>
					<select
						v-model="selectedFestivalId"
						@change="onFestivalChange">
						<option :value="null">Create a new festival</option>
						<option
							v-for="festivalOption in festivals"
							:key="festivalOption.id"
							:value="festivalOption.id">
							{{ festivalOption.name }} {{ festivalOption.year }}
						</option>
					</select>
				</label>

				<button
					class="festival-admin__secondary-button"
					type="button"
					@click="resetForCreate">
					New Festival
				</button>
			</div>
		</header>

		<p
			v-if="statusMessage"
			class="festival-admin__status festival-admin__status--success">
			{{ statusMessage }}
		</p>

		<p
			v-if="errorMessage"
			class="festival-admin__status festival-admin__status--error">
			{{ errorMessage }}
		</p>

		<div class="festival-admin__grid">
			<nav class="festival-admin__sections" aria-label="Admin sections">
				<button
					v-for="section in sections"
					:key="section.key"
					type="button"
					class="festival-admin__section-card"
					:class="{
						'festival-admin__section-card--active':
							activeSection === section.key,
					}"
					@click="activeSection = section.key">
					<span class="festival-admin__section-icon">{{
						section.icon
					}}</span>

					<span class="festival-admin__section-copy">
						<strong>{{ section.title }}</strong>
						<small>{{ section.description }}</small>
					</span>

					<span class="festival-admin__section-meta">{{
						section.meta
					}}</span>
				</button>
			</nav>

			<div class="festival-admin__panel">
				<section
					v-if="activeSection === 'festival'"
					class="festival-admin__surface">
					<div class="festival-admin__surface-header">
						<div>
							<h2>Festival Settings</h2>
							<p>
								Create the festival record and describe the
								event.
							</p>
						</div>
					</div>

					<form
						class="festival-admin__stack"
						@submit.prevent="saveFestival">
						<label class="festival-admin__field">
							<span>Name</span>
							<input
								v-model.trim="festivalForm.name"
								type="text"
								required />
						</label>

						<div class="festival-admin__split">
							<label class="festival-admin__field">
								<span>Year</span>
								<input
									v-model.number="festivalForm.year"
									type="number"
									min="1900"
									max="2100"
									required />
							</label>

							<label class="festival-admin__field">
								<span>Description</span>
								<input
									v-model.trim="festivalForm.description"
									type="text"
									placeholder="Optional short description" />
							</label>
						</div>

						<div class="festival-admin__actions">
							<button
								class="festival-admin__primary-button"
								type="submit"
								:disabled="isSaving">
								{{
									selectedFestivalId
										? "Save Changes"
										: "Create Festival"
								}}
							</button>

							<button
								v-if="selectedFestivalId"
								type="button"
								class="festival-admin__danger-button"
								:disabled="isSaving"
								@click="deleteFestival">
								Delete Festival
							</button>
						</div>
					</form>
				</section>

				<section
					v-else-if="activeSection === 'map'"
					class="festival-admin__surface">
					<div class="festival-admin__surface-header">
						<div>
							<h2>Map Image</h2>
							<p>
								Upload the base artwork that all coordinates map
								against.
							</p>
						</div>
					</div>

					<div
						v-if="!selectedFestivalId"
						class="festival-admin__empty-state">
						Create a festival first to upload its map image.
					</div>

					<div v-else class="festival-admin__stack">
						<label class="festival-admin__upload">
							<input
								type="file"
								accept="image/*"
								@change="onMapFileSelected" />
							<span>Select image</span>
						</label>

						<div class="festival-admin__actions">
							<button
								type="button"
								class="festival-admin__primary-button"
								:disabled="!mapFile || isSaving"
								@click="uploadMapImage">
								Upload Map
							</button>

							<span
								v-if="mapFile"
								class="festival-admin__helper-text">
								{{ mapFile.name }}
							</span>
						</div>

						<div
							v-if="activeFestival?.map_image_url"
							class="festival-admin__map-preview">
							<img
								:src="activeFestival.map_image_url"
								:alt="`${activeFestival.name} map`" />

							<div class="festival-admin__map-meta">
								<span>
									{{ activeFestival.map_width || 0 }} x
									{{ activeFestival.map_height || 0 }}
								</span>

								<span>
									{{ activeFestival.map_image_path }}
								</span>
							</div>
						</div>

						<p v-else class="festival-admin__empty-state">
							No image uploaded yet.
						</p>
					</div>
				</section>

				<section
					v-else-if="activeSection === 'calibration'"
					class="festival-admin__surface">
					<div class="festival-admin__surface-header">
						<div>
							<h2>Calibration Points</h2>
							<p>
								Match locations on your festival artwork with
								their real-world locations.
							</p>
						</div>
					</div>

					<div
						v-if="!selectedFestivalId"
						class="festival-admin__empty-state">
						Create a festival first to calibrate its map.
					</div>

					<div
						v-else-if="!activeFestival?.map_image_url"
						class="festival-admin__empty-state">
						Upload the festival map before adding calibration
						points.
					</div>

					<div v-else class="festival-admin__calibration">
						<div class="festival-admin__calibration-instructions">
							<strong>How calibration works</strong>

							<p>
								Click a location on the festival map, then click
								the exact same location on the real-world map.
								Repeat this for at least two locations, three or
								more would be best.
							</p>

							<div
								class="festival-admin__calibration-status"
								:class="{
									'festival-admin__calibration-status--complete':
										calibrationSelection.pixel &&
										calibrationSelection.geo,
								}">
								<span>
									1.
									{{
										calibrationSelection.pixel
											? "Festival location selected"
											: "Select a location on the festival map"
									}}
								</span>

								<span>
									2.
									{{
										calibrationSelection.geo
											? "Real-world location selected"
											: "Select the matching location on the real map"
									}}
								</span>

								<button
									v-if="
										calibrationSelection.pixel ||
										calibrationSelection.geo
									"
									type="button"
									class="festival-admin__calibration-cancel"
									@click="resetCalibrationSelection">
									Cancel new point
								</button>
							</div>
							Okay be
						</div>

						<div class="festival-admin__calibration-maps">
							<div class="festival-admin__calibration-map-panel">
								<div
									class="festival-admin__calibration-map-header">
									<div>
										<strong>Festival Map</strong>
										<small>
											Click the matching location on your
											artwork.
										</small>
									</div>
								</div>

								<div
									ref="festivalMapContainer"
									class="festival-admin__festival-map"
									@click="handleFestivalMapClick">
									<img
										:src="activeFestival.map_image_url"
										:alt="`${activeFestival.name} map`"
										class="festival-admin__calibration-image" />

									<button
										v-for="point in calibrationPoints"
										:key="`festival-point-${point.id}`"
										type="button"
										class="festival-admin__calibration-marker"
										:style="{
											left: `${(Number(point.pixel_x) / Number(activeFestival.map_width)) * 100}%`,
											top: `${(Number(point.pixel_y) / Number(activeFestival.map_height)) * 100}%`,
										}"
										:title="
											point.label || 'Calibration point'
										"
										@click.stop>
										<span>{{
											point.label || point.id
										}}</span>
									</button>

									<div
										v-if="calibrationSelection.pixel"
										class="festival-admin__calibration-marker festival-admin__calibration-marker--pending"
										:style="{
											left: `${(calibrationSelection.pixel.x / Number(activeFestival.map_width)) * 100}%`,
											top: `${(calibrationSelection.pixel.y / Number(activeFestival.map_height)) * 100}%`,
										}">
										<span>New point</span>
									</div>
								</div>
							</div>

							<div class="festival-admin__calibration-map-header">
								<div>
									<strong>Real World Map</strong>
									<small>
										{{
											calibrationSelection.pixel &&
											!calibrationSelection.geo
												? "Click once to place the matching real-world point."
												: "Pan and zoom to find the matching location."
										}}
									</small>
								</div>
							</div>

							<div
								ref="realMapContainer"
								class="festival-admin__real-map"></div>
						</div>

						<div
							v-if="
								calibrationSelection.pixel ||
								calibrationSelection.geo
							"
							class="festival-admin__calibration-form">
							<div class="festival-admin__calibration-selection">
								<div>
									<strong>Festival location</strong>

									<small v-if="calibrationSelection.pixel">
										Pixel
										{{
											Math.round(
												calibrationSelection.pixel.x,
											)
										}},
										{{
											Math.round(
												calibrationSelection.pixel.y,
											)
										}}
									</small>

									<small v-else> Not selected yet </small>
								</div>

								<div>
									<strong>Real-world location</strong>

									<small v-if="calibrationSelection.geo">
										Location selected
									</small>

									<small v-else> Not selected yet </small>
								</div>
							</div>

							<label class="festival-admin__field">
								<span>Label</span>

								<input
									v-model.trim="calibrationForm.label"
									type="text"
									placeholder="Main Stage" />
							</label>

							<div class="festival-admin__actions">
								<button
									type="button"
									class="festival-admin__secondary-button"
									@click="resetCalibrationSelection">
									Reset Selection
								</button>

								<button
									type="button"
									class="festival-admin__primary-button"
									:disabled="
										isSaving ||
										!calibrationSelection.pixel ||
										!calibrationSelection.geo
									"
									@click="saveCalibrationPoint">
									Save Calibration Point
								</button>
							</div>
						</div>

						<div
							v-if="calibrationPoints.length"
							class="festival-admin__calibration-points">
							<h3>Existing Calibration Points</h3>

							<ul class="festival-admin__list">
								<li
									v-for="point in calibrationPoints"
									:key="point.id"
									class="festival-admin__list-item">
									<div>
										<strong>
											{{
												point.label || "Untitled point"
											}}
										</strong>

										<small>
											Pixel {{ point.pixel_x }},
											{{ point.pixel_y }}
										</small>
									</div>

									<button
										type="button"
										class="festival-admin__text-button"
										@click="
											deleteCalibrationPoint(point.id)
										">
										Delete
									</button>
								</li>
							</ul>
						</div>
					</div>
				</section>

				<section
					v-else-if="activeSection === 'layers'"
					class="festival-admin__surface">
					<div class="festival-admin__surface-header">
						<div>
							<h2>Layer Settings</h2>
							<p>
								Enable the coordinate layers your festival
								should resolve.
							</p>
						</div>
					</div>

					<div
						v-if="!selectedFestivalId"
						class="festival-admin__empty-state">
						Create a festival first to configure layers.
					</div>

					<ul v-else class="festival-admin__list">
						<li
							v-for="layer in layers"
							:key="layer.id"
							class="festival-admin__list-item">
							<div>
								<strong>{{ layer.name }}</strong>
								<small>{{ layer.id }}</small>
							</div>

							<button
								type="button"
								class="festival-admin__secondary-button"
								:disabled="isSaving"
								@click="toggleLayer(layer)">
								{{
									layer.is_active ? "Deactivate" : "Activate"
								}}
							</button>
						</li>
					</ul>
				</section>

				<section
					v-else-if="activeSection === 'pins'"
					class="festival-admin__surface">
					<div class="festival-admin__surface-header">
						<div>
							<h2>Locations & Pins</h2>
							<p>
								Add saved locations using geographic coordinates
								and optional metadata.
							</p>
						</div>
					</div>

					<div
						v-if="!selectedFestivalId"
						class="festival-admin__empty-state">
						Create a festival first to manage locations.
					</div>

					<div v-else class="festival-admin__stack">
						<form
							class="festival-admin__stack"
							@submit.prevent="createPin">
							<label class="festival-admin__field">
								<span>Label</span>
								<input
									v-model.trim="pinForm.label"
									type="text"
									required />
							</label>

							<div class="festival-admin__split">
								<label class="festival-admin__field">
									<span>Latitude</span>
									<input
										v-model.number="pinForm.latitude"
										type="number"
										step="any"
										min="-90"
										max="90"
										required />
								</label>

								<label class="festival-admin__field">
									<span>Longitude</span>
									<input
										v-model.number="pinForm.longitude"
										type="number"
										step="any"
										min="-180"
										max="180"
										required />
								</label>
							</div>

							<label class="festival-admin__field">
								<span>Metadata JSON</span>
								<textarea
									v-model="pinForm.metadata"
									rows="4"
									placeholder='{"category":"stage"}' />
							</label>

							<div class="festival-admin__actions">
								<button
									class="festival-admin__primary-button"
									type="submit"
									:disabled="isSaving">
									Add Location
								</button>
							</div>
						</form>

						<ul v-if="pins.length" class="festival-admin__list">
							<li
								v-for="pin in pins"
								:key="pin.id"
								class="festival-admin__list-item">
								<div>
									<strong>{{ pin.label }}</strong>

									<small>
										Geo {{ pin.latitude }},
										{{ pin.longitude }}
									</small>
								</div>

								<button
									type="button"
									class="festival-admin__text-button"
									@click="deletePin(pin.id)">
									Delete
								</button>
							</li>
						</ul>

						<p v-else class="festival-admin__empty-state">
							No locations added yet.
						</p>
					</div>
				</section>
			</div>
		</div>
	</section>
</template>

<script setup>
import {computed, nextTick, onMounted, ref, watch} from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const props = defineProps({
	title: {
		type: String,
		default: "Festival Admin",
	},
	subtitle: {
		type: String,
		default:
			"Manage maps, calibration, layers, and saved festival locations.",
	},
	festivalId: {
		type: Number,
		default: null,
	},
	apiBase: {
		type: String,
		default: "/api/festival-mapper",
	},
	theme: {
		type: Object,
		default: () => ({}),
	},
});

const emit = defineEmits(["festival-selected", "saved"]);

const festivals = ref([]);
const activeFestival = ref(null);
const selectedFestivalId = ref(props.festivalId);
const calibrationPoints = ref([]);
const layers = ref([]);
const pins = ref([]);
const activeSection = ref("festival");
const isSaving = ref(false);
const statusMessage = ref("");
const errorMessage = ref("");
const mapFile = ref(null);

const festivalMapContainer = ref(null);
const realMapContainer = ref(null);

const realMap = ref(null);
const pendingRealMarker = ref(null);
const savedRealMarkers = ref([]);

const calibrationSelection = ref({
	pixel: null,
	geo: null,
});

async function initialiseRealMap() {
	await nextTick();

	if (!realMapContainer.value) {
		return;
	}

	if (realMap.value) {
		realMap.value.remove();
		realMap.value = null;
	}

	realMap.value = L.map(realMapContainer.value).setView([54.5, -1.5], 6);

	L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		maxZoom: 19,
	}).addTo(realMap.value);

	realMap.value.on("click", handleRealMapClick);

	renderSavedRealMarkers();

	if (calibrationPoints.value.length) {
		const bounds = L.latLngBounds(
			calibrationPoints.value.map((point) => [
				Number(point.latitude),
				Number(point.longitude),
			]),
		);

		realMap.value.fitBounds(bounds, {
			padding: [40, 40],
			maxZoom: 17,
		});
	}
}

function renderSavedRealMarkers() {
	if (!realMap.value) {
		return;
	}

	savedRealMarkers.value.forEach((marker) => {
		marker.remove();
	});

	savedRealMarkers.value = [];

	calibrationPoints.value.forEach((point) => {
		const latitude = Number(point.latitude);
		const longitude = Number(point.longitude);

		if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
			return;
		}

		const marker = L.circleMarker([latitude, longitude], {
			radius: 6,
			weight: 2,
			fillOpacity: 0.85,
		})
			.addTo(realMap.value)
			.bindTooltip(point.label || `Point ${point.id}`, {
				direction: "top",
				offset: [0, -8],
			});

		savedRealMarkers.value.push(marker);
	});
}

function handleFestivalMapClick(event) {
	if (!festivalMapContainer.value || !activeFestival.value) {
		return;
	}

	const rect = festivalMapContainer.value.getBoundingClientRect();

	const displayX = event.clientX - rect.left;
	const displayY = event.clientY - rect.top;

	const imageWidth = activeFestival.value.map_width || rect.width;

	const imageHeight = activeFestival.value.map_height || rect.height;

	const x = (displayX / rect.width) * imageWidth;
	const y = (displayY / rect.height) * imageHeight;

	calibrationSelection.value.pixel = {
		x,
		y,
		displayX,
		displayY,
	};
}

function handleRealMapClick(event) {
	// Do not allow a real-world point until a festival-map  point has been selected first.
	if (!calibrationSelection.value.pixel) {
		return;
	}

	const {lat, lng} = event.latlng;

	calibrationSelection.value.geo = {
		latitude: lat,
		longitude: lng,
	};

	if (pendingRealMarker.value) {
		pendingRealMarker.value.remove();
	}

	pendingRealMarker.value = L.circleMarker([lat, lng], {
		radius: 7,
		weight: 3,
		fillOpacity: 1,
	})
		.addTo(realMap.value)
		.bindTooltip("New point", {
			permanent: true,
			direction: "top",
			offset: [0, -10],
		})
		.openTooltip();

	// Point has now been selected, so give normal map
	// navigation back to the user.
	realMap.value.dragging.enable();
	realMap.value.scrollWheelZoom.enable();
	realMap.value.doubleClickZoom.enable();
}

async function saveCalibrationPoint() {
	if (
		!selectedFestivalId.value ||
		!calibrationSelection.value.pixel ||
		!calibrationSelection.value.geo
	) {
		return;
	}

	isSaving.value = true;
	clearMessages();

	try {
		const point = await apiFetch(
			`/festivals/${selectedFestivalId.value}/calibration`,
			{
				method: "POST",
				body: JSON.stringify({
					pixel_x: calibrationSelection.value.pixel.x,
					pixel_y: calibrationSelection.value.pixel.y,
					latitude: calibrationSelection.value.geo.latitude,
					longitude: calibrationSelection.value.geo.longitude,
					label: calibrationForm.value.label || null,
				}),
			},
		);

		calibrationPoints.value = [...calibrationPoints.value, point];

		resetCalibrationSelection();

		setStatus("Calibration point added.");
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

function resetCalibrationSelection() {
	calibrationSelection.value = {
		pixel: null,
		geo: null,
	};

	calibrationForm.value = createCalibrationForm();

	if (pendingRealMarker.value) {
		pendingRealMarker.value.remove();
		pendingRealMarker.value = null;
	}
}

// function pixelToDisplayX(x) {
// 	if (!festivalMapContainer.value || !activeFestival.value?.map_width) {
// 		return x;
// 	}

// 	return (
// 		(x / activeFestival.value.map_width) *
// 		festivalMapContainer.value.clientWidth
// 	);
// }

// function pixelToDisplayY(y) {
// 	if (!festivalMapContainer.value || !activeFestival.value?.map_height) {
// 		return y;
// 	}

// 	return (
// 		(y / activeFestival.value.map_height) *
// 		festivalMapContainer.value.clientHeight
// 	);
// }

const festivalForm = ref(createFestivalForm());
const calibrationForm = ref(createCalibrationForm());
const pinForm = ref(createPinForm());

const sections = computed(() => {
	const festivalLabel = activeFestival.value
		? `${activeFestival.value.name} ${activeFestival.value.year}`
		: "Start here";

	return [
		{
			key: "festival",
			icon: "01",
			title: "Festival Settings",
			description: "Create the event record and core details.",
			meta: festivalLabel,
		},
		{
			key: "map",
			icon: "02",
			title: "Map Image",
			description: "Upload the base artwork and review its dimensions.",
			meta: activeFestival.value?.map_width
				? `${activeFestival.value.map_width} x ${activeFestival.value.map_height}`
				: "No image",
		},
		{
			key: "calibration",
			icon: "03",
			title: "Calibration",
			description: "Align image pixels to geographic coordinates.",
			meta: `${calibrationPoints.value.length} points`,
		},
		{
			key: "layers",
			icon: "04",
			title: "Layers",
			description: "Switch coordinate layers on or off.",
			meta: `${layers.value.filter((layer) => layer.is_active).length} active`,
		},
		{
			key: "pins",
			icon: "05",
			title: "Locations",
			description: "Store the saved points of interest for the map.",
			meta: `${pins.value.length} saved`,
		},
	];
});

const themeStyles = computed(() => ({
	"--festival-admin-accent": props.theme.accent ?? "#ff6a3d",
	"--festival-admin-accent-soft":
		props.theme.accentSoft ?? "rgba(255, 106, 61, 0.18)",
	"--festival-admin-panel": props.theme.panel ?? "rgba(22, 22, 26, 0.82)",
	"--festival-admin-panel-strong":
		props.theme.panelStrong ?? "rgba(32, 32, 38, 0.96)",
	"--festival-admin-border":
		props.theme.border ?? "rgba(255, 255, 255, 0.08)",
	"--festival-admin-text": props.theme.text ?? "#f8f3ef",
	"--festival-admin-text-muted":
		props.theme.textMuted ?? "rgba(248, 243, 239, 0.72)",
	"--festival-admin-background":
		props.theme.background ??
		"radial-gradient(circle at top, rgba(255, 106, 61, 0.2), transparent 35%), #09080a",
}));

watch(
	() => props.festivalId,
	(newFestivalId) => {
		selectedFestivalId.value = newFestivalId;

		if (newFestivalId) {
			loadFestivalWorkspace(newFestivalId);
		}
	},
);

watch(activeSection, async (section) => {
	if (section === "calibration" && selectedFestivalId.value) {
		await initialiseRealMap();
	}
});

watch(
	() => calibrationSelection.value.pixel,
	(pixel) => {
		if (!realMap.value) {
			return;
		}

		if (pixel) {
			// Festival point has been chosen:
			// turn the real map into "pick a point" mode.
			realMap.value.dragging.disable();
			realMap.value.scrollWheelZoom.disable();
			realMap.value.doubleClickZoom.disable();
		} else {
			// No festival point selected:
			// allow normal map navigation.
			realMap.value.dragging.enable();
			realMap.value.scrollWheelZoom.enable();
			realMap.value.doubleClickZoom.enable();
		}
	},
);

watch(
	calibrationPoints,
	() => {
		renderSavedRealMarkers();
	},
	{deep: true},
);

async function apiFetch(path, options = {}) {
	const headers = {
		Accept: "application/json",
		...(options.headers ?? {}),
	};

	if (!(options.body instanceof FormData)) {
		headers["Content-Type"] = "application/json";
	}

	const response = await fetch(`${props.apiBase}${path}`, {
		...options,
		headers,
	});

	if (!response.ok) {
		const payload = await safeJson(response);
		const message = payload?.message || `API error ${response.status}`;
		throw new Error(message);
	}

	return response.status === 204 ? null : response.json();
}

async function safeJson(response) {
	try {
		return await response.json();
	} catch {
		return null;
	}
}

function createFestivalForm() {
	return {
		name: "",
		year: new Date().getFullYear(),
		description: "",
	};
}

function createCalibrationForm() {
	return {
		pixel_x: 0,
		pixel_y: 0,
		latitude: 0,
		longitude: 0,
		label: "",
	};
}

function createPinForm() {
	return {
		label: "",
		latitude: 0,
		longitude: 0,
		metadata: "{}",
	};
}

function syncFestivalForm(festival) {
	festivalForm.value = {
		name: festival?.name ?? "",
		year: festival?.year ?? new Date().getFullYear(),
		description: festival?.description ?? "",
	};
}

function resetForCreate() {
	selectedFestivalId.value = null;
	activeFestival.value = null;
	calibrationPoints.value = [];
	layers.value = [];
	pins.value = [];
	mapFile.value = null;
	syncFestivalForm(null);
	activeSection.value = "festival";
	emit("festival-selected", null);
	clearMessages();
}

function clearMessages() {
	statusMessage.value = "";
	errorMessage.value = "";
}

function setStatus(message) {
	statusMessage.value = message;
	errorMessage.value = "";
}

function setError(error) {
	errorMessage.value = error instanceof Error ? error.message : String(error);
	statusMessage.value = "";
	return null;
}

async function loadFestivals() {
	festivals.value = await apiFetch("/festivals");

	if (!selectedFestivalId.value && props.festivalId) {
		selectedFestivalId.value = props.festivalId;
	}

	if (!selectedFestivalId.value && festivals.value.length === 1) {
		selectedFestivalId.value = festivals.value[0].id;
	}
}

async function loadFestivalWorkspace(festivalId) {
	clearMessages();

	try {
		const festival = await apiFetch(`/festivals/${festivalId}`);

		activeFestival.value = festival;
		syncFestivalForm(festival);

		calibrationPoints.value = await apiFetch(
			`/festivals/${festivalId}/calibration`,
		);

		pins.value = await apiFetch(`/festivals/${festivalId}/pins`);

		layers.value = await apiFetch(`/festivals/${festivalId}/layers`);
		emit("festival-selected", festival);
	} catch (error) {
		setError(error);
	}
}

async function onFestivalChange() {
	if (!selectedFestivalId.value) {
		resetForCreate();
		return;
	}

	await loadFestivalWorkspace(selectedFestivalId.value);
}

async function saveFestival() {
	isSaving.value = true;
	clearMessages();

	try {
		const isUpdating = Boolean(selectedFestivalId.value);
		const method = selectedFestivalId.value ? "PATCH" : "POST";
		const path = selectedFestivalId.value
			? `/festivals/${selectedFestivalId.value}`
			: "/festivals";

		const festival = await apiFetch(path, {
			method,
			body: JSON.stringify(festivalForm.value),
		});

		selectedFestivalId.value = festival.id;
		activeFestival.value = festival;
		syncFestivalForm(festival);

		await loadFestivals();
		await loadFestivalWorkspace(festival.id);

		setStatus(isUpdating ? "Festival saved." : "Festival created.");
		emit("saved", festival);
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

async function deleteFestival() {
	if (!selectedFestivalId.value) {
		return;
	}

	isSaving.value = true;
	clearMessages();

	try {
		await apiFetch(`/festivals/${selectedFestivalId.value}`, {
			method: "DELETE",
		});

		await loadFestivals();
		resetForCreate();
		setStatus("Festival deleted.");
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

function onMapFileSelected(event) {
	mapFile.value = event.target.files?.[0] ?? null;
}

async function uploadMapImage() {
	if (!selectedFestivalId.value || !mapFile.value) {
		return;
	}

	isSaving.value = true;
	clearMessages();

	try {
		const payload = new FormData();
		payload.append("map_image", mapFile.value);

		await apiFetch(`/festivals/${selectedFestivalId.value}/map`, {
			method: "POST",
			body: payload,
		});

		mapFile.value = null;

		await loadFestivalWorkspace(selectedFestivalId.value);

		setStatus("Map image uploaded.");
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

async function deleteCalibrationPoint(pointId) {
	if (!selectedFestivalId.value) {
		return;
	}

	isSaving.value = true;
	clearMessages();

	try {
		await apiFetch(
			`/festivals/${selectedFestivalId.value}/calibration/${pointId}`,
			{
				method: "DELETE",
			},
		);

		calibrationPoints.value = calibrationPoints.value.filter(
			(point) => point.id !== pointId,
		);

		setStatus("Calibration point removed.");
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

async function toggleLayer(layer) {
	if (!selectedFestivalId.value) {
		return;
	}

	isSaving.value = true;
	clearMessages();

	try {
		await apiFetch(
			`/festivals/${selectedFestivalId.value}/layers/${layer.id}/${layer.is_active ? "deactivate" : "activate"}`,
			{method: "POST"},
		);

		layers.value = layers.value.map((entry) =>
			entry.id === layer.id
				? {...entry, is_active: !entry.is_active}
				: entry,
		);

		setStatus(`Layer ${layer.is_active ? "deactivated" : "activated"}.`);
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

async function createPin() {
	if (!selectedFestivalId.value) {
		return;
	}

	isSaving.value = true;
	clearMessages();

	try {
		const metadata = pinForm.value.metadata.trim()
			? JSON.parse(pinForm.value.metadata)
			: {};

		const pin = await apiFetch(
			`/festivals/${selectedFestivalId.value}/pins`,
			{
				method: "POST",
				body: JSON.stringify({
					label: pinForm.value.label,
					latitude: pinForm.value.latitude,
					longitude: pinForm.value.longitude,
					metadata,
				}),
			},
		);

		pins.value = [...pins.value, pin];
		pinForm.value = createPinForm();

		setStatus("Location added.");
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

async function deletePin(pinId) {
	if (!selectedFestivalId.value) {
		return;
	}

	isSaving.value = true;
	clearMessages();

	try {
		await apiFetch(`/festivals/${selectedFestivalId.value}/pins/${pinId}`, {
			method: "DELETE",
		});

		pins.value = pins.value.filter((pin) => pin.id !== pinId);

		setStatus("Location removed.");
	} catch (error) {
		setError(error);
	} finally {
		isSaving.value = false;
	}
}

onMounted(async () => {
	try {
		await loadFestivals();

		if (selectedFestivalId.value) {
			await loadFestivalWorkspace(selectedFestivalId.value);
		} else {
			syncFestivalForm(null);
		}
	} catch (error) {
		setError(error);
	}
});
</script>

<style scoped>
.festival-admin {
	padding: 1.5rem;
	border-radius: 28px;
	background: var(--festival-admin-background);
	color: var(--festival-admin-text);
	font-family: "Avenir Next", "Segoe UI", sans-serif;
}

.festival-admin__hero {
	display: flex;
	justify-content: space-between;
	gap: 1.5rem;
	padding: 1.5rem;
	border: 1px solid var(--festival-admin-border);
	border-radius: 24px;
	background: linear-gradient(
		145deg,
		var(--festival-admin-panel-strong),
		var(--festival-admin-panel)
	);
	box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
	margin-bottom: 1.5rem;
}

.festival-admin__eyebrow {
	margin: 0 0 0.5rem;
	font-size: 0.75rem;
	letter-spacing: 0.16em;
	text-transform: uppercase;
	color: var(--festival-admin-accent);
}

.festival-admin__hero h1,
.festival-admin__surface-header h2 {
	margin: 0;
	font-weight: 700;
}

.festival-admin__subtitle,
.festival-admin__surface-header p,
.festival-admin__helper-text,
.festival-admin__map-meta,
.festival-admin__list-item small,
.festival-admin__section-copy small {
	color: var(--festival-admin-text-muted);
}

.festival-admin__hero-actions,
.festival-admin__stack,
.festival-admin__field,
.festival-admin__surface,
.festival-admin__section-copy,
.festival-admin__map-meta {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.festival-admin__hero-actions {
	min-width: min(320px, 100%);
	align-self: flex-end;
	gap: 1rem;
}

.festival-admin__field span {
	font-size: 0.85rem;
	font-weight: 600;
	color: var(--festival-admin-text-muted);
}

.festival-admin__field input,
.festival-admin__field select,
.festival-admin__field textarea {
	width: 100%;
	padding: 0.9rem 1rem;
	border: 1px solid var(--festival-admin-border);
	border-radius: 14px;
	background: rgba(255, 255, 255, 0.04);
	color: var(--festival-admin-text);
	font: inherit;
}

.festival-admin__field textarea {
	resize: vertical;
	min-height: 7rem;
}

.festival-admin__grid {
	display: grid;
	grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
	gap: 1.5rem;
}

.festival-admin__sections,
.festival-admin__panel {
	display: flex;
	flex-direction: column;
	gap: 1rem;
}

.festival-admin__section-card,
.festival-admin__surface,
.festival-admin__status {
	border: 1px solid var(--festival-admin-border);
	border-radius: 22px;
	background: var(--festival-admin-panel);
	backdrop-filter: blur(16px);
	box-shadow: 0 18px 40px rgba(0, 0, 0, 0.18);
}

.festival-admin__section-card {
	display: grid;
	grid-template-columns: auto 1fr auto;
	align-items: center;
	gap: 1rem;
	width: 100%;
	padding: 1rem 1.1rem;
	color: inherit;
	text-align: left;
	cursor: pointer;
	transition:
		transform 180ms ease,
		border-color 180ms ease,
		background 180ms ease;
}

.festival-admin__section-card:hover,
.festival-admin__section-card--active {
	transform: translateY(-2px);
	border-color: rgba(255, 106, 61, 0.38);
	background: linear-gradient(
		145deg,
		rgba(255, 106, 61, 0.18),
		var(--festival-admin-panel)
	);
}

.festival-admin__section-icon,
.festival-admin__section-meta {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 0.55rem 0.7rem;
	border-radius: 12px;
	background: var(--festival-admin-accent-soft);
	color: var(--festival-admin-accent);
	font-size: 0.8rem;
	font-weight: 700;
	letter-spacing: 0.08em;
	text-transform: uppercase;
}

.festival-admin__section-meta {
	padding-inline: 0.8rem;
	white-space: nowrap;
	font-size: 0.72rem;
}

.festival-admin__surface {
	padding: 1.5rem;
	gap: 1.25rem;
	min-height: 100%;
}

.festival-admin__surface-header {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
}

.festival-admin__split {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 1rem;
}

.festival-admin__split--four {
	grid-template-columns: repeat(4, minmax(0, 1fr));
}

.festival-admin__actions {
	display: flex;
	align-items: center;
	gap: 0.75rem;
	flex-wrap: wrap;
}

.festival-admin__primary-button,
.festival-admin__secondary-button,
.festival-admin__danger-button,
.festival-admin__text-button,
.festival-admin__upload {
	appearance: none;
	border: 0;
	border-radius: 14px;
	padding: 0.9rem 1.1rem;
	font: inherit;
	font-weight: 600;
	cursor: pointer;
	transition:
		opacity 180ms ease,
		transform 180ms ease;
}

.festival-admin__primary-button,
.festival-admin__upload {
	background: linear-gradient(135deg, var(--festival-admin-accent), #ff8f50);
	color: #1a120f;
}

.festival-admin__secondary-button {
	background: rgba(255, 255, 255, 0.08);
	color: var(--festival-admin-text);
	border: 1px solid var(--festival-admin-border);
}

.festival-admin__danger-button,
.festival-admin__text-button {
	background: transparent;
	color: #ffb1a0;
	padding-inline: 0;
}

.festival-admin__primary-button:disabled,
.festival-admin__secondary-button:disabled,
.festival-admin__danger-button:disabled {
	opacity: 0.55;
	cursor: not-allowed;
	transform: none;
}

.festival-admin__upload {
	position: relative;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	max-width: 180px;
	overflow: hidden;
}

.festival-admin__upload input {
	position: absolute;
	inset: 0;
	opacity: 0;
	cursor: pointer;
}

.festival-admin__list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 0.85rem;
}

.festival-admin__list-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
	padding: 1rem 1.1rem;
	border-radius: 18px;
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid var(--festival-admin-border);
}

.festival-admin__map-preview {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	padding: 1rem;
	border-radius: 22px;
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid var(--festival-admin-border);
}

.festival-admin__map-preview img {
	width: 100%;
	max-height: 420px;
	object-fit: contain;
	border-radius: 16px;
	background: rgba(0, 0, 0, 0.18);
}

.festival-admin__empty-state,
.festival-admin__status {
	padding: 1rem 1.1rem;
	color: var(--festival-admin-text-muted);
}

.festival-admin__status {
	margin: 0 0 1rem;
	border-radius: 18px;
}

.festival-admin__status--success {
	border-color: rgba(88, 214, 141, 0.28);
	color: #c2ffd8;
}

.festival-admin__status--error {
	border-color: rgba(255, 106, 61, 0.28);
	color: #ffd2c4;
}

@media (max-width: 960px) {
	.festival-admin__hero,
	.festival-admin__grid {
		grid-template-columns: 1fr;
		display: grid;
	}

	.festival-admin__hero-actions {
		min-width: 0;
		align-self: stretch;
	}

	.festival-admin__split,
	.festival-admin__split--four {
		grid-template-columns: 1fr;
	}

	.festival-admin__list-item,
	.festival-admin__section-card {
		grid-template-columns: 1fr;
		align-items: flex-start;
	}

	.festival-admin__list-item {
		flex-direction: column;
	}
}

.festival-admin__calibration {
	display: flex;
	flex-direction: column;
	gap: 1.25rem;
}

.festival-admin__calibration-instructions {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.festival-admin__calibration-instructions p {
	margin: 0;
	color: var(--festival-admin-text-muted);
}

.festival-admin__calibration-status {
	display: flex;
	flex-wrap: wrap;
	gap: 0.75rem;
	margin-top: 0.5rem;
}

.festival-admin__calibration-status span {
	padding: 0.6rem 0.8rem;
	border-radius: 10px;
	background: rgba(255, 255, 255, 0.05);
	color: var(--festival-admin-text-muted);
	font-size: 0.85rem;
}

.festival-admin__calibration-status--complete span {
	background: var(--festival-admin-accent-soft);
	color: var(--festival-admin-text);
}

.festival-admin__calibration-maps {
	display: grid;
	grid-template-columns: 1fr;
	gap: 1.5rem;
}

.festival-admin__calibration-map-panel {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	min-width: 0;
}

.festival-admin__calibration-map-header {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
}

.festival-admin__calibration-map-header div {
	display: flex;
	flex-direction: column;
	gap: 0.2rem;
}

.festival-admin__calibration-map-header small {
	color: var(--festival-admin-text-muted);
}

.festival-admin__festival-map {
	position: relative;
	width: 100%;
	overflow: hidden;
	border: 1px solid var(--festival-admin-border);
	border-radius: 18px;
	background: rgba(0, 0, 0, 0.25);
	cursor: crosshair;
}

.festival-admin__calibration-image {
	display: block;
	width: 100%;
	height: auto;
}

.festival-admin__real-map {
	width: 100%;
	height: 500px;
	overflow: hidden;
	border: 1px solid var(--festival-admin-border);
	border-radius: 18px;
}

.festival-admin__calibration-marker {
	position: absolute;
	z-index: 5;
	width: 16px;
	height: 16px;
	padding: 0;
	border: 3px solid white;
	border-radius: 50%;
	background: var(--festival-admin-accent);
	transform: translate(-50%, -50%);
	cursor: default;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
}

.festival-admin__calibration-marker span {
	position: absolute;
	top: -2rem;
	left: 50%;
	transform: translateX(-50%);
	white-space: nowrap;
	padding: 0.3rem 0.5rem;
	border-radius: 6px;
	background: rgba(0, 0, 0, 0.8);
	color: white;
	font-size: 0.7rem;
	pointer-events: none;
}

.festival-admin__calibration-marker--pending {
	background: #ffffff;
	border-color: var(--festival-admin-accent);
	pointer-events: none;
}

.festival-admin__calibration-form {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	padding: 1rem;
	border: 1px solid var(--festival-admin-border);
	border-radius: 18px;
	background: rgba(255, 255, 255, 0.04);
}

.festival-admin__calibration-selection {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 1rem;
}

.festival-admin__calibration-selection > div {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}

.festival-admin__calibration-selection small {
	color: var(--festival-admin-text-muted);
}

.festival-admin__calibration-points {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.festival-admin__calibration-points h3 {
	margin: 0;
	font-size: 1rem;
}

@media (max-width: 900px) {
	.festival-admin__calibration-maps {
		grid-template-columns: 1fr;
	}

	.festival-admin__calibration-selection {
		grid-template-columns: 1fr;
	}
}

.festival-admin__calibration-cancel {
	appearance: none;
	padding: 0.6rem 0.8rem;
	border: 1px solid rgba(255, 106, 61, 0.35);
	border-radius: 10px;
	background: transparent;
	color: #ffb1a0;
	font: inherit;
	font-size: 0.85rem;
	font-weight: 600;
	cursor: pointer;
}
</style>
