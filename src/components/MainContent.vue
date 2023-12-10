<template>
	<div id="trackmania_main" class="section">
		<h2>
			<TrackmaniaIcon class="icon" />
			<span>{{ t('integration_trackmania', 'Trackmania integration') }}</span>
		</h2>
		<div class="header">
			<NcButton @click="$emit('reload')">
				<template #icon>
					<ReloadIcon />
				</template>
				{{ t('integration_trackmania', 'Reload data') }}
			</NcButton>
			<NcButton @click="$emit('disconnect')">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('integration_trackmania', 'Disconnect') }}
			</NcButton>
		</div>
		<div class="summary">
			<div class="summary__medals">
				<h3>{{ t('integration_trackmania', 'Medals') }}</h3>
				<p>🟢 {{ t('integration_trackmania', '{nb} Author', { nb: medalCount.author }) }}</p>
				<p>🟡 {{ t('integration_trackmania', '{nb} Gold', { nb: medalCount.gold }) }}</p>
				<p>🔵 {{ t('integration_trackmania', '{nb} Silver', { nb: medalCount.silver }) }}</p>
				<p>🟤 {{ t('integration_trackmania', '{nb} Bronze', { nb: medalCount.bronze }) }}</p>
				<p>{{ t('integration_trackmania', '{nb} tracks without any medal', { nb: medalCount.none }) }}</p>
			</div>
			<div v-for="zn in enabledZones"
				:key="zn"
				class="summary__top">
				<h3>{{ zn }}</h3>
				<p>{{ t('integration_trackmania', '{nb} records in top 1', { nb: topCount[zn][1] }) }}</p>
				<p>{{ t('integration_trackmania', '{nb} records in top 10', { nb: topCount[zn][10] }) }}</p>
				<p>{{ t('integration_trackmania', '{nb} records in top 100', { nb: topCount[zn][100] }) }}</p>
				<p>{{ t('integration_trackmania', '{nb} records in top 1000', { nb: topCount[zn][1000] }) }}</p>
			</div>
		</div>
		<!--div class="charts">
			<NbRecordsPerPosition :pbs="filteredPbs" />
		</div-->
		<div class="checkColumns">
			<NcCheckboxRadioSwitch
				:checked="config.show_column_line_number ?? true"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_line_number', $event)">
				{{ t('integration_trackmania', 'Line numbers') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="config.show_column_date ?? true"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_date', $event)">
				{{ t('integration_trackmania', 'Date') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="config.show_column_favorite ?? true"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_favorite', $event)">
				{{ t('integration_trackmania', 'Favorite') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="config.show_column_medals ?? true"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_medals', $event)">
				{{ t('integration_trackmania', 'Medals') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-for="zn in zoneNames"
				:key="zn"
				:checked="config['show_column_zone_' + zn] ?? zn === 'World'"
				class="checkColumn"
				@update:checked="onZoneCheck(zn, $event)">
				{{ t('integration_trackmania', 'Position in {zone}', { zone: zn }) }}
			</NcCheckboxRadioSwitch>
		</div>
		<br>
		<MapDetailModal v-if="detailPb"
			:pb="detailPb"
			@close="detailPb = null" />
		<div class="table-header">
			<span>
				{{ t('integration_trackmania', '{nb} rows', { nb: rowCount }) }}
			</span>
			<NcButton @click="clearFilters">
				<template #icon>
					<FilterRemoveIcon />
				</template>
				{{ t('integration_trackmania', 'Clear table filters') }}
			</NcButton>
		</div>
		<VueGoodTable
			:columns="columns"
			:rows="filteredPbs"
			:fixed-header="true"
			:sort-options="tableSortOptions"
			@on-cell-click="onCellClick"
			@on-sort-change="onSortOrderChange">
			<template slot="table-row" slot-scope="props">
				<span v-if="props.column.field === '#'">
					{{ props.index + 1 }}
				</span>
				<span v-else-if="props.column.field === 'mapInfo.cleanName'"
					v-html="props.row.mapInfo.htmlName" />
				<span v-else-if="props.column.field === 'mapInfo.favorite'">
					{{ props.row.mapInfo.formattedFavorite }}
				</span>
				<span v-else-if="props.column.field === 'record.medal'"
					:title="getFormattedBestMedal(props.row)">
					{{ props.row.record.formattedMedal }}
				</span>
				<span v-else-if="props.column.field === 'record.recordScore.time'">
					{{ props.row.record.recordScore.formattedTime }}
				</span>
				<span v-else-if="props.column.field === 'record.unix_timestamp'">
					{{ props.row.record.formattedDate }}
				</span>
				<span v-else>
					{{ props.formattedRow[props.column.field] }}
				</span>
			</template>
			<template slot="column-filter" slot-scope="props">
				<input
					v-if="props.column.field === 'mapInfo.cleanName'"
					:value="mapNameFilter"
					type="text"
					class="text-input-filter"
					@keyup.enter="onMapNameFilterChange">
				<input
					v-else-if="props.column.field === 'record.recordScore.time'"
					:value="timeFilter"
					type="text"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onTimeFilterChange">
				<select
					v-else-if="props.column.field === 'mapInfo.favorite'"
					v-model="favoriteFilter"
					class="select-filter"
					@input="onFavoriteFilterChange">
					<option value="">
						{{ t('integration_trackmania', 'All') }}
					</option>
					<option value="false">
						{{ '☆ ' + t('integration_trackmania', 'Not favorite') }}
					</option>
					<option value="true">
						{{ '⭐ ' + t('integration_trackmania', 'Favorite') }}
					</option>
				</select>
				<div v-else-if="props.column.field === 'record.unix_timestamp'"
					class="date-filters">
					<input
						v-model="dateMinFilter"
						type="date"
						@input="onDateChange">
					{{ '<= ' + t('integration_trackmania', 'Date') + ' <' }}
					<input
						v-model="dateMaxFilter"
						type="date"
						@input="onDateChange">
				</div>
				<select
					v-else-if="props.column.field === 'record.medal'"
					v-model="medalFilter"
					class="select-filter"
					@input="onMedalFilterChange">
					<option value="">
						{{ t('integration_trackmania', 'No filter') }}
					</option>
					<option value="0">
						{{ t('integration_trackmania', 'None') }}
					</option>
					<option value="1">
						{{ '🟤 ' + t('integration_trackmania', 'Bronze') }}
					</option>
					<option value="2">
						{{ '🔵 ' + t('integration_trackmania', 'Silver') }}
					</option>
					<option value="3">
						{{ '🟡 ' + t('integration_trackmania', 'Gold') }}
					</option>
					<option value="4">
						{{ '🟢 ' + t('integration_trackmania', 'Author') }}
					</option>
					<option value=">= 1">
						{{ '🟤 ' + t('integration_trackmania', 'At least bronze') }}
					</option>
					<option value=">= 2">
						{{ '🔵 ' + t('integration_trackmania', 'At least silver') }}
					</option>
					<option value=">= 3">
						{{ '🟡 ' + t('integration_trackmania', 'At least gold') }}
					</option>
				</select>
				<input
					v-if="props.column.field.startsWith('recordPosition.zones.')"
					:value="zonePositionFilters[props.column.field] ?? ''"
					type="text"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onZonePositionFilterChange(props.column.field, $event.target.value)">
			</template>
		</VueGoodTable>
	</div>
</template>

<script>
import ReloadIcon from 'vue-material-design-icons/Reload.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import FilterRemoveIcon from 'vue-material-design-icons/FilterRemove.vue'

import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import MapDetailModal from './MapDetailModal.vue'
// import NbRecordsPerPosition from './charts/NbRecordsPerPosition.vue'

import { VueGoodTable } from 'vue-good-table'
import 'vue-good-table/dist/vue-good-table.css'

import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

const configState = loadState('integration_trackmania', 'table-config')

export default {
	name: 'MainContent',

	components: {
		// NbRecordsPerPosition,
		MapDetailModal,
		TrackmaniaIcon,
		VueGoodTable,
		NcButton,
		NcCheckboxRadioSwitch,
		ReloadIcon,
		CloseIcon,
		FilterRemoveIcon,
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

	// TODO save/restore sort orders
	data() {
		return {
			tableSortOptions: {
				multipleColumns: true,
			},
			config: configState,
			// filter values
			dateMinFilter: configState.filter_dateMin ? moment.unix(configState.filter_dateMin).format('YYYY-MM-DD') : '',
			dateMaxFilter: configState.filter_dateMax ? moment.unix(configState.filter_dateMax).format('YYYY-MM-DD') : '',
			mapNameFilter: configState.filter_mapName ?? '',
			timeFilter: configState.filter_time ?? '',
			favoriteFilter: configState.filter_favorite ?? '',
			medalFilter: configState.filter_medal ?? '',
			zonePositionFilters: {},
			detailPb: null,
		}
	},

	computed: {
		// refilter the pbs with table filters + external filters to count the rows
		filteredPbs() {
			let myFiltered = this.pbs
			if (this.dateMinTimestamp) {
				myFiltered = myFiltered.filter(pb => pb.record.unix_timestamp > this.dateMinTimestamp)
			}
			if (this.dateMaxTimestamp) {
				myFiltered = myFiltered.filter(pb => pb.record.unix_timestamp < this.dateMaxTimestamp)
			}
			if (this.mapNameFilter) {
				myFiltered = myFiltered.filter(pb => this.filterString(pb.mapInfo.cleanName, this.mapNameFilter))
			}
			if (this.timeFilter) {
				myFiltered = myFiltered.filter(pb => this.filterNumber(pb.record.recordScore.time, this.timeFilter))
			}
			if (this.favoriteFilter) {
				myFiltered = myFiltered.filter(pb => this.filterFavorite(pb.mapInfo.favorite, this.favoriteFilter))
			}
			if (this.medalFilter) {
				myFiltered = myFiltered.filter(pb => this.filterNumber(pb.record.medal, this.medalFilter))
			}
			this.zoneNames.forEach(zn => {
				const zoneFilterKey = `recordPosition.zones.${zn}`
				if (this.zonePositionFilters[zoneFilterKey]) {
					myFiltered = myFiltered.filter(pb => this.filterNumber(pb.recordPosition.zones[zn], this.zonePositionFilters[zoneFilterKey]))
				}
			})
			console.debug('my filtered row list', myFiltered)
			return myFiltered
		},
		rowCount() {
			return this.filteredPbs.length
		},
		enabledZones() {
			return this.zoneNames.filter(zn => this.config['show_column_zone_' + zn] ?? false)
		},
		topCount() {
			const tops = {}
			this.zoneNames.forEach(zn => {
				const zoneTops = {
					1: 0,
					10: 0,
					100: 0,
					1000: 0,
				}
				this.filteredPbs.forEach(pb => {
					const position = pb.recordPosition.zones[zn]
					if (position === 1) {
						zoneTops[1]++
						zoneTops[10]++
						zoneTops[100]++
						zoneTops[1000]++
					} else if (position <= 10) {
						zoneTops[10]++
						zoneTops[100]++
						zoneTops[1000]++
					} else if (position <= 100) {
						zoneTops[100]++
						zoneTops[1000]++
					} else if (position <= 1000) {
						zoneTops[1000]++
					}
				})
				tops[zn] = zoneTops
			})
			return tops
		},
		medalCount() {
			const medals = {
				author: 0,
				gold: 0,
				silver: 0,
				bronze: 0,
				none: 0,
			}
			this.filteredPbs.forEach(pb => {
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
				} else {
					medals.none++
				}
			})
			return medals
		},
		columns() {
			const columns = []
			if (this.config.show_column_line_number ?? true) {
				columns.push({
					label: '#',
					type: 'number',
					field: '#',
					sortable: false,
					// otherwise the filter th is not rendered
					filterOptions: {
						enabled: true,
					},
				})
			}
			if (this.config.show_column_favorite ?? true) {
				columns.push({
					label: t('integration_trackmania', 'Favorite'),
					type: 'boolean',
					field: 'mapInfo.favorite',
					tdClass: 'mapFavoriteColumn',
					// otherwise the filter th is not rendered
					filterOptions: {
						enabled: true,
					},
				})
			}
			columns.push(...[
				{
					label: t('integration_trackmania', 'Map name'),
					type: 'text',
					field: 'mapInfo.cleanName',
					tdClass: 'mapNameColumn',
				},
				{
					label: t('integration_trackmania', 'PB'),
					type: 'number',
					field: 'record.recordScore.time',
				},
			])
			if (this.config.show_column_date ?? true) {
				columns.push({
					label: t('integration_trackmania', 'Date'),
					type: 'number',
					field: 'record.unix_timestamp',
				})
			}
			if (this.config.show_column_medals ?? true) {
				columns.push({
					label: t('integration_trackmania', 'Medals'),
					type: 'number',
					field: 'record.medal',
				})
			}
			columns.push(
				...this.zoneNames.filter(zn => this.config['show_column_zone_' + zn] ?? zn === 'World').map(zn => {
					return {
						label: t('integration_trackmania', '# in {zn}', { zn }),
						type: 'number',
						field: `recordPosition.zones.${zn}`,
					}
				}),
			)
			return columns
		},
		dateMinTimestamp() {
			if (this.dateMinFilter) {
				return moment(this.dateMinFilter).unix()
			}
			return ''
		},
		dateMaxTimestamp() {
			if (this.dateMaxFilter) {
				return moment(this.dateMaxFilter).unix()
			}
			return ''
		},
	},

	watch: {
	},

	beforeMount() {
		// initialize filter for each zone position
		Object.keys(this.config).forEach(configKey => {
			if (configKey.startsWith('filter_position_zone_')) {
				const zn = configKey.replace('filter_position_zone_', '')
				this.$set(this.zonePositionFilters, 'recordPosition.zones.' + zn, this.config[configKey])
			}
		})
		// initialize sort order
		if (this.config.sort_columns) {
			const columns = this.config.sort_columns.split(',')
			const orders = this.config.sort_orders.split(',')
			if (columns.length === orders.length) {
				const fields = columns.map(c => {
					if (c.startsWith('position_')) {
						return c.replace('position_', 'recordPosition.zones.')
					} else if (c === 'date') {
						return 'record.unix_timestamp'
					} else if (c === 'name') {
						return 'mapInfo.cleanName'
					} else if (c === 'time') {
						return 'record.recordScore.time'
					} else if (c === 'medal') {
						return 'record.medal'
					}
					return ''
				})
				this.$set(this.tableSortOptions, 'initialSortBy', [])
				for (let i = 0; i < columns.length; i++) {
					this.tableSortOptions.initialSortBy.push({
						field: fields[i],
						type: orders[i],
					})
				}
			}
		}
	},

	mounted() {
		console.debug('aaaaaaaaaaaaa pbs', this.pbs)
		console.debug('aaaaaaaaaaaaa tops', this.topCount)
		console.debug('aaaaaaaaaaaaa medals', this.medalCount)
		console.debug('aaaaaaaaaaaaa config', configState)
	},

	methods: {
		getFormattedBestMedal(pb) {
			if (pb.record.medal === 4) {
				return pb.mapInfo.formattedAuthorTime
			} else if (pb.record.medal === 3) {
				return pb.mapInfo.formattedGoldTime
			} else if (pb.record.medal === 2) {
				return pb.mapInfo.formattedSilverTime
			} else if (pb.record.medal === 1) {
				return pb.mapInfo.formattedBronzeTime
			}
			return ''
		},
		onZoneCheck(zn, checked) {
			this.$set(this.config, 'show_column_zone_' + zn, checked)
			this.saveOptions({ ['show_column_zone_' + zn]: checked ? '1' : '0' })
		},
		onColumnCheck(key, checked) {
			this.$set(this.config, key, checked)
			this.saveOptions({ [key]: checked ? '1' : '0' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_trackmania/config')
			axios.put(url, req).then((response) => {
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to save options')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
		},
		filterString(data, filterString) {
			return data.toUpperCase().includes(filterString.toUpperCase())
		},
		filterNumber(data, filterString) {
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
		filterFavorite(data, filterValue) {
			return filterValue === 'false'
				? data === false
				: data === true
		},
		onDateChange() {
			this.saveOptions({
				filter_dateMin: this.dateMinTimestamp,
				filter_dateMax: this.dateMaxTimestamp,
			})
		},
		onMapNameFilterChange(e) {
			this.mapNameFilter = e.target.value
			this.saveOptions({
				filter_mapName: this.mapNameFilter,
			})
		},
		onTimeFilterChange(e) {
			this.timeFilter = e.target.value
			this.saveOptions({
				filter_time: this.timeFilter,
			})
		},
		onFavoriteFilterChange(e) {
			this.saveOptions({
				filter_favorite: e.target.value,
			})
		},
		onMedalFilterChange(e) {
			this.saveOptions({
				filter_medal: e.target.value,
			})
		},
		onZonePositionFilterChange(field, value) {
			const zn = field.replace('recordPosition.zones.', '')
			this.$set(this.zonePositionFilters, field, value)
			this.saveOptions({
				['filter_position_zone_' + zn]: value,
			})
		},
		clearFilters() {
			const values = {
				filter_medal: '',
				filter_favorite: '',
				filter_time: '',
				filter_mapName: '',
				filter_dateMin: '',
				filter_dateMax: '',
			}
			this.zoneNames.forEach(zn => {
				values['filter_position_zone_' + zn] = ''
				this.$set(this.zonePositionFilters, 'recordPosition.zones.' + zn, '')
			})
			this.saveOptions(values)
			this.medalFilter = ''
			this.favoriteFilter = ''
			this.timeFilter = ''
			this.mapNameFilter = ''
			this.dateMinFilter = ''
			this.dateMaxFilter = ''
		},
		onCellClick(params) {
			if (params.column.field === 'mapInfo.cleanName') {
				this.detailPb = params.row
			} else if (params.column.field === 'mapInfo.favorite') {
				emit('toggle-favorite', params.row)
			}
		},
		onSortOrderChange(params) {
			const columns = params.map(p => {
				if (p.field.startsWith('recordPosition.zones.')) {
					const zn = p.field.replace('recordPosition.zones.', '')
					return 'position_' + zn
				} else if (p.field === 'record.unix_timestamp') {
					return 'date'
				} else if (p.field === 'mapInfo.cleanName') {
					return 'name'
				} else if (p.field === 'record.recordScore.time') {
					return 'time'
				} else if (p.field === 'record.medal') {
					return 'medal'
				}
				return ''
			})
			const orders = params.map(p => p.type)
			this.saveOptions({
				sort_columns: columns.join(','),
				sort_orders: orders.join(','),
			})
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

	.header {
		margin-bottom: 24px;
		display: flex;
		gap: 8px;
	}

	.table-header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 12px;
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
		cursor: pointer;
		&:hover {
			background: #909090;
		}
		* {
			cursor: pointer !important;
		}
	}

	:deep(.mapFavoriteColumn) {
		text-align: center;
		cursor: pointer;
		* {
			cursor: pointer !important;
		}
	}

	.date-filters {
		display: flex;
		align-items: center;
		gap: 4px;
		input {
			flex-grow: 1;
		}
	}

	.select-filter,
	.text-input-filter {
		width: 100%;
	}

	.checkColumns {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}
}
</style>
