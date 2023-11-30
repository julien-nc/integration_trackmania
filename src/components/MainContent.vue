<template>
	<div id="trackmania_main" class="section">
		<h2>
			<TrackmaniaIcon class="icon" />
			<span>{{ t('integration_trackmania', 'Trackmania integration') }}</span>
		</h2>
		<br>
		<p>🟢 {{ t('integration_trackmania', '{nb} Author medals', { nb: medalCount.author }) }}</p>
		<p>🟡 {{ t('integration_trackmania', '{nb} Gold medals', { nb: medalCount.gold }) }}</p>
		<p>🔵 {{ t('integration_trackmania', '{nb} Silver medals', { nb: medalCount.silver }) }}</p>
		<p>🟤 {{ t('integration_trackmania', '{nb} Bronze medals', { nb: medalCount.bronze }) }}</p>
		<br>
		<p>{{ t('integration_trackmania', '{nb} in top 1', { nb: topCount[1] }) }}</p>
		<p>{{ t('integration_trackmania', '{nb} in top 10', { nb: topCount[10] }) }}</p>
		<p>{{ t('integration_trackmania', '{nb} in top 100', { nb: topCount[100] }) }}</p>
		<p>{{ t('integration_trackmania', '{nb} in top 1000', { nb: topCount[1000] }) }}</p>
		<br>
		<span>
			{{ t('integration_trackmania', '{nb} rows', { nb: rowCount }) }}
		</span>
		<VueGoodTable
			:columns="columns"
			:rows="pbs"
			:fixed-header="true"
			@on-column-filter="onColumnFilter">
			<template slot="table-row" slot-scope="props">
				<span v-if="props.column.field === '#'">
					{{ props.index + 1 }}
				</span>
				<span v-else-if="props.column.field === 'mapInfo.name'" v-html="props.formattedRow[props.column.field]" />
				<span v-else>
					{{ props.formattedRow[props.column.field] }}
				</span>
			</template>
		</VueGoodTable>
	</div>
</template>

<script>
import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

// import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { VueGoodTable } from 'vue-good-table'
import 'vue-good-table/dist/vue-good-table.css'

