<template>
	<NcContent app-name="integration_trackmania">
		<NcAppContent
			:show-details="false"
			@update:showDetails="a = 2">
			<!--template #list>
			</template-->
			<div class="tm-page">
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
						:show-title="false"
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
			selectedOtherAccount: null,
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
			this.userState.user_display_name = data.user_display_name
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
				// pages are fetched in parallel waves; responses are buffered per offset
				// and merged in offset order as soon as their neighbors have arrived,
				// so the built array does not depend on the wave splits
				// and the counter is updated when each single page request completes
				const waveBuffer = new Map()
				let nextOffsetToMerge = 0
				let waveReachedEnd = false
				const mergeResponseByOffset = (offset, response) => {
					waveBuffer.set(offset, response)
					while (waveBuffer.has(nextOffsetToMerge)) {
						const responseToMerge = waveBuffer.get(nextOffsetToMerge)
						waveBuffer.delete(nextOffsetToMerge)
						nextOffsetToMerge += OWN_RECORDS_PAGE_SIZE
						// an incomplete upstream page covers the last records,
						// the pages after it are beyond the last record and only come back empty
						if (responseToMerge.data.hasMoreRecords === false) {
							waveReachedEnd = true
						}
						responseToMerge.data.records.forEach(rawPb => {
							rawPb.mapInfo.favorite = this.$options.favoritesMapIdSet.has(rawPb.mapInfo.mapId)
						})
						this.$options.rawPbsToGetInfoOn.push(...responseToMerge.data.records)
						this.loadingText = t('integration_trackmania', 'Loading records ({count})', {
							count: this.$options.rawPbsToGetInfoOn.length,
						})
					}
				}
				let baseOffset = 0
				while (true) {
					const offsets = []
					for (let p = 0; p < RECORDS_PAGE_WINDOW; p++) {
						offsets.push(baseOffset + p * OWN_RECORDS_PAGE_SIZE)
					}
					await Promise.all(offsets.map(offset => axios.post(
						generateUrl('/apps/integration_trackmania/pbs/records') + '?offset=' + offset,
						{},
						{ signal: this.abortController.signal },
					).then(response => mergeResponseByOffset(offset, response))))
					if (waveReachedEnd) {
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

<style lang="scss">
// Neon glass design tokens of the Trackmania pages, dark and light theme variations.
// Defined on body because modals are teleported outside of .tm-page.
// The tokens and the .tm-card utility are non-scoped so every page component can use them.
// Theme definitions mirror what Nextcloud does on <body>:
// a bare data-theme-light / data-theme-dark attribute when a theme is forced,
// and only the OS preference (via media query) otherwise.
:root {
	--tm-radius: 16px;
	--tm-radius-small: 12px;

	--tm-cyan: #0087a5;
	--tm-purple: #6d4bd6;
	--tm-green: #0c7d4f;
	--tm-red: #c94055;
	--tm-gold: #9a6d00;

	--tm-surface: rgba(255, 255, 255, 0.6);
	--tm-surface-strong: rgba(255, 255, 255, 0.92);
	--tm-ring: rgba(0, 0, 0, 0.1);
	--tm-ring-strong: rgba(0, 0, 0, 0.18);
	--tm-line: rgba(0, 0, 0, 0.09);
	--tm-tile-bg: rgba(0, 0, 0, 0.03);

	// neon glow for emphasized texts, tuned for dark backgrounds.
	// the glowing elements must use --tm-neon-glow-text as color so the text
	// itself participates in the illusion of the light source
	--tm-neon-glow: drop-shadow(0 0 2px color-mix(in srgb, var(--color-primary) 100%, transparent))
		drop-shadow(0 0 6px color-mix(in srgb, var(--color-primary) 80%, transparent))
		drop-shadow(0 0 16px color-mix(in srgb, var(--color-primary) 60%, transparent))
		drop-shadow(0 0 36px color-mix(in srgb, var(--color-primary) 40%, transparent));
	--tm-neon-glow-text: currentColor;
}

@supports not (backdrop-filter: blur(1px)) {
	:root {
		--tm-surface: var(--color-main-background);
	}
}

body[data-theme-dark] {
	--tm-cyan: #1fd4e8;
	--tm-purple: #a78bfa;
	--tm-green: #2de0a5;
	--tm-red: #ff6b7e;
	--tm-gold: #ffcf5e;

	--tm-surface: rgba(24, 27, 33, 0.55);
	--tm-surface-strong: rgba(24, 27, 33, 0.85);
	--tm-ring: rgba(255, 255, 255, 0.09);
	--tm-ring-strong: rgba(255, 255, 255, 0.16);
	--tm-line: rgba(255, 255, 255, 0.07);
	--tm-tile-bg: rgba(255, 255, 255, 0.04);
}

body[data-theme-light] {
	// on light backgrounds the tight glow layers of the dark mode turn into a
	// muddy halo on the text: keep the glow tight so it outlines the text, and
	// tint the text itself towards the primary color so letters and glow read
	// as the same light source
	// (recommendations from the CSS community, e.g. the neon glow guides)
	--tm-neon-glow: drop-shadow(0 0 1px color-mix(in srgb, var(--color-primary) 100%, transparent))
		drop-shadow(0 0 3px color-mix(in srgb, var(--color-primary) 90%, transparent))
		drop-shadow(0 0 9px color-mix(in srgb, var(--color-primary) 40%, transparent));
	--tm-neon-glow-text: color-mix(in srgb, var(--color-primary) 75%, currentColor);
}

@media (prefers-color-scheme: light) {
	body:not([data-theme-light]):not([data-theme-dark]) {
		--tm-neon-glow: drop-shadow(0 0 1px color-mix(in srgb, var(--color-primary) 100%, transparent))
			drop-shadow(0 0 3px color-mix(in srgb, var(--color-primary) 90%, transparent))
			drop-shadow(0 0 9px color-mix(in srgb, var(--color-primary) 40%, transparent));
		--tm-neon-glow-text: color-mix(in srgb, var(--color-primary) 75%, currentColor);
	}
}

@media (prefers-color-scheme: dark) {
	body:not([data-theme-light]):not([data-theme-dark]) {
		--tm-cyan: #1fd4e8;
		--tm-purple: #a78bfa;
		--tm-green: #2de0a5;
		--tm-red: #ff6b7e;
		--tm-gold: #ffcf5e;

		--tm-surface: rgba(24, 27, 33, 0.55);
		--tm-surface-strong: rgba(24, 27, 33, 0.85);
		--tm-ring: rgba(255, 255, 255, 0.09);
		--tm-ring-strong: rgba(255, 255, 255, 0.16);
		--tm-line: rgba(255, 255, 255, 0.07);
		--tm-tile-bg: rgba(255, 255, 255, 0.04);
	}
}

.tm-page {
	padding: 0 30px;
	// fill the scroll container so the background is never cut on short pages
	min-height: 100%;
	background-attachment: fixed;
	background-image:
		radial-gradient(700px 500px at 12% 0%, color-mix(in srgb, var(--color-primary) 22%, transparent), transparent 65%),
		radial-gradient(900px 700px at 88% 24%, color-mix(in srgb, var(--color-primary) 18%, transparent), transparent 65%),
		radial-gradient(700px 600px at 55% 95%, color-mix(in srgb, var(--color-primary) 14%, transparent), transparent 65%);

	color: var(--color-main-text);
}

.tm-card {
	background: var(--tm-surface);
	backdrop-filter: blur(14px);
	-webkit-backdrop-filter: blur(14px);
	border: 1px solid var(--tm-ring);
	border-radius: var(--tm-radius);
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
	position: relative;
	overflow: hidden;

	&::before {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		height: 2px;
		background: linear-gradient(90deg, transparent 6%, var(--tm-accent, var(--color-primary)) 30%, var(--tm-accent, var(--color-primary)) 70%, transparent 94%);
		opacity: 0.75;
	}
}

.tm-page h2.page-title {
	padding: 20px 0 0;
	display: flex;
	justify-content: center;
	filter: var(--tm-neon-glow);

	.icon {
		margin-right: 8px;
	}

	span {
		// the text itself participates in the neon glow illusion (light mode);
		// this is currentColor in dark mode
		color: var(--tm-neon-glow-text);
	}
}
</style>
