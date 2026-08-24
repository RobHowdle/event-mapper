import {ref} from "vue";

const currentGeo = ref(null);

export function useMapPosition() {
	function setCurrentGeo(latitude, longitude) {
		currentGeo.value = {
			latitude,
			longitude,
		};
	}

	function clearCurrentGeo() {
		currentGeo.value = null;
	}

	return {
		currentGeo,
		setCurrentGeo,
		clearCurrentGeo,
	};
}