// import moment from '@nextcloud/moment'
import { dig } from '../utils.js'
import {
	Time,
	// TextFormatter,
} from 'tm-essentials'
import { htmlify } from 'tm-text'

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
		zoneNames: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			rowCount: this.pbs.length,
			columns: [
				{
					label: '#',
					type: 'number',
					field: '#',
					sortable: false,
				},
				{
					label: t('integration_trackmania', 'Map name'),
					type: 'text',
					field: 'mapInfo.name',
					formatFn: this.formatMapName,
					tdClass: 'mapNameColumn',
					filterOptions: {
						customFilter: true,
						// styleClass: 'plop',
						enabled: true, // enable filter for this column
						placeholder: t('integration_trackmania', 'Filter names'), // placeholder for filter input
						// filterValue: '', // initial populated value for this filter
						filterDropdownItems: [],
						filterFn: this.stringFilter,
						trigger: 'enter',
					},
				},
				{
					label: t('integration_trackmania', 'PB'),
					type: 'number',
					field: 'record.recordScore.time',
					formatFn: this.formatTime,
					filterOptions: {
						enabled: true, // enable filter for this column
						placeholder: t('integration_trackmania', '"{example}" for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false }), // placeholder for filter input
						filterFn: this.numberFilter,
						trigger: 'enter',
					},
				},
				{
					label: t('integration_trackmania', 'Medals'),
					type: 'number',
					field: 'record.medal',
					formatFn: this.formatMedals,
					filterOptions: {
						// styleClass: 'class1', // class to be added to the parent th element
						enabled: true, // enable filter for this column
						// filterValue: '', // initial populated value for this filter
						placeholder: t('integration_trackmania', 'Any medal'), // placeholder for filter input
						filterDropdownItems: [
							{ value: 0, text: 'None' },
							{ value: 1, text: '🟤 ' + t('integration_trackmania', 'Bronze') },
							{ value: 2, text: '🔵 ' + t('integration_trackmania', 'Silver') },
							{ value: 3, text: '🟡 ' + t('integration_trackmania', 'Gold') },
							{ value: 4, text: '🟢 ' + t('integration_trackmania', 'Author') },
							{ value: '>= 1', text: '🔵 ' + t('integration_trackmania', 'At least bronze') },
							{ value: '>= 2', text: '🔵 ' + t('integration_trackmania', 'At least silver') },
							{ value: '>= 3', text: '🟡 ' + t('integration_trackmania', 'At least gold') },
						],
						filterFn: this.numberFilter,
					},
				},
				...this.zoneNames.map(zn => {
					return {
						label: t('integration_trackmania', '# in {zn}', { zn }),
						type: 'number',
						field: `recordPosition.zones.${zn}.ranking.position`,
						filterOptions: {
							enabled: true, // enable filter for this column
							placeholder: t('integration_trackmania', '"{example}" for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false }),
							filterFn: this.numberFilter,
							trigger: 'enter',
						},
					}
				}),
				/*
				{
					label: t('integration_trackmania', 'Position'),
					type: 'number',
					field: 'recordPosition.zones.World.ranking.position',
					filterOptions: {
						// styleClass: 'class1', // class to be added to the parent th element
						enabled: true, // enable filter for this column
						placeholder: t('integration_trackmania', '"{example}" for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false }), // placeholder for filter input
						// filterValue: '', // initial populated value for this filter
						filterFn: this.numberFilter,
						trigger: 'enter',
					},
				},
				*/
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
			} else if (filterString.startsWith('<')) {
				return data < parseInt(filterString.replace('<', ''))
			} else if (filterString.startsWith('>=')) {
				return data >= parseInt(filterString.replace('>=', ''))
			} else if (filterString.startsWith('>')) {
				return data > parseInt(filterString.replace('>', ''))
			} else {
				return data === parseInt(filterString)
			}
		},
		formatTime(value) {
			/*
			const milli = value % 1000
			const totalSeconds = Math.floor(value / 1000)
			const hours = Math.floor(totalSeconds / 3600)
			const minutes = Math.floor(totalSeconds / 60) - (hours * 60)
			const seconds = totalSeconds - (hours * 3600) - (minutes * 60)
			return String(hours).padStart(2, '0')
				+ ':' + String(minutes).padStart(2, '0')
				+ ':' + String(seconds).padStart(2, '0')
				+ '.' + milli + ' (' + value + ')'
			*/
			return Time.fromMilliseconds(value).toTmString() + ' (' + value + ')'
		},
		formatMapName(value) {
			// return TextFormatter.deformat(value)
			return htmlify(value)
		},
		formatMedals(value) {
			return value === 4
				? '🟢 Author'
				: value === 3
					? '🟡 Gold'
					: value === 2
						? '🔵 Silver'
						: value === 1
							? '🟤 Bronze'
							: 'None'
		},
		// recompute the filtered list to get the total number of rows...because good table no good
		onColumnFilter(params) {
			let myFiltered = this.pbs
			Object.keys(params.columnFilters).forEach(field => {
				const filterString = params.columnFilters[field]
				if (filterString === '') {
					return
				}
				const columnConfig = this.columns.find(c => c.field === field)
				myFiltered = myFiltered.filter(pb => {
					const data = dig(pb, field)
					return columnConfig.filterOptions.filterFn(data, filterString)
				})
			})
			this.rowCount = myFiltered.length
			console.debug('my filtered row list', myFiltered)
		},
	},
}
</script>

<style scoped lang="scss">
#trackmania_main {
	overflow-x: scroll;

	>h2 {
		display: flex;
		.icon {
			margin-right: 8px;
		}
	}

	#trackmania-content {
		//margin-left: 40px;
	}

	:deep(.mapNameColumn) {
		background: #B0B0B0;
		font-weight: bold;
	}
}
</style>
