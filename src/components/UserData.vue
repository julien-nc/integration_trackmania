<template>
	<div id="trackmania_main">
		<div v-if="configState.other_account_id" class="compare-badges">
			<span class="compare-badge compare-badge--better">
				👍 {{ t('integration_trackmania', '{nb} records better than other account', { nb: betterThanOtherCount }) }}
			</span>
			<span class="compare-badge compare-badge--worse">
				👎 {{ t('integration_trackmania', '{nb} records worse than other account', { nb: worseThanOtherCount }) }}
			</span>
			<span v-if="equalThanOtherCount > 0" class="compare-badge compare-badge--equal">
				👌 {{ t('integration_trackmania', '{nb} records equal than other account', { nb: equalThanOtherCount }) }}
			</span>
		</div>
		<div class="summary tm-card">
			<div class="stat-tiles">
				<div class="stat-tile stat-tile--medal">
					<img :src="authorMedalImageUrl" class="stat-medal" alt="">
					<span class="stat-number">{{ medalCount.author }}</span>
					<span class="stat-label">{{ t('integration_trackmania', 'Author') }}</span>
				</div>
				<div class="stat-tile stat-tile--medal">
					<img :src="goldMedalImageUrl" class="stat-medal" alt="">
					<span class="stat-number">{{ medalCount.gold }}</span>
					<span class="stat-label">{{ t('integration_trackmania', 'Gold') }}</span>
				</div>
				<div class="stat-tile stat-tile--medal">
					<img :src="silverMedalImageUrl" class="stat-medal" alt="">
					<span class="stat-number">{{ medalCount.silver }}</span>
					<span class="stat-label">{{ t('integration_trackmania', 'Silver') }}</span>
				</div>
				<div class="stat-tile stat-tile--medal">
					<img :src="bronzeMedalImageUrl" class="stat-medal" alt="">
					<span class="stat-number">{{ medalCount.bronze }}</span>
					<span class="stat-label">{{ t('integration_trackmania', 'Bronze') }}</span>
				</div>
				<div class="stat-tile stat-tile--none">
					<span class="stat-number">{{ medalCount.none }}</span>
					<span class="stat-label">{{ t('integration_trackmania', 'tracks without any medal') }}</span>
				</div>
				<div v-for="zn in enabledZones"
					:key="zn"
					class="stat-tile stat-tile--zone"
					:class="{ 'stat-tile--zone-open': unfoldedZones[zn] }"
					:title="t('integration_trackmania', 'Toggle the top 10/100/1000 details')"
					@click="toggleZoneTile(zn)">
					<span class="stat-zone-name">{{ zn }}</span>
					<span class="stat-number">{{ topCount[zn][1] }}</span>
					<span class="stat-label">{{ t('integration_trackmania', 'records in top 1') }}</span>
					<div v-if="unfoldedZones[zn]" class="stat-zone-rows">
						<span class="stat-zone-number">{{ topCount[zn][10] }}</span> {{ t('integration_trackmania', 'top 10') }}
						<span class="stat-zone-number">{{ topCount[zn][100] }}</span> {{ t('integration_trackmania', 'top 100') }}
						<span class="stat-zone-number">{{ topCount[zn][1000] }}</span> {{ t('integration_trackmania', 'top 1000') }}
					</div>
					<ChevronDownIcon class="stat-zone-chevron" :size="16" />
				</div>
			</div>
		</div>
		<!--div class="charts">
			<NbRecordsPerPosition :pbs="filteredPbs" />
		</div-->
		<div class="table-options tm-card" :class="{ open: tableOptionsOpen }">
			<div class="table-options-header"
				:title="t('integration_trackmania', 'Table options')"
				:aria-expanded="tableOptionsOpen"
				@click="tableOptionsOpen = !tableOptionsOpen">
				<span class="table-options-title">
					<TableCogIcon :size="16" />
					{{ t('integration_trackmania', 'Table options') }}
				</span>
				<ChevronDownIcon class="table-options-chevron" :size="18" />
			</div>
			<div v-show="tableOptionsOpen" class="checkColumns">
				<NcCheckboxRadioSwitch
					:model-value="configState.show_column_line_number !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_line_number', $event)">
					{{ t('integration_trackmania', 'Line numbers') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="configState.show_column_favorite !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_favorite', $event)">
					{{ t('integration_trackmania', 'Favorite') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="configState.show_column_author_name !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_author_name', $event)">
					{{ t('integration_trackmania', 'Author name') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="configState.show_column_date !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_date', $event)">
					{{ t('integration_trackmania', 'Date') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="configState.show_column_medals !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_medals', $event)">
					{{ t('integration_trackmania', 'Medals') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="configState.show_column_best_position !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_best_position', $event)">
					{{ t('integration_trackmania', 'Best position') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-for="zn in zoneNames"
					:key="zn"
					:model-value="configState['show_column_zone_' + zn] !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onZoneCheck(zn, $event)">
					{{ t('integration_trackmania', 'Position in {zone}', { zone: zn }) }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					:model-value="configState.show_column_wr !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_wr', $event)">
					{{ t('integration_trackmania', 'World record') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-if="configState.other_account_id"
					:model-value="configState.show_column_other_time !== '0'"
					class="checkColumn"
					type="switch"
					@update:model-value="onColumnCheck('show_column_other_time', $event)">
					{{ t('integration_trackmania', 'Other account information') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<MapDetailModal v-if="detailPb"
			:pb="detailPb"
			:config-state="configState"
			@close="detailPb = null"
			@world-record-fetched="onWorldRecordFetched" />
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
		<div ref="tableStartSentinel" />
		<SimpleTable
			:columns="columns"
			:rows="renderedPbs"
			:sort-options="sortOptions"
			@cell-clicked="onCellClick"
			@header-clicked="onHeaderClick"
			@header-shift-clicked="onHeaderShiftClick">
			<template #cell="{row, column}">
				<!-- eslint-disable vue/no-v-html -->
				<NcPopover v-if="column.field === 'mapInfo.cleanName'"
					class="map-name-popover"
					:delay="{ show: 300, hide: 100 }"
					:triggers="['hover']"
					no-focus-trap>
					<template #trigger>
						<span class="map-name-cell-trigger" v-html="row.mapInfo.htmlName" />
					</template>
					<template #default>
						<div class="map-name-popover-content">
							<div
								class="map-thumbnail"
								:class="{ 'thumbnail-loaded': mapThumbnailLoaded[row.mapInfo.mapId] }">
								<span class="map-thumbnail-loading">
									<NcLoadingIcon v-if="!mapThumbnailLoaded[row.mapInfo.mapId]"
										:size="24" />
								</span>
								<img
									:src="mapThumbnailUrl(row.mapInfo.mapId)"
									alt=""
									loading="lazy"
									@load="onMapThumbnailLoad(row.mapInfo.mapId)">
							</div>
							<span class="popover-map-name">{{ row.mapInfo.cleanName }}</span>
						</div>
					</template>
				</NcPopover>
				<!-- eslint-enable -->
				<span v-else-if="column.field === 'mapInfo.favorite'"
					:title="t('integration_trackmania', 'Click to toggle favorite')">
					<NcLoadingIcon v-if="row.mapInfo.favoriteLoading" />
					<StarIcon v-else-if="row.mapInfo.favorite" style="color: var(--color-favorite, --color-warning);" />
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
				<span v-else-if="column.field === 'bestKnownPosition.lastSeenAt'">
					{{ row.bestKnownPosition.formattedLastSeenAt ?? '' }}
				</span>
				<span v-else-if="column.field === 'otherRecord.time'">
					{{ row.otherRecord?.formattedTime ?? '' }}
				</span>
				<span v-else-if="column.field === 'otherRecord.position.zones.World'">
					<template v-if="row.otherRecord?.position?.zones?.World !== undefined">
						{{ row.otherRecord.position.zones.World }}
					</template>
					<template v-else-if="row.otherRecord">
						<span class="rank-button-cell">
							<NcLoadingIcon v-if="rankLoading[row.mapInfo.mapId]"
								:size="16" />
							<span v-else-if="rankBeyond[row.mapInfo.mapId]">
								{{ formatBeyondRank(rankBeyond[row.mapInfo.mapId]) }}
							</span>
							<NcButton v-else
								:title="t('integration_trackmania', 'Compute the exact position')"
								@click="computeExactRank(row)">
								<template #icon>
									<TargetIcon :size="16" />
								</template>
							</NcButton>
						</span>
					</template>
				</span>
				<span v-else-if="column.field === 'otherRecord.delta'"
					:style="row.otherRecord?.delta < 0 ? 'color: var(--color-border-success, --color-success);' : 'color: var(--color-border-error, --color-error);'">
					{{ row.otherRecord?.formattedDelta ?? '' }}
				</span>
				<span v-else-if="column.field === 'worldRecord.time'">
					{{ row.worldRecord?.formattedTime ?? '' }}
				</span>
				<span v-else-if="column.field === 'worldRecord.delta'"
					:style="row.worldRecord?.delta <= 0 ? 'color: var(--color-border-success, --color-success);' : 'color: var(--color-border-error, --color-error);'">
					{{ row.worldRecord?.formattedDelta ?? '' }}
				</span>
				<span v-else-if="column.field === 'record.unix_timestamp'"
					:title="row.record.formattedDateWithZone">
					{{ row.record.formattedDate }}
				</span>
				<span v-else-if="column.field === 'otherRecord.unix_timestamp'"
					:title="row.otherRecord?.formattedDateWithZone ?? undefined">
					{{ row.otherRecord?.formattedDate ?? '' }}
				</span>
				<span v-else-if="column.field === 'mapInfo.authorName'"
					:title="row.mapInfo.author ?? undefined">
					{{ row.mapInfo.authorName ?? '' }}
				</span>
				<span v-else>
					{{ getRawCellValue(row, column.field) }}
				</span>
			</template>
			<template #filter="{column}">
				<NcTextField
					v-if="column.field === 'mapInfo.cleanName'"
					:model-value="mapNameFilter"
					type="text"
					:label="t('integration_trackmania', 'Map name filter')"
					:placeholder="t('integration_trackmania', 'summer 2023')"
					:show-trailing-button="!!mapNameFilter"
					class="text-input-filter"
					@keyup.enter="onMapNameFilterChange"
					@trailing-button-click="setMapNameFilter('')" />
				<NcTextField
					v-else-if="column.field === 'mapInfo.authorName'"
					:model-value="mapAuthorNameFilter"
					type="text"
					:label="t('integration_trackmania', 'Map author name filter')"
					:placeholder="t('integration_trackmania', 'Nadeo')"
					:show-trailing-button="!!mapAuthorNameFilter"
					class="text-input-filter"
					@keyup.enter="onMapAuthorNameFilterChange"
					@trailing-button-click="setMapAuthorNameFilter('')" />
				<NcTextField
					v-else-if="column.field === 'record.recordScore.time'"
					:model-value="timeFilter"
					type="text"
					:label="t('integration_trackmania', 'Record time filter')"
					:show-trailing-button="!!timeFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onTimeFilterChange"
					@trailing-button-click="setTimeFilter('')" />
				<NcTextField
					v-else-if="column.field === 'otherRecord.time'"
					:model-value="otherTimeFilter"
					type="text"
					:label="t('integration_trackmania', 'Other time filter')"
					:show-trailing-button="!!otherTimeFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onOtherTimeFilterChange"
					@trailing-button-click="setOtherTimeFilter('')" />
				<NcTextField
					v-else-if="column.field === 'otherRecord.delta'"
					:model-value="otherDeltaFilter"
					type="text"
					:label="t('integration_trackmania', 'Other delta filter')"
					:show-trailing-button="!!otherDeltaFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onOtherDeltaFilterChange"
					@trailing-button-click="setOtherDeltaFilter('')" />
				<NcTextField
					v-else-if="column.field === 'worldRecord.name'"
					:model-value="worldRecordNameFilter"
					type="text"
					:label="t('integration_trackmania', 'World record holder filter')"
					:placeholder="t('integration_trackmania', 'Nadeo')"
					:show-trailing-button="!!worldRecordNameFilter"
					class="text-input-filter"
					@keyup.enter="onWorldRecordNameFilterChange"
					@trailing-button-click="setWorldRecordNameFilter('')" />
				<NcTextField
					v-else-if="column.field === 'worldRecord.time'"
					:model-value="worldRecordTimeFilter"
					type="text"
					:label="t('integration_trackmania', 'World record time filter')"
					:show-trailing-button="!!worldRecordTimeFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onWorldRecordTimeFilterChange"
					@trailing-button-click="setWorldRecordTimeFilter('')" />
				<NcTextField
					v-else-if="column.field === 'worldRecord.delta'"
					:model-value="worldRecordDeltaFilter"
					type="text"
					:label="t('integration_trackmania', 'World record delta filter')"
					:show-trailing-button="!!worldRecordDeltaFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for less than 10 seconds', { example: '< 10000' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onWorldRecordDeltaFilterChange"
					@trailing-button-click="setWorldRecordDeltaFilter('')" />
				<NcSelect
					v-else-if="column.field === 'mapInfo.favorite'"
					:model-value="selectedFavoriteFilter"
					:options="favoriteFilterOptions"
					:multiple="false"
					:label-outside="true"
					:aria-label-combobox="t('integration_trackmania', 'Favorite filter')"
					:placeholder="t('integration_trackmania', 'No filter')"
					class="select-filter favorite-select-filter"
					:class="{'favorite-select-filter-not-empty': !!selectedFavoriteFilter }"
					@update:model-value="onFavoriteFilterChange">
					<template #option="option">
						<div class="favorite-filter-select__option" style="display: flex; gap: 4px; align-items: center;">
							<StarIcon v-if="option.value === 'true'" style="color: var(--color-favorite, --color-warning);" />
							<StarOutlineIcon v-else-if="option.value === 'false'" style="color: var(--color-text-maxcontrast);" />
							{{ option.label }}
						</div>
					</template>
					<template #selected-option="option">
						<div class="favorite-filter-select__option" style="display: flex; gap: 4px; align-items: center;">
							<StarIcon v-if="option.value === 'true'" style="color: var(--color-favorite, --color-warning);" />
							<StarOutlineIcon v-else-if="option.value === 'false'" style="color: var(--color-text-maxcontrast);" />
							{{ option.label }}
						</div>
					</template>
				</NcSelect>
				<div v-else-if="column.field === 'bestKnownPosition.lastSeenAt'"
					class="date-filters">
					<NcDateTimePicker
						v-model="bestPositionLastSeenAfterFilter"
						class="date-picker"
						type="date"
						:placeholder="t('integration_trackmania', 'Lost after')"
						:confirm="false"
						:clearable="true"
						:hide-label="true"
						@update:model-value="onLastSeenAfterFilterChange" />
				</div>
				<div v-else-if="column.field === 'record.unix_timestamp'"
					class="date-filters">
					<NcDateTimePicker
						v-model="dateMinFilter"
						class="date-picker"
						type="date"
						:placeholder="t('integration_trackmania', 'Min date')"
						:confirm="false"
						:clearable="true"
						:hide-label="true"
						@update:model-value="onDateChange" />
					<NcDateTimePicker
						v-model="dateMaxFilter"
						class="date-picker"
						type="date"
						:placeholder="t('integration_trackmania', 'Max date')"
						:confirm="false"
						:clearable="true"
						:hide-label="true"
						@update:model-value="onDateChange" />
				</div>
				<NcSelect
					v-else-if="column.field === 'record.medal'"
					:model-value="selectedMedalFilter"
					:options="medalFilterOptions"
					:multiple="true"
					:label-outside="true"
					:aria-label-combobox="t('integration_trackmania', 'Medal filter')"
					:placeholder="t('integration_trackmania', 'No filter')"
					class="medal-filter-select"
					@update:model-value="onMedalFilterChange">
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
					v-else-if="column.field === 'bestKnownPosition.position'"
					:model-value="bestPositionFilter"
					type="text"
					:label="t('integration_trackmania', 'Best position filter')"
					:show-trailing-button="!!bestPositionFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onBestPositionFilterChange"
					@trailing-button-click="setBestPositionFilter('')" />
				<NcTextField
					v-else-if="column.field === 'otherRecord.position.zones.World'"
					:model-value="otherPositionFilter"
					type="text"
					:label="t('integration_trackmania', 'Other position filter')"
					:show-trailing-button="!!otherPositionFilter"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onOtherPositionFilterChange"
					@trailing-button-click="setOtherPositionFilter('')" />
				<NcTextField
					v-if="column.field.startsWith('recordPosition.zones.')"
					:model-value="zonePositionFilters[column.field] ?? ''"
					type="text"
					:label="t('integration_trackmania', 'Position filter')"
					:show-trailing-button="!!zonePositionFilters[column.field]"
					class="text-input-filter"
					:placeholder="t('integration_trackmania', '\'{example}\' for top 100', { example: '<= 100' }, null, { escape: false, sanitize: false })"
					@keyup.enter="onZonePositionFilterChange(column.field, $event.target.value)"
					@trailing-button-click="onZonePositionFilterChange(column.field, '')" />
			</template>
		</SimpleTable>
		<div ref="tableEndSentinel" />
	</div>
</template>

<script>
import StarIcon from 'vue-material-design-icons/Star.vue'
import StarOutlineIcon from 'vue-material-design-icons/StarOutline.vue'
import FilterRemoveIcon from 'vue-material-design-icons/FilterRemove.vue'
import TargetIcon from 'vue-material-design-icons/Target.vue'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import TableCogIcon from 'vue-material-design-icons/TableCog.vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import moment from '@nextcloud/moment'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { defineAsyncComponent } from 'vue'
import {
	dig, getFormattedBestMedal, getMedalImageUrl, formatWorldRecord,
	authorMedalImageUrl, goldMedalImageUrl, silverMedalImageUrl, bronzeMedalImageUrl,
} from '../utils.js'
// import NbRecordsPerPosition from './charts/NbRecordsPerPosition.vue'

// Filters are constant while a filter pass iterates the rows, so their derived
// form (uppercased string or parsed operator/number) is memoized by raw value.
const filterValueCache = new Map()

function cachedFilterValue(value, build) {
	let helper = filterValueCache.get(value)
	if (helper === undefined) {
		if (filterValueCache.size > 100) {
			filterValueCache.clear()
		}
		helper = build(value)
		filterValueCache.set(value, helper)
	}
	return helper
}

function parseNumberFilter(filterString) {
	if (filterString.startsWith('<=')) {
		return data => data <= parseInt(filterString.slice(2))
	} else if (filterString.startsWith('<')) {
		return data => data < parseInt(filterString.slice(1))
	} else if (filterString.startsWith('>=')) {
		return data => data >= parseInt(filterString.slice(2))
	} else if (filterString.startsWith('>')) {
		return data => data > parseInt(filterString.slice(1))
	}
	return data => data === parseInt(filterString)
}

// Same collation ordering as localeCompare, but through a reusable collator
// instance: the expensive setup is shared by all comparisons.
const sortCollator = new Intl.Collator()

// progressive rendering: rows are appended by chunks while the user scrolls
const RENDER_CHUNK = 200
const TABLE_END_ROOT_MARGIN = '1200px 0px'
const TABLE_START_ROOT_MARGIN = '600px 0px'

function compareSortValues(vA, vB, type) {
	if (type === 'boolean') {
		return vA === vB ? 0 : vA ? 1 : -1
	} else if (type === 'number') {
		return vA > vB ? 1 : vA < vB ? -1 : 0
	}
	return sortCollator.compare(vA, vB)
}

export default {
	name: 'UserData',

	components: {
		// NbRecordsPerPosition,
		SimpleTable: defineAsyncComponent(() => import('./SimpleTable.vue')),
		MapDetailModal: defineAsyncComponent(() => import('./MapDetailModal.vue')),
		NcSelect: defineAsyncComponent(() => import('@nextcloud/vue/components/NcSelect')),
		NcButton,
		NcLoadingIcon,
		NcPopover,
		NcTextField: defineAsyncComponent(() => import('@nextcloud/vue/components/NcTextField')),
		NcDateTimePicker: defineAsyncComponent(() => import('@nextcloud/vue/components/NcDateTimePicker')),
		NcCheckboxRadioSwitch: defineAsyncComponent(() => import('@nextcloud/vue/components/NcCheckboxRadioSwitch')),
		FilterRemoveIcon,
		TargetIcon,
		StarIcon,
		StarOutlineIcon,
		ChevronDownIcon,
		TableCogIcon,
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
			mapAuthorNameFilter: this.configState.filter_mapAuthorName ?? '',
			timeFilter: this.configState.filter_time ?? '',
			otherTimeFilter: this.configState.filter_other_time ?? '',
			otherDeltaFilter: this.configState.filter_other_delta ?? '',
			otherPositionFilter: this.configState.filter_other_position_world ?? '',
			worldRecordNameFilter: this.configState.filter_world_record_name ?? '',
			worldRecordTimeFilter: this.configState.filter_world_record_time ?? '',
			worldRecordDeltaFilter: this.configState.filter_world_record_delta ?? '',
			favoriteFilter: this.configState.filter_favorite ?? '',
			bestPositionFilter: this.configState.filter_best_position ?? '',
			bestPositionLastSeenAfterFilter: this.configState.filter_best_position_last_seen_at ? moment.unix(this.configState.filter_best_position_last_seen_at).toDate() : '',
			medalFilter: this.configState.filter_medal ? this.configState.filter_medal.split(',').map(v => parseInt(v)) : [],
			zonePositionFilters: {},
			sortOptions: this.initSortOptions(),
			detailPb: null,
			mapThumbnailLoaded: {},
			visibleRowCount: RENDER_CHUNK,
			// the table options card is collapsed by default
			tableOptionsOpen: false,
			// zone stats show only the top 1 count, the top 10/100/1000 rows unfold on click
			unfoldedZones: {},
			// per-map rank computations triggered from the table cells
			rankLoading: {},
			rankBeyond: {},
		}
	},

	computed: {
		hasFilters() {
			return this.medalFilter.length > 0
				|| this.favoriteFilter
				|| this.timeFilter
				|| this.otherTimeFilter
				|| this.otherDeltaFilter
				|| this.otherPositionFilter
				|| this.mapNameFilter
				|| this.mapAuthorNameFilter
				|| this.dateMinFilter
				|| this.dateMaxFilter
				|| this.bestPositionFilter
				|| this.bestPositionLastSeenAfterFilter
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
			// the predicates of the active filters are collected before the
			// single pass, so each row is filtered by every filter at once
			// instead of once per actively chained filter
			const predicates = []
			if (this.dateMinTimestamp) {
				const dateMinTimestamp = this.dateMinTimestamp
				predicates.push(pb => pb.record.unix_timestamp > dateMinTimestamp)
			}
			if (this.dateMaxTimestamp) {
				const dateMaxTimestamp = this.dateMaxTimestamp
				predicates.push(pb => pb.record.unix_timestamp < dateMaxTimestamp)
			}
			if (this.mapNameFilter) {
				const mapNameFilter = this.mapNameFilter
				predicates.push(pb => this.filterString(pb.mapInfo.cleanName, mapNameFilter))
			}
			if (this.mapAuthorNameFilter) {
				const mapAuthorNameFilter = this.mapAuthorNameFilter
				predicates.push(pb => this.filterString(pb.mapInfo.authorName, mapAuthorNameFilter))
			}
			if (this.bestPositionLastSeenAfterTimestamp) {
				const bestPositionLastSeenAfterTimestamp = this.bestPositionLastSeenAfterTimestamp
				predicates.push(pb => pb.bestKnownPosition.lastSeenAt > bestPositionLastSeenAfterTimestamp)
			}
			if (this.bestPositionFilter) {
				const bestPositionFilter = this.bestPositionFilter
				predicates.push(pb => this.filterNumber(pb.bestKnownPosition.position, bestPositionFilter))
			}
			if (this.timeFilter) {
				const timeFilter = this.timeFilter
				predicates.push(pb => this.filterNumber(pb.record.recordScore.time, timeFilter))
			}
			if (this.otherTimeFilter) {
				const otherTimeFilter = this.otherTimeFilter
				predicates.push(pb => this.filterNumber(pb.otherRecord?.time, otherTimeFilter))
			}
			if (this.otherDeltaFilter) {
				const otherDeltaFilter = this.otherDeltaFilter
				predicates.push(pb => this.filterNumber(pb.otherRecord?.delta, otherDeltaFilter))
			}
			if (this.otherPositionFilter) {
				const otherPositionFilter = this.otherPositionFilter
				predicates.push(pb => this.filterNumber(pb.otherRecord?.position?.zones?.World, otherPositionFilter))
			}
			if (this.worldRecordNameFilter) {
				const worldRecordNameFilter = this.worldRecordNameFilter
				predicates.push(pb => pb.worldRecord !== null
					&& typeof pb.worldRecord?.name === 'string'
					&& this.filterString(pb.worldRecord.name, worldRecordNameFilter))
			}
			if (this.worldRecordTimeFilter) {
				const worldRecordTimeFilter = this.worldRecordTimeFilter
				predicates.push(pb => this.filterNumber(pb.worldRecord?.time, worldRecordTimeFilter))
			}
			if (this.worldRecordDeltaFilter) {
				const worldRecordDeltaFilter = this.worldRecordDeltaFilter
				predicates.push(pb => this.filterNumber(pb.worldRecord?.delta, worldRecordDeltaFilter))
			}
			if (this.favoriteFilter) {
				const favoriteFilter = this.favoriteFilter
				predicates.push(pb => this.filterFavorite(pb.mapInfo.favorite, favoriteFilter))
			}
			if (this.medalFilter.length > 0) {
				const medalFilter = this.medalFilter
				predicates.push(pb => medalFilter.includes(pb.record.medal))
			}
			this.zoneNames.forEach(zn => {
				const zoneFilterKey = `recordPosition.zones.${zn}`
				const zoneFilterValue = this.zonePositionFilters[zoneFilterKey]
				if (zoneFilterValue) {
					predicates.push(pb => this.filterNumber(pb.recordPosition.zones[zn], zoneFilterValue))
				}
			})

			if (predicates.length === 0) {
				return this.pbs
			}
			return this.pbs.filter(pb => {
				for (const predicate of predicates) {
					if (!predicate(pb)) {
						return false
					}
				}
				return true
			})
		},
		sortedFilteredPbs() {
			if (this.sortOptions.length === 0) {
				return this.filteredPbs
			}
			const columns = this.sortOptions.map(so => this.columns.find(c => c.sortName === so.sortName)).filter(c => !!c)
			if (columns.length === 0) {
				return this.filteredPbs
			}
			// decorate: the values of each row are resolved once instead of walking
			// the dotted field path for both rows of every comparison
			const decorated = this.filteredPbs.map(pb => ({
				pb,
				keys: columns.map(c => {
					const value = this.getRawCellValue(pb, c.field)
					if (c.type === 'text') {
						return value ?? ''
					} else if (c.type === 'number') {
						return value ?? null
					}
					return value
				}),
			}))
			decorated.sort((itemA, itemB) => {
				// the index alignment between columns and sortOptions matches
				// the previous recursive comparator
				for (let i = 0; i < columns.length; i++) {
					const result = compareSortValues(itemA.keys[i], itemB.keys[i], columns[i].type)
					if (result !== 0) {
						return this.sortOptions[i].order === 'desc' ? -result : result
					}
				}
				return 0
			})
			return decorated.map(item => item.pb)
		},
		// the full sorted list is always complete, only the rendered rows are
		// capped: more chunks are appended while the user scrolls
		renderedPbs() {
			return this.visibleRowCount >= this.sortedFilteredPbs.length
				? this.sortedFilteredPbs
				: this.sortedFilteredPbs.slice(0, this.visibleRowCount)
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
					tdClass: 'lineNumberColumn',
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
			columns.push({
				label: t('integration_trackmania', 'Map name'),
				type: 'text',
				field: 'mapInfo.cleanName',
				tdClass: 'mapNameColumn',
				sortName: 'mapName',
			})
			if (this.configState.show_column_author_name !== '0') {
				columns.push({
					label: t('integration_trackmania', 'Author name'),
					type: 'text',
					field: 'mapInfo.authorName',
					tdClass: 'mapAuthorNameColumn',
					sortName: 'authorName',
				})
			}
			columns.push({
				label: t('integration_trackmania', 'PB'),
				type: 'number',
				field: 'record.recordScore.time',
				sortName: 'time',
			})
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
			if (this.configState.show_column_best_position !== '0') {
				columns.push({
					label: t('integration_trackmania', 'Best position'),
					type: 'number',
					field: 'bestKnownPosition.position',
					sortName: 'bestPosition',
				})
				columns.push({
					label: t('integration_trackmania', 'Best position last seen'),
					type: 'number',
					field: 'bestKnownPosition.lastSeenAt',
					sortName: 'bestPositionLastSeenAt',
				})
			}
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
				columns.push({
					label: t('integration_trackmania', 'Other # in World'),
					type: 'number',
					field: 'otherRecord.position.zones.World',
					tdClass: 'otherPositionColumn',
					sortName: 'otherPosition',
				})
			}
			if (this.configState.show_column_wr !== '0') {
				columns.push({
					label: t('integration_trackmania', 'World record holder'),
					type: 'text',
					field: 'worldRecord.name',
					sortName: 'wrHolderName',
				})
				columns.push({
					label: t('integration_trackmania', 'World record time'),
					type: 'number',
					field: 'worldRecord.time',
					sortName: 'wrTime',
				})
				columns.push({
					label: t('integration_trackmania', 'Delta with world record'),
					type: 'number',
					field: 'worldRecord.delta',
					sortName: 'wrDelta',
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
		bestPositionLastSeenAfterTimestamp() {
			if (this.bestPositionLastSeenAfterFilter) {
				return moment(this.bestPositionLastSeenAfterFilter).unix()
			}
			return ''
		},
	},

	watch: {
		// each new data load restarts the progressive rendering
		pbs() {
			this.visibleRowCount = RENDER_CHUNK
		},
	},

	beforeMount() {
		// initialize filter for each zone position
		Object.keys(this.configState).forEach(configKey => {
			if (configKey.startsWith('filter_position_zone_')) {
				const zn = configKey.replace('filter_position_zone_', '')
				this.zonePositionFilters['recordPosition.zones.' + zn] = this.configState[configKey]
			}
		})
	},

	mounted() {
		// append RENDER_CHUNK more rows whenever the table end (plus the root
		// margin) intersects the viewport; consecutive fires cascade until the
		// sentinel is pushed out of the margin
		this.tableEndObserver = new IntersectionObserver((entries) => {
			if (entries[0].isIntersecting
				&& this.visibleRowCount < this.sortedFilteredPbs.length) {
				this.visibleRowCount += RENDER_CHUNK
			}
		}, {
			rootMargin: TABLE_END_ROOT_MARGIN,
		})
		this.tableEndObserver.observe(this.$refs.tableEndSentinel)

		// near the table start the viewport can only show the first rows, so
		// the rendered rows can safely shrink back to the initial chunk
		// without removing anything from the screen
		this.tableStartObserver = new IntersectionObserver((entries) => {
			if (entries[0].isIntersecting
				&& this.visibleRowCount > RENDER_CHUNK) {
				this.visibleRowCount = RENDER_CHUNK
			}
		}, {
			rootMargin: TABLE_START_ROOT_MARGIN,
		})
		this.tableStartObserver.observe(this.$refs.tableStartSentinel)
	},

	unmounted() {
		this.tableEndObserver?.disconnect()
		this.tableEndObserver = null
		this.tableStartObserver?.disconnect()
		this.tableStartObserver = null
	},

	methods: {
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
		mapThumbnailUrl(mapId) {
			return generateUrl('/apps/integration_trackmania/map/{mapId}/thumbnail', { mapId })
		},
		onMapThumbnailLoad(mapId) {
			this.mapThumbnailLoaded[mapId] = true
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
		toggleZoneTile(zn) {
			this.unfoldedZones[zn] = !this.unfoldedZones[zn]
		},
		saveOptions(values) {
			emit('save-options', values)
		},
		filterString(data, filterString) {
			return data.toUpperCase().includes(cachedFilterValue(filterString, f => f.toUpperCase()))
		},
		filterNumber(data, filterString) {
			if (data) {
				return cachedFilterValue(filterString, parseNumberFilter)(data)
			}
			return false
		},
		filterFavorite(data, filterValue) {
			return filterValue === 'false'
				? data === false
				: data === true
		},
		onLastSeenAfterFilterChange() {
			this.saveOptions({
				filter_best_position_last_seen_at: this.bestPositionLastSeenAfterTimestamp,
			})
		},
		onDateChange() {
			this.saveOptions({
				filter_dateMin: this.dateMinTimestamp,
				filter_dateMax: this.dateMaxTimestamp,
			})
		},
		onMapNameFilterChange(e) {
			this.setMapNameFilter(e.target.value)
		},
		setMapNameFilter(value) {
			this.mapNameFilter = value
			this.saveOptions({
				filter_mapName: this.mapNameFilter,
			})
		},
		onMapAuthorNameFilterChange(e) {
			this.setMapAuthorNameFilter(e.target.value)
		},
		setMapAuthorNameFilter(value) {
			this.mapAuthorNameFilter = value
			this.saveOptions({
				filter_mapAuthorName: this.mapAuthorNameFilter,
			})
		},
		onBestPositionFilterChange(e) {
			this.setBestPositionFilter(e.target.value)
		},
		setBestPositionFilter(value) {
			this.bestPositionFilter = value
			this.saveOptions({
				filter_best_position: this.bestPositionFilter,
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
		onOtherPositionFilterChange(e) {
			this.setOtherPositionFilter(e.target.value)
		},
		setOtherPositionFilter(value) {
			this.otherPositionFilter = value
			this.saveOptions({
				filter_other_position_world: this.otherPositionFilter,
			})
		},
		onWorldRecordNameFilterChange(e) {
			this.setWorldRecordNameFilter(e.target.value)
		},
		setWorldRecordNameFilter(value) {
			this.worldRecordNameFilter = value
			this.saveOptions({
				filter_world_record_name: this.worldRecordNameFilter,
			})
		},
		onWorldRecordTimeFilterChange(e) {
			this.setWorldRecordTimeFilter(e.target.value)
		},
		setWorldRecordTimeFilter(value) {
			this.worldRecordTimeFilter = value
			this.saveOptions({
				filter_world_record_time: this.worldRecordTimeFilter,
			})
		},
		onWorldRecordDeltaFilterChange(e) {
			this.setWorldRecordDeltaFilter(e.target.value)
		},
		setWorldRecordDeltaFilter(value) {
			this.worldRecordDeltaFilter = value
			this.saveOptions({
				filter_world_record_delta: this.worldRecordDeltaFilter,
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
			this.zonePositionFilters[field] = value
			this.saveOptions({
				['filter_position_zone_' + zn]: value,
			})
		},
		formatBeyondRank(beyondPosition) {
			return '> ' + beyondPosition
		},
		computeExactRank(pb) {
			const mapId = pb.mapInfo?.mapId
			if (!mapId || this.rankLoading[mapId] || !pb.otherRecord?.time) {
				return
			}
			this.rankLoading[mapId] = true
			const url = generateUrl('/apps/integration_trackmania/map/{mapId}/ranking/{time}', {
				mapId,
				time: pb.otherRecord.time,
			})
			axios.get(url)
				.then((response) => {
					const { position, beyondPosition } = response.data
					if (position !== null && position !== undefined) {
						if (!pb.otherRecord.position) {
							// the row is patched in place so it joins the sort and filters right away
							pb.otherRecord.position = { score: pb.otherRecord.time, zones: {} }
						}
						pb.otherRecord.position.zones.World = position
					} else if (beyondPosition !== null && beyondPosition !== undefined) {
						this.rankBeyond[mapId] = beyondPosition
					}
					// without a rank and without a bound, the button stays, the click can be retried
				})
				.catch((error) => {
					console.debug('Failed to compute the exact other position', error)
				})
				.then(() => {
					this.rankLoading[mapId] = false
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
				filter_mapAuthorName: '',
				filter_other_time: '',
				filter_other_delta: '',
				filter_other_position_world: '',
				filter_world_record_name: '',
				filter_world_record_time: '',
				filter_world_record_delta: '',
				filter_best_position: '',
				filter_best_position_last_seen_at: '',
			}
			this.zoneNames.forEach(zn => {
				values['filter_position_zone_' + zn] = ''
				this.zonePositionFilters['recordPosition.zones.' + zn] = ''
			})
			this.saveOptions(values)
			this.medalFilter = []
			this.favoriteFilter = ''
			this.timeFilter = ''
			this.mapNameFilter = ''
			this.dateMinFilter = ''
			this.dateMaxFilter = ''
			this.mapAuthorNameFilter = ''
			this.otherTimeFilter = ''
			this.otherDeltaFilter = ''
			this.otherPositionFilter = ''
			this.worldRecordNameFilter = ''
			this.worldRecordTimeFilter = ''
			this.worldRecordDeltaFilter = ''
			this.bestPositionFilter = ''
			this.bestPositionLastSeenAfterFilter = ''
		},
		onCellClick(column, row) {
			if (column.field === 'mapInfo.cleanName') {
				this.detailPb = row
			} else if (column.field === 'mapInfo.favorite') {
				emit('toggle-favorite', row)
			}
		},
		onWorldRecordFetched(pb, worldRecord) {
			pb.worldRecord = worldRecord
			formatWorldRecord(pb)
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
	// no overflow here: any non-visible overflow makes this element the nearest
	// scroll container (even when it never scrolls) and traps the sticky table
	// headers of SimpleTable, which must bind to the app-content scroller instead.
	// The table's horizontal overflow is handled by .app-content.
	height: 100%;
	padding: 30px;

	.table-header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin: 0 0 12px;
		color: var(--color-text-maxcontrast);
		justify-content: center;
	}

	.compare-badges {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
		margin-bottom: 16px;
		justify-content: center;
	}

	.compare-badge {
		display: inline-flex;
		align-items: center;
		padding: 4px 12px;
		border-radius: 999px;
		border: 1px solid var(--tm-ring);
		background: var(--tm-surface);
		backdrop-filter: blur(10px);
		font-size: 0.85em;

		&--better {
			color: var(--tm-green);
			border-color: rgba(45, 224, 165, 0.35);
			box-shadow: 0 0 12px rgba(45, 224, 165, 0.12);
		}

		&--worse {
			color: var(--tm-red);
			border-color: rgba(255, 107, 126, 0.35);
			box-shadow: 0 0 12px rgba(255, 107, 126, 0.12);
		}

		&--equal {
			color: var(--tm-gold);
			border-color: rgba(255, 207, 94, 0.35);
			box-shadow: 0 0 12px rgba(255, 207, 94, 0.12);
		}
	}

	.summary {
		padding: 20px;
		margin: 0 auto 16px;
		width: fit-content;
	}

	.stat-tiles {
		display: flex;
		align-items: stretch;
		gap: 12px;
		flex-wrap: wrap;
	}

	.stat-tile {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 2px;
		min-width: 96px;
		padding: 12px 16px;
		border-radius: var(--tm-radius-small);
		border: 1px solid var(--tm-ring);
		background: var(--tm-tile-bg);
		text-align: center;

		.stat-medal {
			width: 32px;
			filter: drop-shadow(0 0 8px rgba(255, 207, 94, 0.4));
		}

		.stat-number {
			font-size: 1.7em;
			font-weight: bold;
			font-variant-numeric: tabular-nums;
			line-height: 1.1;
		}

		.stat-label {
			color: var(--color-text-maxcontrast);
			font-size: 0.75em;
		}
	}

	.stat-tile--none {
		max-width: 140px;
		color: var(--tm-red);
	}

	.stat-tile--zone {
		cursor: pointer;
		// some children have their own UA/global cursor, keep the pointer everywhere
		* {
			cursor: pointer;
		}

		min-width: 120px;

		&:hover {
			border-color: var(--tm-ring-strong);
		}

		.stat-zone-chevron {
			margin-top: 4px;
			transition: transform 0.15s ease;
			color: var(--tm-cyan);
		}

		&.stat-tile--zone-open .stat-zone-chevron {
			transform: rotate(180deg);
		}
	}

	.stat-zone-name {
		color: var(--tm-purple);
		font-weight: bold;
	}

	.stat-zone-rows {
		display: flex;
		flex-direction: column;
		align-items: center;
		color: var(--color-text-maxcontrast);
		font-size: 0.75em;
		gap: 2px;
		margin-top: 4px;

		.stat-zone-number {
			font-weight: bold;
			color: var(--color-main-text);
			font-variant-numeric: tabular-nums;
		}
	}

	.table-options {
		margin: 0 auto 16px;
		padding: 4px 20px;
		width: fit-content;

		&.open {
			width: 100%;
		}

		.table-options-header {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 12px 0;
			cursor: pointer;
			user-select: none;
			// some children have their own UA/global cursor, keep the pointer everywhere
			* {
				cursor: pointer;
			}
			color: var(--color-text-maxcontrast);

			.table-options-title {
				display: flex;
				align-items: center;
				gap: 8px;
				font-weight: bold;
			}

			.table-options-chevron {
				margin-left: 8px;
				transition: transform 0.15s ease;
			}

			&:hover {
				color: var(--tm-cyan);
				.table-options-chevron {
					color: var(--tm-cyan);
				}
			}
		}

		&.open .table-options-chevron {
			transform: rotate(180deg);
		}
	}

	.checkColumns {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		padding-bottom: 12px;
	}

	:deep(.mapNameColumn) {
		background: rgba(255, 255, 255, 0.05);
		font-weight: bold;
		cursor: pointer;
		max-width: 250px;
		overflow: hidden;
		text-overflow: ellipsis;
		border-radius: 6px;
		transition: background 0.1s ease, color 0.1s ease;

		&:hover {
			background: rgba(31, 212, 232, 0.14);
			color: var(--tm-cyan);
			box-shadow: 0 0 10px rgba(31, 212, 232, 0.18) inset;
		}
		* {
			cursor: pointer !important;
		}
	}

	// the map name cell has its own (always-on) background, which is not affected
	// by the row hover background-color of SimpleTable: emulate the row hover here
	// (:not(:hover) keeps the cell's own cyan hover effective when directly hovered)
	:deep(tr:hover .mapNameColumn:not(:hover)) {
		background-color: color-mix(in srgb, var(--color-primary-element-hover) 40%, transparent);
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

	:deep(.otherPositionColumn) .rank-button-cell {
		display: flex;
		align-items: center;
		justify-content: center;
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
	.text-input-filter {
		width: 100%;
	}

	.favorite-select-filter {
		min-width: 80px;
		width: 80px;
	}

	.medal-filter-select,
	.favorite-select-filter-not-empty {
		min-width: 170px;
		width: 170px;
	}

	.checkColumns {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}
}

// The popover trigger must be the whole map name cell: floating-vue binds the
// hover listeners to the trigger's root element, so outdent it over the cell
// padding (SimpleTable uses 6px) to cover the entire cell
.map-name-cell-trigger {
	display: block;
	box-sizing: border-box;
	margin: -6px;
	padding: 6px;
	height: calc(100% + 12px);
}

// NcPopover content is teleported to body, keep it out of the #trackmania_main nesting
.map-name-popover-content {
	display: flex;
	flex-direction: column;
	gap: 6px;
	align-items: center;
	padding: 8px;

	.map-thumbnail {
		position: relative;
		width: 400px;
		height: 224px;

		.map-thumbnail-loading {
			position: absolute;
			inset: 0;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		img {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			object-fit: contain;
			border-radius: var(--tm-radius-small);
			visibility: hidden;
		}
	}
	.thumbnail-loaded img {
		visibility: visible;
	}
	.popover-map-name {
		text-align: center;
	}
}
</style>
