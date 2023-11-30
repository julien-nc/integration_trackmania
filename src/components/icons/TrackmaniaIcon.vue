<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon trackmania-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 308 200"
			version="1.1"
			viewBox="0 0 308 200"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<path d="M 87.994159,129.75 C 88.39771,127.51094 89.69724,115 78.45,115 H 0 L 12.5,45.75 h 122.25 c 15.9009,0 17.89362,10.635527 16.75,17 l -23,128 H 77 Z" />
			<path d="m 161.75,61.75 c 0.55909,-3.102042 5.59706,-16 22.25,-16 H 307.25 L 294.75,115 H 216.5 c -8.52253,0 -13.93298,7.41026 -15.07561,13.75 l -11.17439,62 H 138.5 Z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'TrackmaniaIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
