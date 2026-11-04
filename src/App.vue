<template>
	<NcContent app-name="integration_trackmania">
		<NcAppContent
			:show-details="false"
			@update:showDetails="a = 2">
			<!--template #list>
			</template-->
			<h2 class="page-title">
				<TrackmaniaIcon class="icon" />
				<span>{{ t('integration_trackmania', 'Trackmania integration') }}</span>
			</h2>
			<div v-if="!connected">
				<NcEmptyContent
					:name="t('integration_trackmania', 'You are not connected to Trackmania')">
					<template #icon>
						<CogIcon />
					</template>
				</NcEmptyContent>
				<PersonalSettings
					class="settings"
					:show-title="true"
					:config="userState"
					@connected="onConnected" />
			</div>
			<NcEmptyContent v-else-if="loadingData"
				class="main-empty-content"
				:name="t('integration_trackmania', 'Loading your records')">
				<template #icon>
					<NcLoadingIcon />
				</template>
				<template #action>
					<div class="loading-progress">
						{{ infoLoadingPercent }} %
						<NcProgressBar :value="infoLoadingPercent"
							type="linear"
							size="medium" />
						<span class="loading-caption">{{ loadingText }}</span>
						<NcButton @click="cancelDataLoad">
							<template #icon>
								<CloseIcon />
							</template>
							{{ t('integration_trackmania', 'Cancel') }}
						</NcButton>
					</div>
				</template>
			</NcEmptyContent>
			<div v-else>
				<AccountHeader
					v-model:other-account="selectedOtherAccount"
					:user-state="userState"
					:last-update-time="lastUpdateTime"
					:last-load-duration-ms="lastLoadDurationMs"
					@disconnect="disconnect"
					@reload="reloadData"
					@update:other-account="onUpdateOtherAccount" />
				<UserData
					:pbs="pbs"
					:zone-names="zoneNames"
					:config-state="tableState" />
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import CogIcon from 'vue-material-design-icons/Cog.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import TrackmaniaIcon from './components/icons/TrackmaniaIcon.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { defineAsyncComponent } from 'vue'
import { formatPbs } from './utils.js'

// While the info chunks can display an exact percentage, the number of records is unknown
// upstream, so it is displayed as a growing counter instead.
// The bar range 0-FAVORITES_PERCENT_END is reserved for the favorites request (made in one shot),
// it stays at FAVORITES_PERCENT_END while the records are fetched by pages
// and goes from FAVORITES_PERCENT_END to 100 with the info chunks.
const FAVORITES_PERCENT_END = 10
// must match TrackmaniaAPIService::OWN_RECORDS_PAGE_SIZE
const OWN_RECORDS_PAGE_SIZE = 1000
// how many records pages are fetched at once,
// in line with the responsible request burst guidance
const RECORDS_PAGE_WINDOW = 8

