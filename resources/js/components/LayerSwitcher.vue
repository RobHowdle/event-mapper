<template>
	<div class="layer-switcher">
		<button
			v-for="layer in layers"
			:key="layer.id"
			class="layer-switcher__btn mapper-button"
			:aria-pressed="layer.id === activeLayerId"
            :class="{'layer-switcher__btn--active': layer.id === activeLayerId}"
			type="button"
			@click="emit('switch', layer.id)">
			{{ layer.name }}
		</button>
	</div>
</template>

<script setup>
defineProps({
	layers: {type: Array, default: () => []},
	activeLayerId: {type: String, default: null},
});

const emit = defineEmits(["switch"]);
</script>

<style scoped>
.layer-switcher { display:flex; flex-wrap:wrap; gap:8px; }
</style>
