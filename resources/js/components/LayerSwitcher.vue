<template>
	<div class="layer-switcher">
		<button
			v-for="layer in layers"
			:key="layer.id"
			class="layer-switcher__btn"
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
.layer-switcher {
	display: flex;
	gap: 0.5rem;
	flex-wrap: wrap;
}

.layer-switcher__btn {
	padding: 0.4rem 0.9rem;
	border: 1px solid #ccc;
	border-radius: 4px;
	background: #fff;
	cursor: pointer;
	font-size: 0.875rem;
}

.layer-switcher__btn--active {
	background: #1a56db;
	color: #fff;
	border-color: #1a56db;
}
</style>
