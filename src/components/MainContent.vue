<template>
	<div id="trackmania_main" class="section">
		<h2>
			<TrackmaniaIcon class="icon" />
			<span>{{ t('integration_trackmania', 'Trackmania integration') }}</span>
		</h2>
		<br>
		<div class="summary">
			<div class="summary__medals">
				<p>🟢 {{ t('integration_trackmania', '{nb} Author medals', { nb: medalCount.author }) }}</p>
				<p>🟡 {{ t('integration_trackmania', '{nb} Gold medals', { nb: medalCount.gold }) }}</p>
				<p>🔵 {{ t('integration_trackmania', '{nb} Silver medals', { nb: medalCount.silver }) }}</p>
				<p>🟤 {{ t('integration_trackmania', '{nb} Bronze medals', { nb: medalCount.bronze }) }}</p>
			</div>
			<div class="summary__top">
				<p>{{ t('integration_trackmania', '{nb} records in top 1', { nb: topCount[1] }) }}</p>
				<p>{{ t('integration_trackmania', '{nb} records in top 10', { nb: topCount[10] }) }}</p>
				<p>{{ t('integration_trackmania', '{nb} records in top 100', { nb: topCount[100] }) }}</p>
				<p>{{ t('integration_trackmania', '{nb} records in top 1000', { nb: topCount[1000] }) }}</p>
			</div>
		</div>
		<div class="checkColumns">
			<NcCheckboxRadioSwitch
				:checked.sync="showLineNumberColumn"
				class="checkColumn">
				{{ t('integration_trackmania', 'Line numbers') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked.sync="showDatesColumn"
				class="checkColumn">
				{{ t('integration_trackmania', 'Date') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked.sync="showMedalsColumn"
				class="checkColumn">
				{{ t('integration_trackmania', 'Medals') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-for="zn in zoneNames"
				:key="zn"
				:checked="zoneColumnsEnabled[zn] ?? true"
				class="checkColumn"
				@update:checked="onZoneCheck(zn, $event)">
				{{ t('integration_trackmania', 'Position in {zone}', { zone: zn }) }}
			</NcCheckboxRadioSwitch>
		</div>
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
				<span v-else-if="props.column.field === 'record.medal'"
					:title="getMedalTime(props)">
					{{ props.formattedRow[props.column.field] }}
				</span>
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import { VueGoodTable } from 'vue-good-table'
import 'vue-good-table/dist/vue-good-table.css'

import moment from '@nextcloud/moment'
import { dig } from '../utils.js'
import {
	Time,
	TextFormatter,
} from 'tm-essentials'
import { htmlify } from 'tm-text'

export default {
	name: 'MainContent',

	components: {
		TrackmaniaIcon,
		VueGoodTable,
		// NcButton,
		NcCheckboxRadioSwitch,
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
			showLineNumberColumn: true,
			showDatesColumn: true,
			showMedalsColumn: true,
			zoneColumnsEnabled: {},
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
				const worldPosition = pb.recordPosition.zones.World
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
		columns() {
			const columns = []
			if (this.showLineNumberColumn) {
				columns.push({
					label: '#',
					type: 'number',
					field: '#',
					sortable: false,
				})
			}
			columns.push(...[
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
						filterFn: this.mapNameFilter,
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
			])
			if (this.showDatesColumn) {
				columns.push({
					label: t('integration_trackmania', 'Date'),
					type: 'number',
					field: 'record.unix_timestamp',
					formatFn: this.formatTimestamp,
				})
			}
			if (this.showMedalsColumn) {
				columns.push({
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
				})
			}
			columns.push(
				...this.zoneNames.filter(zn => this.zoneColumnsEnabled[zn] ?? true).map(zn => {
					return {
						label: t('integration_trackmania', '# in {zn}', { zn }),
						type: 'number',
						field: `recordPosition.zones.${zn}`,
						filterOptions: {
							enabled: true, // enable filter for this column
							placeholder: t('integration_trackmania', '"{example}" for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false }),
							filterFn: this.numberFilter,
							trigger: 'enter',
						},
					}
				}),
			)
			return columns
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
		getMedalTime(props) {
			if (props.row.record.medal === 4) {
				return t('integration_trackmania', 'Author time is {t}', { t: this.formatTime(props.row.mapInfo.authorTime) })
			} else if (props.row.record.medal === 3) {
				return t('integration_trackmania', 'Gold time is {t}', { t: this.formatTime(props.row.mapInfo.goldTime) })
			} else if (props.row.record.medal === 2) {
				return t('integration_trackmania', 'Silver time is {t}', { t: this.formatTime(props.row.mapInfo.silverTime) })
			} else if (props.row.record.medal === 1) {
				return t('integration_trackmania', 'Bronze time is {t}', { t: this.formatTime(props.row.mapInfo.bronzeTime) })
			}
			return ''
		},
		onZoneCheck(zn, checked) {
			this.$set(this.zoneColumnsEnabled, zn, checked)
		},
		stringFilter(data, filterString) {
			return data.toUpperCase().includes(filterString.toUpperCase())
		},
		mapNameFilter(data, filterString) {
			console.debug('aaaaaaaaaaaaa FILTER MAP NAME', data)
			return TextFormatter.deformat(data).includes(filterString.toUpperCase())
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
		formatTimestamp(value) {
			return moment.unix(value).format('LLL')
		},
		formatMapName(value) {
			return htmlify(value)
		},
		formatMedals(value) {
			return value === 4
				? '🟢 ' + t('integration_trackmania', 'Author')
				: value === 3
					? '🟡 ' + t('integration_trackmania', ' Gold')
					: value === 2
						? '🔵 ' + t('integration_trackmania', ' Silver')
						: value === 1
							? '🟤 ' + t('integration_trackmania', ' Bronze')
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

	.summary {
		display: flex;
		align-items: center;
		gap: 44px;
		margin-bottom: 24px;
	}

	:deep(.mapNameColumn) {
		background: #B0B0B0;
		font-weight: bold;
	}

	.checkColumns {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}
}
</style>
