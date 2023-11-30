<template>
	<div id="trackmania_main" class="section">
		<h2>
			<TrackmaniaIcon class="icon" />
			{{ t('integration_trackmania', 'Trackmania integration') }}
		</h2>
		<br>
		<div id="trackmania-content">
			PLOP
		</div>
	</div>
</template>

<script>
import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

// import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

// import moment from '@nextcloud/moment'

export default {
	name: 'MainContent',

	components: {
		TrackmaniaIcon,
		// NcButton,
	},

	props: {
		pbs: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		topCount() {
			const tops = {
				1: 0,
				10: 0,
				100: 0,
				1000: 0,
			}
			this.pbs.forEach(pb => {
				const worldPosition = pb.recordPosition.zones.World.ranking.position
				if (worldPosition === 1) {
					tops[1]++
					tops[10]++
					tops[100]++
					tops[1000]++
				} else if (worldPosition <= 10) {
					tops[10]++
					tops[100]++
					tops[1000]++
				} else if (worldPosition <= 100) {
					tops[100]++
					tops[1000]++
				} else if (worldPosition <= 1000) {
					tops[1000]++
				}
			})
			return tops
		},
		medalCount() {
			const medals = {
				bronze: 0,
				silver: 0,
				gold: 0,
				author: 0,
			}
			this.pbs.forEach(pb => {
				const medal = pb.record.medal
				if (medal === 4) {
					medals.author++
					medals.gold++
					medals.silver++
					medals.bronze++
				} else if (medal === 3) {
					medals.gold++
					medals.silver++
					medals.bronze++
				} else if (medal === 2) {
					medals.silver++
					medals.bronze++
				} else if (medal === 1) {
					medals.bronze++
				}
			})
			return medals
		},
	},

	watch: {
	},

	mounted() {
		console.debug('aaaaaaaaaaaaa pbs', this.pbs)
		console.debug('aaaaaaaaaaaaa tops', this.topCount)
		console.debug('aaaaaaaaaaaaa medals', this.medalCount)
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
#trackmania_main {
	#trackmania-content {
		margin-left: 40px;
	}
}
</style>
