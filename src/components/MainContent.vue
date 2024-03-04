<template>
	<div id="trackmania_main">
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
			<NcButton @click="$emit('reload', filteredPbs.map(pb => pb.mapInfo.mapId))">
				<template #icon>
					<ReloadIcon />
				</template>
				{{ t('integration_trackmania', 'Reload data for current filtered record list') }}
			</NcButton>
			<NcButton @click="$emit('disconnect')">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('integration_trackmania', 'Disconnect') }}
			</NcButton>
		</div>
		<slot name="extra" />
		<div v-if="configState.other_account_id">
			<p style="color: var(--color-success);">
				👍 {{ t('integration_trackmania', '{nb} records better than other account', { nb: betterThanOtherCount }) }}
			</p>
			<p style="color: var(--color-error);">
				👎 {{ t('integration_trackmania', '{nb} records worse than other account', { nb: worseThanOtherCount }) }}
			</p>
			<p v-if="equalThanOtherCount > 0" style="color: var(--color-warning);">
				👌 {{ t('integration_trackmania', '{nb} records equal than other account', { nb: equalThanOtherCount }) }}
			</p>
		</div>
		<div class="summary">
			<div class="summary__medals">
				<h3>{{ t('integration_trackmania', 'Medals') }}</h3>
				<p><img :src="authorMedalImageUrl"> {{ t('integration_trackmania', '{nb} Author', { nb: medalCount.author }) }}</p>
				<p><img :src="goldMedalImageUrl">  {{ t('integration_trackmania', '{nb} Gold', { nb: medalCount.gold }) }}</p>
				<p><img :src="silverMedalImageUrl">  {{ t('integration_trackmania', '{nb} Silver', { nb: medalCount.silver }) }}</p>
				<p><img :src="bronzeMedalImageUrl">  {{ t('integration_trackmania', '{nb} Bronze', { nb: medalCount.bronze }) }}</p>
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
				:checked="configState.show_column_line_number !== '0'"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_line_number', $event)">
				{{ t('integration_trackmania', 'Line numbers') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="configState.show_column_favorite !== '0'"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_favorite', $event)">
				{{ t('integration_trackmania', 'Favorite') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="configState.show_column_date !== '0'"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_date', $event)">
				{{ t('integration_trackmania', 'Date') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="configState.show_column_medals !== '0'"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_medals', $event)">
				{{ t('integration_trackmania', 'Medals') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-for="zn in zoneNames"
				:key="zn"
				:checked="configState['show_column_zone_' + zn] !== '0'"
				class="checkColumn"
				@update:checked="onZoneCheck(zn, $event)">
				{{ t('integration_trackmania', 'Position in {zone}', { zone: zn }) }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-if="configState.other_account_id"
				:checked="configState.show_column_other_time !== '0'"
				class="checkColumn"
				@update:checked="onColumnCheck('show_column_other_time', $event)">
				{{ t('integration_trackmania', 'Other account information') }}
			</NcCheckboxRadioSwitch>
		</div>
		<br>
		<MapDetailModal v-if="detailPb"
			:pb="detailPb"
			:config-state="configState"
			@close="detailPb = null" />
		<div class="table-header">
			<span>
				{{ t('integration_trackmania', '{nb} track records', { nb: rowCount }) }}
			</span>
			<NcButton v-if="hasFilters" @click="clearFilters">
				<template #icon>
					<FilterRemoveIcon />
				</template>
				{{ t('integration_trackmania', 'Clear table filters') }}
			</NcButton>
		</div>
		<SimpleTable
			:columns="columns"
			:rows="sortedFilteredPbs"
			:sort-options="sortOptions"
			@cell-clicked="onCellClick"
			@header-clicked="onHeaderClick"
			@header-shift-clicked="onHeaderShiftClick">
			<template #cell="{row, column, index}">
				<span v-if="column.field === '#'">
					{{ index + 1 }}
				</span>
				<span v-else-if="column.field === 'mapInfo.cleanName'"
					:title="row.mapInfo.cleanName"
					v-html="row.mapInfo.htmlName" />
				<span v-else-if="column.field === 'mapInfo.favorite'"
					:title="t('integration_trackmania', 'Click to toggle favorite')">
					<NcLoadingIcon v-if="row.mapInfo.favoriteLoading" />
					<StarIcon v-else-if="row.mapInfo.favorite" style="color: var(--color-warning);" />
					<StarOutlineIcon v-else style="color: var(--color-text-maxcontrast);" />
				</span>
				<span v-else-if="column.field === 'record.medal'"
					:title="getFormattedBestMedal(row)"
					class="medal-cell">
					<span>{{ row.record.formattedMedal }}</span>
					<img :src="getMedalImageUrl(row.record.medal)">
				</span>
				<span v-else-if="column.field === 'otherRecord.medal' && row.otherRecord"
					class="medal-cell">
					<span>{{ row.otherRecord.formattedMedal }}</span>
					<img :src="getMedalImageUrl(row.otherRecord.medal)">
				</span>
				<span v-else-if="column.field === 'record.recordScore.time'">
					{{ row.record.recordScore.formattedTime }}
				</span>
				<span v-else-if="column.field === 'otherRecord.time'">
					{{ row.otherRecord?.formattedTime ?? '' }}
				</span>
				<span v-else-if="column.field === 'otherRecord.delta'"
					:style="row.otherRecord?.delta < 0 ? 'color: var(--color-success);' : 'color: var(--color-error);'">
					{{ row.otherRecord?.formattedDelta ?? '' }}
				</span>
				<span v-else-if="column.field === 'record.unix_timestamp'"
					:title="row.record.formattedDateWithZone">
					{{ row.record.formattedDate }}
				</span>
				<span v-else-if="column.field === 'otherRecord.unix_timestamp'"
					:title="row.otherRecord?.formattedDateWithZone ?? undefined">
					{{ row.otherRecord?.formattedDate ?? '' }}
				</span>
				<span v-else>
					{{ getRawCellValue(row, column.field) }}
				</span>
			</template>
			<template #filter="{column}">
				<NcTextField
					v-if="column.field === 'mapInfo.cleanName'"
					:value="mapNameFilter"
					type="text"
					:label="t('integration_trackmania', 'Map name filter')"
					:placeholder="t('integration_trackmania', 'summer 2023')"
					:show-trailing-button="!!mapNameFilter"
					class="text-input-filter"
					@keyup.enter="onMapNameFilterChange"
					@trailing-button-click="setMapNameFilter('')" />
				<NcTextField
					v-else-if="column.field === 'record.recordScore.time'"
					:value="timeFilter"
					type="text"
					:label="t('integration_trackmania', 'Record time filter')"
					:show-trailing-button="!!timeFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onTimeFilterChange"
					@trailing-button-click="setTimeFilter('')" />
				<NcTextField
					v-else-if="column.field === 'otherRecord.time'"
					:value="otherTimeFilter"
					type="text"
					:label="t('integration_trackmania', 'Other time filter')"
					:show-trailing-button="!!otherTimeFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onOtherTimeFilterChange"
					@trailing-button-click="setOtherTimeFilter('')" />
				<NcTextField
					v-else-if="column.field === 'otherRecord.delta'"
					:value="otherDeltaFilter"
					type="text"
					:label="t('integration_trackmania', 'Other delta filter')"
					:show-trailing-button="!!otherDeltaFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onOtherDeltaFilterChange"
					@trailing-button-click="setOtherDeltaFilter('')" />
				<NcSelect
					v-else-if="column.field === 'mapInfo.favorite'"
					:value="selectedFavoriteFilter"
					:options="favoriteFilterOptions"
					:multiple="false"
					:label-outside="true"
					:aria-label-combobox="t('integration_trackmania', 'Favorite filter')"
					:placeholder="t('integration_trackmania', 'No filter')"
					class="select-filter"
					@input="onFavoriteFilterChange">
					<template #option="option">
						<div class="favorite-filter-select__option" style="display: flex; gap: 4px; align-items: center;">
							<StarIcon v-if="option.value === 'true'" style="color: var(--color-warning);" />
							<StarOutlineIcon v-else-if="option.value === 'false'" style="color: var(--color-text-maxcontrast);" />
							{{ option.label }}
						</div>
					</template>
					<template #selected-option="option">
						<div class="favorite-filter-select__option" style="display: flex; gap: 4px; align-items: center;">
							<StarIcon v-if="option.value === 'true'" style="color: var(--color-warning);" />
							<StarOutlineIcon v-else-if="option.value === 'false'" style="color: var(--color-text-maxcontrast);" />
							{{ option.label }}
						</div>
					</template>
				</NcSelect>
				<div v-else-if="column.field === 'record.unix_timestamp'"
					class="date-filters">
					<NcDateTimePicker
						v-model="dateMinFilter"
						class="date-picker"
						type="date"
						:placeholder="t('integration_trackmania', 'Min date')"
						:confirm="false"
						:clearable="true"
						@input="onDateChange" />
					<NcDateTimePicker
						v-model="dateMaxFilter"
						class="date-picker"
						type="date"
						:placeholder="t('integration_trackmania', 'Max date')"
						:confirm="false"
						:clearable="true"
						@input="onDateChange" />
				</div>
				<NcSelect
					v-else-if="column.field === 'record.medal'"
					:value="selectedMedalFilter"
					:options="medalFilterOptions"
					:multiple="true"
					:label-outside="true"
					:aria-label-combobox="t('integration_trackmania', 'Medal filter')"
					:placeholder="t('integration_trackmania', 'No filter')"
					class="medal-filter-select"
					@input="onMedalFilterChange">
					<template #option="option">
						<div class="medal-filter-select__option" style="display: flex; gap: 4px; align-items: center;">
							<img v-if="option.medalImageUrl"
								:src="option.medalImageUrl"
								style="width: 32px;">
							{{ option.label }}
						</div>
					</template>
					<template #selected-option="option">
						<div class="medal-filter-select__option" style="display: flex; gap: 4px; align-items: center;">
							<img v-if="option.medalImageUrl"
								:src="option.medalImageUrl"
								style="width: 32px;">
							{{ option.label }}
						</div>
					</template>
				</NcSelect>
				<NcTextField
					v-if="column.field.startsWith('recordPosition.zones.')"
					:value="zonePositionFilters[column.field] ?? ''"
					type="text"
					:label="t('integration_trackmania', 'Position filter')"
					:show-trailing-button="!!zonePositionFilters[column.field]"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onZonePositionFilterChange(column.field, $event.target.value)"
					@trailing-button-click="onZonePositionFilterChange(column.field, '')" />
			</template>
		</SimpleTable>
	</div>
</template>

<script>
import StarIcon from 'vue-material-design-icons/Star.vue'
import StarOutlineIcon from 'vue-material-design-icons/StarOutline.vue'
import ReloadIcon from 'vue-material-design-icons/Reload.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import FilterRemoveIcon from 'vue-material-design-icons/FilterRemove.vue'

import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcDateTimePicker from '@nextcloud/vue/dist/Components/NcDateTimePicker.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import SimpleTable from './SimpleTable.vue'
import MapDetailModal from './MapDetailModal.vue'
// import NbRecordsPerPosition from './charts/NbRecordsPerPosition.vue'

import moment from '@nextcloud/moment'
import { imagePath } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'
import {
	dig, getFormattedBestMedal, getMedalImageUrl,
	authorMedalImageUrl, goldMedalImageUrl, silverMedalImageUrl, bronzeMedalImageUrl,
} from '../utils.js'

export default {
	name: 'MainContent',

	components: {
		SimpleTable,
		// NbRecordsPerPosition,
		MapDetailModal,
		TrackmaniaIcon,
		NcSelect,
		NcButton,
		NcLoadingIcon,
		NcTextField,
		NcDateTimePicker,
		NcCheckboxRadioSwitch,
		ReloadIcon,
		CloseIcon,
		FilterRemoveIcon,
		StarIcon,
		StarOutlineIcon,
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
		configState: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			authorMedalImageUrl,
			goldMedalImageUrl,
			silverMedalImageUrl,
			bronzeMedalImageUrl,
			favoriteFilterOptions: [
				{
					value: 'true',
					label: t('integration_trackmania', 'Favorite'),
				},
				{
					value: 'false',
					label: t('integration_trackmania', 'Not favorite'),
				},
			],
			medalFilterOptions: [
				{
					id: 0,
					label: t('integration_trackmania', 'No medal'),
				},
				{
					id: 1,
					label: t('integration_trackmania', 'Bronze'),
					medalImageUrl: imagePath('integration_trackmania', 'medal.bronze.custom.png'),
				},
				{
					id: 2,
					label: t('integration_trackmania', 'Silver'),
					medalImageUrl: imagePath('integration_trackmania', 'medal.silver.png'),
				},
				{
					id: 3,
					label: t('integration_trackmania', 'Gold'),
					medalImageUrl: imagePath('integration_trackmania', 'medal.gold.png'),
				},
				{
					id: 4,
					label: t('integration_trackmania', 'Author'),
					medalImageUrl: imagePath('integration_trackmania', 'medal.author.png'),
				},
			],
			tableSortOptions: {
				multipleColumns: true,
			},
			// filter values
			dateMinFilter: this.configState.filter_dateMin ? moment.unix(this.configState.filter_dateMin).toDate() : '',
			dateMaxFilter: this.configState.filter_dateMax ? moment.unix(this.configState.filter_dateMax).toDate() : '',
			mapNameFilter: this.configState.filter_mapName ?? '',
			timeFilter: this.configState.filter_time ?? '',
			otherTimeFilter: this.configState.filter_other_time ?? '',
			otherDeltaFilter: this.configState.filter_other_delta ?? '',
			favoriteFilter: this.configState.filter_favorite ?? '',
			medalFilter: this.configState.filter_medal ? this.configState.filter_medal.split(',').map(v => parseInt(v)) : [],
			zonePositionFilters: {},
			sortOptions: this.initSortOptions(),
			detailPb: null,
		}
	},

	computed: {
		hasFilters() {
			return this.medalFilter.length > 0
				|| this.favoriteFilter
				|| this.timeFilter
				|| this.otherTimeFilter
				|| this.otherDeltaFilter
				|| this.mapNameFilter
				|| this.dateMinFilter
				|| this.dateMaxFilter
		},
		selectedMedalFilter() {
			return this.medalFilter.map(mid => {
				return this.medalFilterOptions.find(option => option.id === mid)
			})
		},
		selectedFavoriteFilter() {
			return this.favoriteFilterOptions.find(option => option.value === this.favoriteFilter)
		},
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
			if (this.otherTimeFilter) {
				myFiltered = myFiltered.filter(pb => this.filterNumber(pb.otherRecord?.time, this.otherTimeFilter))
			}
			if (this.otherDeltaFilter) {
				myFiltered = myFiltered.filter(pb => this.filterNumber(pb.otherRecord?.delta, this.otherDeltaFilter))
			}
			if (this.favoriteFilter) {
				myFiltered = myFiltered.filter(pb => this.filterFavorite(pb.mapInfo.favorite, this.favoriteFilter))
			}
			if (this.medalFilter.length > 0) {
				myFiltered = myFiltered.filter(pb => this.medalFilter.includes(pb.record.medal))
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
		sortedFilteredPbs() {
			if (this.sortOptions.length === 0) {
				return this.filteredPbs
			}
			const columns = this.sortOptions.map(so => this.columns.find(c => c.sortName === so.sortName)).filter(c => !!c)
			if (columns.length > 0) {
				const sortFun = this.getSortFunction(columns, this.sortOptions)
				return this.filteredPbs.slice().sort(sortFun)
			}
			return this.filteredPbs
		},
		rowCount() {
			return this.filteredPbs.length
		},
		enabledZones() {
			return this.zoneNames.filter(zn => this.configState['show_column_zone_' + zn] !== '0')
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
		betterThanOtherCount() {
			return this.filteredPbs.filter(pb => {
				return pb.otherRecord?.time && pb.record.recordScore.time < pb.otherRecord?.time
			}).length
		},
		worseThanOtherCount() {
			return this.filteredPbs.filter(pb => {
				return pb.otherRecord?.time && pb.record.recordScore.time > pb.otherRecord?.time
			}).length
		},
		equalThanOtherCount() {
			return this.filteredPbs.filter(pb => {
				return pb.otherRecord?.time && pb.record.recordScore.time === pb.otherRecord?.time
			}).length
		},
		columns() {
			const columns = []
			if (this.configState.show_column_line_number !== '0') {
				columns.push({
					label: '#',
					type: 'number',
					field: '#',
				})
			}
			if (this.configState.show_column_favorite !== '0') {
				columns.push({
					label: t('integration_trackmania', 'Favorite'),
					type: 'boolean',
					field: 'mapInfo.favorite',
					tdClass: 'mapFavoriteColumn',
					sortName: 'favorite',
				})
			}
			columns.push(...[
				{
					label: t('integration_trackmania', 'Map name'),
					type: 'text',
					field: 'mapInfo.cleanName',
					tdClass: 'mapNameColumn',
					sortName: 'mapName',
				},
				{
					label: t('integration_trackmania', 'PB'),
					type: 'number',
					field: 'record.recordScore.time',
					sortName: 'time',
				},
			])
			if (this.configState.show_column_date !== '0') {
				columns.push({
					label: t('integration_trackmania', 'Date'),
					type: 'number',
					field: 'record.unix_timestamp',
					sortName: 'date',
				})
			}
			if (this.configState.show_column_medals !== '0') {
				columns.push({
					label: t('integration_trackmania', 'Medals'),
					type: 'number',
					field: 'record.medal',
					tdClass: 'mapMedalColumn',
					sortName: 'medal',
				})
			}
			columns.push(
				...this.zoneNames.filter(zn => this.configState['show_column_zone_' + zn] !== '0').map(zn => {
					return {
						label: t('integration_trackmania', '# in {zn}', { zn }),
						type: 'number',
						field: `recordPosition.zones.${zn}`,
						sortName: `position_${zn}`,
					}
				}),
			)
			if (this.configState.other_account_id && this.configState.show_column_other_time !== '0') {
				columns.push({
					label: t('integration_trackmania', 'Other PB'),
					type: 'number',
					field: 'otherRecord.time',
					sortName: 'otherTime',
				})
				columns.push({
					label: t('integration_trackmania', 'Other PB date'),
					type: 'number',
					field: 'otherRecord.unix_timestamp',
					sortName: 'otherDate',
				})
				columns.push({
					label: t('integration_trackmania', 'Delta with other'),
					type: 'number',
					field: 'otherRecord.delta',
					sortName: 'otherDelta',
				})
				columns.push({
					label: t('integration_trackmania', 'Other medal'),
					type: 'number',
					field: 'otherRecord.medal',
					tdClass: 'mapMedalColumn',
					sortName: 'otherMedal',
				})
			}
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
		Object.keys(this.configState).forEach(configKey => {
			if (configKey.startsWith('filter_position_zone_')) {
				const zn = configKey.replace('filter_position_zone_', '')
				this.$set(this.zonePositionFilters, 'recordPosition.zones.' + zn, this.configState[configKey])
			}
		})
	},

	mounted() {
		console.debug('aaaaaaaaaaaaa pbs', this.pbs)
		console.debug('aaaaaaaaaaaaa tops', this.topCount)
		console.debug('aaaaaaaaaaaaa medals', this.medalCount)
		console.debug('aaaaaaaaaaaaa config', this.configState)
	},

	methods: {
		getSortFunction(columns, sortOptions) {
			const firstColumn = columns[0]
			const firstSortOption = sortOptions[0]
			const field = firstColumn.field
			const type = firstColumn.type
			const order = firstSortOption.order
			const nextSortFunction = columns.length > 1
				? this.getSortFunction(columns.slice(1), sortOptions.slice(1))
				: () => 0

			if (type === 'boolean') {
				return order === 'asc'
					? (a, b) => {
						const vA = this.getRawCellValue(a, field)
						const vB = this.getRawCellValue(b, field)
						return vA === vB
							? nextSortFunction(a, b)
							: (vA ? 1 : -1)
					}
					: (a, b) => {
						const vA = this.getRawCellValue(a, field)
						const vB = this.getRawCellValue(b, field)
						return vA === vB
							? nextSortFunction(a, b)
							: (vB ? 1 : -1)
					}
			} else if (type === 'number') {
				return order === 'asc'
					? (a, b) => {
						const vA = this.getRawCellValue(a, field) ?? null
						const vB = this.getRawCellValue(b, field) ?? null
						return vA > vB
							? 1
							: vA < vB
								? -1
								: nextSortFunction(a, b)
					}
					: (a, b) => {
						const vA = this.getRawCellValue(a, field) ?? null
						const vB = this.getRawCellValue(b, field) ?? null
						return vA > vB
							? -1
							: vA < vB
								? 1
								: nextSortFunction(a, b)
					}
			} else if (type === 'text') {
				return order === 'asc'
					? (a, b) => {
						const vA = this.getRawCellValue(a, field)
						const vB = this.getRawCellValue(b, field)
						return vA === vB
							? nextSortFunction(a, b)
							: vA.localeCompare(vB)
					}
					: (a, b) => {
						const vA = this.getRawCellValue(a, field)
						const vB = this.getRawCellValue(b, field)
						return vA === vB
							? nextSortFunction(a, b)
							: vB.localeCompare(vA)
					}
			}
		},
		initSortOptions() {
			if (this.configState.sort_columns && this.configState.sort_orders) {
				const cols = this.configState.sort_columns.split(',')
				const orders = this.configState.sort_orders.split(',')
				if (cols.length === orders.length) {
					const sortOptions = []
					for (let i = 0; i < cols.length; i++) {
						sortOptions.push({
							sortName: cols[i],
							order: orders[i],
						})
					}
					return sortOptions
				}
			}
			return []
		},
		getRawCellValue(row, field) {
			return dig(row, field)
		},
		getMedalImageUrl(medal) {
			return getMedalImageUrl(medal)
		},
		getFormattedBestMedal(pb) {
			return getFormattedBestMedal(pb)
		},
		onZoneCheck(zn, checked) {
			this.saveOptions({ ['show_column_zone_' + zn]: checked ? '1' : '0' })
		},
		onColumnCheck(key, checked) {
			this.saveOptions({ [key]: checked ? '1' : '0' })
		},
		saveOptions(values) {
			emit('save-options', values)
		},
		filterString(data, filterString) {
			return data.toUpperCase().includes(filterString.toUpperCase())
		},
		filterNumber(data, filterString) {
			if (data) {
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
			}
			return false
		},
		filterFavorite(data, filterValue) {
			return filterValue === 'false'
				? data === false
				: data === true
		},
		onDateChange() {
			console.debug('date change')
			this.saveOptions({
				filter_dateMin: this.dateMinTimestamp,
				filter_dateMax: this.dateMaxTimestamp,
			})
		},
		onMapNameFilterChange(e) {
			console.debug('eeeeeee', e)
			this.setMapNameFilter(e.target.value)
		},
		setMapNameFilter(value) {
			this.mapNameFilter = value
			this.saveOptions({
				filter_mapName: this.mapNameFilter,
			})
		},
		onTimeFilterChange(e) {
			this.setTimeFilter(e.target.value)
		},
		setTimeFilter(value) {
			this.timeFilter = value
			this.saveOptions({
				filter_time: this.timeFilter,
			})
		},
		onOtherTimeFilterChange(e) {
			this.setOtherTimeFilter(e.target.value)
		},
		setOtherTimeFilter(value) {
			this.otherTimeFilter = value
			this.saveOptions({
				filter_other_time: this.otherTimeFilter,
			})
		},
		onOtherDeltaFilterChange(e) {
			this.setOtherDeltaFilter(e.target.value)
		},
		setOtherDeltaFilter(value) {
			this.otherDeltaFilter = value
			this.saveOptions({
				filter_other_delta: this.otherDeltaFilter,
			})
		},
		onFavoriteFilterChange(option) {
			if (option === null) {
				this.favoriteFilter = ''
			} else {
				this.favoriteFilter = option.value
			}
			this.saveOptions({
				filter_favorite: this.favoriteFilter,
			})
		},
		onMedalFilterChange(value) {
			if (value === null) {
				this.medalFilter = []
			} else {
				this.medalFilter = value.map(option => parseInt(option.id))
			}
			this.saveOptions({
				filter_medal: this.medalFilter.join(','),
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
			this.medalFilter = []
			this.favoriteFilter = ''
			this.timeFilter = ''
			this.otherTimeFilter = ''
			this.otherDeltaFilter = ''
			this.mapNameFilter = ''
			this.dateMinFilter = ''
			this.dateMaxFilter = ''
		},
		onCellClick(column, row) {
			if (column.field === 'mapInfo.cleanName') {
				this.detailPb = row
			} else if (column.field === 'mapInfo.favorite') {
				emit('toggle-favorite', row)
			}
		},
		onHeaderShiftClick(column) {
			if (this.sortOptions.length === 0) {
				this.onHeaderClick(column)
				return
			}
			const existingSortOption = this.sortOptions.find(so => so.sortName === column.sortName)
			if (existingSortOption) {
				if (existingSortOption.order === 'desc') {
					const index = this.sortOptions.findIndex(so => so.sortName === column.sortName)
					this.sortOptions.splice(index, 1)
				} else {
					existingSortOption.order = 'desc'
				}
			} else {
				this.sortOptions.push({
					sortName: column.sortName,
					order: 'asc',
				})
			}
			this.saveOptions({
				sort_columns: this.sortOptions.map(so => so.sortName).join(','),
				sort_orders: this.sortOptions.map(so => so.order).join(','),
			})
		},
		onHeaderClick(column) {
			if (this.sortOptions.length > 0 && this.sortOptions[0].sortName === column.sortName) {
				if (this.sortOptions[0].order === 'desc') {
					this.sortOptions = []
				} else {
					this.sortOptions = [{
						sortName: column.sortName,
						order: 'desc',
					}]
				}
			} else {
				this.sortOptions = [{
					sortName: column.sortName,
					order: 'asc',
				}]
			}

			this.saveOptions({
				sort_columns: this.sortOptions.map(so => so.sortName).join(','),
				sort_orders: this.sortOptions.map(so => so.order).join(','),
			})
		},
	},
}
</script>

<style scoped lang="scss">
#trackmania_main {
	overflow-x: scroll;
	height: 100%;
	padding: 30px;

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
		&__medals p {
			display: flex;
			gap: 8px;
			align-items: center;
			img {
				width: 32px;
			}
		}
	}

	:deep(.mapNameColumn) {
		color: #111;
		background: #B0B0B0;
		font-weight: bold;
		cursor: pointer;
		max-width: 250px;
		overflow: hidden;
		text-overflow: ellipsis;
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

	:deep(.mapMedalColumn) .medal-cell {
		display: flex;
		align-items: center;
		justify-content: end;
		gap: 4px;
		img {
			width: 32px;
		}
	}

	.date-filters {
		display: flex;
		align-items: center;
		justify-content: center;
		flex-wrap: wrap;
		gap: 4px;
		input {
			flex-grow: 1;
		}
	}

	.select-filter {
		min-width: 170px;
	}

	.text-input-filter {
		margin: 0;
	}

	.select-filter,
	.medal-filter-select,
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
