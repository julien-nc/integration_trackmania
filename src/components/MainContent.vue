<template>
	<div id="trackmania_main" class="section">
		<h2>
			<TrackmaniaIcon class="icon" />
			{{ t('integration_trackmania', 'Trackmania integration') }}
		</h2>
		<br>
		<div id="trackmania-content">
			<VueGoodTable
				:columns="columns"
				:rows="pbs"
				max-height="600px"
				:fixed-header="true" />
		</div>
	</div>
</template>

<script>
import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

// import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { VueGoodTable } from 'vue-good-table'
import 'vue-good-table/dist/vue-good-table.css'

// import moment from '@nextcloud/moment'

export default {
	name: 'MainContent',

	components: {
		TrackmaniaIcon,
		VueGoodTable,
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
			columns: [
				{
					label: 'Map name',
					type: 'text',
					field: 'mapInfo.name',
					filterOptions: {
						// styleClass: 'class1', // class to be added to the parent th element
						enabled: true, // enable filter for this column
						placeholder: 'Filter names', // placeholder for filter input
						// filterValue: '', // initial populated value for this filter
						filterDropdownItems: [],
						filterFn: this.stringFilter,
						trigger: 'enter',
					},
				},
				{
					label: 'Position',
					type: 'number',
					field: 'recordPosition.zones.World.ranking.position',
					filterOptions: {
						// styleClass: 'class1', // class to be added to the parent th element
						enabled: true, // enable filter for this column
						placeholder: 'Example: "<= 100" for top 100', // placeholder for filter input
						// filterValue: '', // initial populated value for this filter
						filterFn: this.numberFilter,
						trigger: 'enter',
					},
				},
				{
					label: 'PB',
					type: 'number',
					field: 'record.recordScore.time',
					formatFn: this.formatTime,
				},
			],
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
		stringFilter(data, filterString) {
			return data.toUpperCase().includes(filterString.toUpperCase())
		},
		numberFilter(data, filterString) {
			if (filterString.startsWith('<=')) {
				return data <= parseInt(filterString.replace('<=', ''))
			// } else if () {

			}
		},
		formatTime(value) {
			const milli = value % 1000
			const totalSeconds = Math.floor(value / 1000)
			const hours = Math.floor(totalSeconds / 3600)
			const minutes = Math.floor(totalSeconds / 60) - (hours * 60)
			const seconds = totalSeconds - (hours * 3600) - (minutes * 60)
			return String(hours).padStart(2, '0')
				+ ':' + String(minutes).padStart(2, '0')
				+ ':' + String(seconds).padStart(2, '0')
				+ '.' + milli + ' (' + value + ')'
		},
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