export default {
	name: 'App',

	components: {
		UserData: defineAsyncComponent(() => import('./components/UserData.vue')),
		AccountHeader: defineAsyncComponent(() => import('./components/AccountHeader.vue')),
		PersonalSettings: defineAsyncComponent(() => import('./components/PersonalSettings.vue')),
		NcAppContent: defineAsyncComponent(() => import('@nextcloud/vue/components/NcAppContent')),
		NcContent: defineAsyncComponent(() => import('@nextcloud/vue/components/NcContent')),
		NcEmptyContent: defineAsyncComponent(() => import('@nextcloud/vue/components/NcEmptyContent')),
		NcProgressBar: defineAsyncComponent(() => import('@nextcloud/vue/components/NcProgressBar')),
		NcLoadingIcon: defineAsyncComponent(() => import('@nextcloud/vue/components/NcLoadingIcon')),
		NcButton: defineAsyncComponent(() => import('@nextcloud/vue/components/NcButton')),
		TrackmaniaIcon,
		CogIcon,
		CloseIcon,
	},

	props: {
	},

	data() {
		return {
			userState: loadState('integration_trackmania', 'user-config'),
			tableState: loadState('integration_trackmania', 'table-config'),
			loadingData: false,
			abortController: null,
			zoneNames: [],
			pbs: [],
			infoLoadingPercent: 0,
			loadingText: '',
			selectedOtherAccount: {},
			lastUpdateTime: null,
			lastLoadDurationMs: null,
		}
	},

	computed: {
		connected() {
			return !!this.userState.user_name && !!this.userState.core_token
		},
		hasData() {
			return this.pbs.length > 0
		},
	},

	watch: {
	},

	beforeMount() {
		// never log userState: it contains the OAuth core token
		console.debug('[trackmania] tableState', this.tableState)
	},

	mounted() {
		if (this.connected) {
			this.getPbs()
		}
		subscribe('get-nb-players', this.getNbPlayers)
		subscribe('toggle-favorite', this.toggleFavorite)
		subscribe('save-options', this.saveOptions)
		if (this.tableState.other_account_id && this.tableState.other_account_name) {
			this.selectedOtherAccount = {
				id: this.tableState.other_account_id,
				name: this.tableState.other_account_name,
				zoneDisplayName: this.tableState.other_account_zone_name,
				flagCode: this.tableState.other_account_flag_code,
				flagUrl: generateUrl('/apps/integration_trackmania/flag/{code}', { code: this.tableState.other_account_flag_code }),
			}
		}
	},

	unmounted() {
		unsubscribe('get-nb-players', this.getNbPlayers)
		unsubscribe('toggle-favorite', this.toggleFavorite)
		unsubscribe('save-options', this.saveOptions)
	},

	methods: {
		onConnected(data) {
			this.userState.user_name = data.user_name
			this.userState.account_id = data.user_id
			this.userState.user_flag_code = data.user_flag_code
			this.userState.user_zone_name = data.user_zone_name
			this.userState.core_token = 'plop'
			this.getPbs()
		},
		disconnect() {
			const req = {
				values: {
					core_token: '',
				},
			}
			const url = generateUrl('/apps/integration_trackmania/config')
			axios.put(url, req)
				.then((response) => {
					this.userState.core_token = ''
				})
				.catch((error) => {
					showError(
						t('integration_trackmania', 'Failed to disconnect')
						+ ': ' + (error.response?.request?.responseText ?? ''),
					)
					console.error(error)
				})
		},
		onUpdateOtherAccount() {
			this.saveOptions({
				other_account_id: this.selectedOtherAccount?.id ?? '',
				other_account_name: this.selectedOtherAccount?.name ?? '',
				other_account_flag_code: this.selectedOtherAccount?.flagCode ?? '',
				other_account_zone_name: this.selectedOtherAccount?.zoneDisplayName ?? '',
			})
			this.reloadData()
		},
		cancelDataLoad() {
			this.loadingData = false
			if (this.abortController) {
				this.abortController.abort()
			}
		},
		reloadData() {
			this.pbs = []
			this.getPbs()
		},
		// first get favorites and raw records page by page, then map info by chunks
		getPbs() {
			this.abortController = new AbortController()
			this.infoLoadingPercent = 0
			this.loadingText = ''
			this.loadingData = true
			this._loadStartTime = Date.now()
			this.$options.favoritesMapIdSet = new Set()
			this.$options.rawPbsToGetInfoOn = null
			this.$options.pbsWithInfo = null
			this.loadData()
		},
		async loadData() {
			const favoritesLoaded = await this.runFavoritesPhase()
			if (!favoritesLoaded) {
				return
			}
			const recordsLoaded = await this.runRecordsPhase()
			if (!recordsLoaded) {
				return
			}
			this.$options.pbsWithInfo = []
			if (this.$options.rawPbsToGetInfoOn.length > 0) {
				this.getPbsInfo()
			} else {
				this.finishDataLoad()
			}
		},
		async runFavoritesPhase() {
			// all favorites are fetched in one server-side request, this phase is quick
			this.loadingText = t('integration_trackmania', 'Loading favorites…')
			try {
				const response = await axios.post(
					generateUrl('/apps/integration_trackmania/pbs/favorites'),
					{},
					{ signal: this.abortController.signal },
				)
				response.data.mapIds.forEach(mapId => this.$options.favoritesMapIdSet.add(mapId))
				this.infoLoadingPercent = FAVORITES_PERCENT_END
				return true
			} catch (error) {
				this.handleLoadError(error)
				return false
			}
		},
		async runRecordsPhase() {
			// the total number of records is not provided by the upstream endpoint,
			// so no percentage is shown while they are fetched, only a growing counter
			this.loadingText = t('integration_trackmania', 'Loading records ({count})', { count: 0 })
			this.$options.rawPbsToGetInfoOn = []
			try {
				// pages are fetched in parallel waves while keeping the offset order,
				// so the built array does not depend on the wave splits
				let baseOffset = 0
				while (true) {
					const offsets = []
					for (let p = 0; p < RECORDS_PAGE_WINDOW; p++) {
						offsets.push(baseOffset + p * OWN_RECORDS_PAGE_SIZE)
					}
					const responses = await Promise.all(offsets.map(offset => axios.post(
						generateUrl('/apps/integration_trackmania/pbs/records') + '?offset=' + offset,
						{},
						{ signal: this.abortController.signal },
					)))
					// a page with an incomplete upstream page covers the last records:
					// when one returns hasMoreRecords === false, the wave pages at lower
					// offsets are merged in order, the ones after it are beyond the last record
					// and only come back empty
					const reachedEnd = responses.some(response => response.data.hasMoreRecords === false)
					for (const response of responses) {
						response.data.records.forEach(rawPb => {
							rawPb.mapInfo.favorite = this.$options.favoritesMapIdSet.has(rawPb.mapInfo.mapId)
						})
						this.$options.rawPbsToGetInfoOn.push(...response.data.records)
					}
					this.loadingText = t('integration_trackmania', 'Loading records ({count})', {
						count: this.$options.rawPbsToGetInfoOn.length,
					})
					if (reachedEnd) {
						break
					}
					baseOffset += RECORDS_PAGE_WINDOW * OWN_RECORDS_PAGE_SIZE
				}
				return true
			} catch (error) {
				this.handleLoadError(error)
				return false
			}
		},
		handleLoadError(error) {
			if (error.code === 'ERR_CANCELED') {
				console.debug('Data loading was canceled, do nothing')
				return
			}
			const data = error.response?.data
			if (data?.error === 'token_refresh_failed' && data?.status_code === 401) {
				showError(
					t('integration_trackmania', 'Your Trackmania session has expired and cannot be refreshed anymore. Please reconnect'),
				)
				this.disconnect()
			} else {
				showError(t('integration_trackmania', 'Failed to get data'))
			}
			console.debug(error)
			this.loadingData = false
		},
		finishDataLoad() {
			this.loadingData = false
			this.lastUpdateTime = new Date()
			this.lastLoadDurationMs = Date.now() - this._loadStartTime
		},
		getPbsInfo() {
			this.loadingText = t('integration_trackmania', 'Loading map info…')
			const rawPbsToGetInfoOn = this.$options.rawPbsToGetInfoOn
			const chunks = []
			let i = 0
			while (i < rawPbsToGetInfoOn.length) {
				let j = 0
				const currentChunk = []
				while (j < 100 && i < rawPbsToGetInfoOn.length) {
					currentChunk.push(rawPbsToGetInfoOn[i])
					i++
					j++
				}
				chunks.push(currentChunk)
			}
			this.$options.nbChunksDone = 0
			this.$options.nbChunks = chunks.length
			Promise.all(chunks.map(c => this.getPbsChunkInfo(c)))
				.then(results => {
					if (results.some(result => result.code === 'ERR_CANCELED')) {
						console.debug('At least one request has been canceled, do nothing')
						return
					}
					this.zoneNames = this.getZoneNames(this.$options.pbsWithInfo[0])
					this.pbs = formatPbs(this.$options.pbsWithInfo)
					this.$options.rawPbsToGetInfoOn = null
					this.$options.pbsWithInfo = null
				})
				.catch(error => {
					console.error(error)
				})
				.then(() => {
					this.finishDataLoad()
				})
		},
		getPbsChunkInfo(chunk) {
			const pbTimesByMapId = {}
			for (let i = 0; i < chunk.length; i++) {
				const mapId = chunk[i].mapInfo.mapId
				const time = chunk[i].record.recordScore.time
				pbTimesByMapId[mapId] = time
			}
			const url = generateUrl('/apps/integration_trackmania/pbs/info')
			const reqConfig = {
				signal: this.abortController.signal,
			}
			const reqData = {
				pbTimesByMapId,
			}
			if (this.tableState.other_account_id) {
				reqData.otherAccountId = this.tableState.other_account_id
			}
			if (this.tableState.show_column_wr !== '0') {
				reqData.withWorldRecords = true
			}
			return axios.post(url, reqData, reqConfig).then(response => {
				const infoByMapId = response.data
				const chunkWithInfo = chunk.map(c => {
					if (c.mapInfo.mapId in infoByMapId) {
						const info = infoByMapId[c.mapInfo.mapId]
						return {
							mapInfo: {
								...c.mapInfo,
								...info.mapInfo,
							},
							record: c.record,
							recordPosition: info.recordPosition,
							otherRecord: info.otherRecord ?? null,
							worldRecord: info.worldRecord ?? null,
							bestKnownPosition: info.bestKnownPosition,
						}
					}
					return c
				})
				this.$options.pbsWithInfo.push(...chunkWithInfo)
				const loadedShare = this.$options.rawPbsToGetInfoOn.length > 0
					? this.$options.pbsWithInfo.length / this.$options.rawPbsToGetInfoOn.length
					: 1
				this.infoLoadingPercent = parseInt(
					FAVORITES_PERCENT_END + (loadedShare * (100 - FAVORITES_PERCENT_END)),
				)
				console.debug(`----- ${++this.$options.nbChunksDone}/${this.$options.nbChunks} chunks done ${this.infoLoadingPercent} %`)
				return response
			}).catch((error) => {
				console.error(error)
				return error
			})
		},
		/**
		 * get all at once
		 */
		getPbsAndInfo() {
			this.loadingData = true
			this._loadStartTime = Date.now()
			const url = generateUrl('/apps/integration_trackmania/pbs')
			const reqConfig = {
				signal: this.abortController.signal,
			}
			axios.get(url, reqConfig).then((response) => {
				this.zoneNames = this.getZoneNames(response.data[0])
				this.pbs = formatPbs(response.data)
			}).catch((error) => {
				const data = error.response?.data
				if (data?.error === 'trackmania_request_failed' && data?.status_code === 401) {
					showError(
						t('integration_trackmania', 'Your Trackmania session has expired and cannot be refreshed anymore. Please reconnect'),
					)
					this.disconnect()
				} else {
					showError(
						t('integration_trackmania', 'Failed to get data')
						+ ': ' + (data?.error ?? ''),
					)
				}
				console.debug(error)
			}).then(() => {
				this.loadingData = false
				this.lastUpdateTime = new Date()
				this.lastLoadDurationMs = Date.now() - this._loadStartTime
			})
		},
		getZoneNames(onePb) {
			return Object.keys(onePb.recordPosition.zones) || []
		},
		getNbPlayers(pb) {
			const url = generateUrl('/apps/integration_trackmania/map/{mapUid}/finish-count', { mapUid: pb.mapInfo.uid })
			axios.get(url).then((response) => {
				pb.mapInfo.nb_players = response.data
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to get player count')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
		},
		toggleFavorite(pb) {
			const realPb = this.pbs.find(e => e.mapInfo.uid === pb.mapInfo.uid)
			realPb.mapInfo.favoriteLoading = true
			const url = pb.mapInfo.favorite
				? generateUrl('/apps/integration_trackmania/map/favorite/{mapUid}/remove', { mapUid: pb.mapInfo.uid })
				: generateUrl('/apps/integration_trackmania/map/favorite/{mapUid}/add', { mapUid: pb.mapInfo.uid })
			axios.post(url).then((response) => {
				realPb.mapInfo.favorite = !pb.mapInfo.favorite
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to add/remove favorite map')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			}).then(() => {
				realPb.mapInfo.favoriteLoading = false
			})
		},
		saveOptions(values) {
			// Object.assign(this.tableState, values)
			Object.keys(values).forEach(k => {
				this.tableState[k] = values[k]
			})
			// the world record columns need the WR data to be fetched on the next full reload
			if (values.show_column_wr !== undefined) {
				this.reloadData()
			}
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_trackmania/config')
			return axios.put(url, req).then((response) => {
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to save options')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
// TODO in global css loaded by main
body {
	min-height: 100%;
	height: auto;
}

h2.page-title {
	margin-top: 0;
	padding: 20px 0 0 30px;
	display: flex;
	.icon {
		margin-right: 8px;
	}
}

.settings {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.main-empty-content {
	margin-top: 24px;
}

.loading-progress {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}

.loading-caption {
	color: var(--color-text-maxcontrast);
}
</style>
