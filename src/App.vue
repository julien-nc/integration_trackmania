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
					:other-account.sync="selectedOtherAccount"
					:user-state="userState"
					@disconnect="disconnect"
					@reload="reloadData"
					@reload-filtered="reloadFilteredData"
					@update:other-account="onUpdateOtherAccount" />
				<UserData
					ref="userData"
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

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import NcButton from '@nextcloud/vue/components/NcButton'

import PersonalSettings from './components/PersonalSettings.vue'
import UserData from './components/UserData.vue'
import AccountHeader from './components/AccountHeader.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { formatPbs } from './utils.js'

export default {
	name: 'App',

	components: {
		UserData,
		AccountHeader,
		TrackmaniaIcon,
		PersonalSettings,
		CogIcon,
		CloseIcon,
		NcAppContent,
		NcContent,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
		NcButton,
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
			selectedOtherAccount: {},
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
		// console.debug('userState', this.userState)
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
		reloadFilteredData() {
			const filteredPbs = this.$refs.userData.filteredPbs
			this.reloadData(filteredPbs.map(pb => pb.mapInfo.mapId))
		},
		reloadData(mapIdList = null) {
			if (mapIdList === null) {
				this.pbs = []
			}
			if (mapIdList === null || mapIdList.length > 0) {
				this.getPbs(mapIdList)
			}
		},
		// first get records and then map info by chunks
		getPbs(mapIdList = null) {
			this.abortController = new AbortController()
			this.infoLoadingPercent = 0
			this.loadingData = true
			const reqData = {}
			const reqConfig = {
				signal: this.abortController.signal,
			}
			if (mapIdList !== null) {
				reqData.mapIdList = mapIdList
			}
			const url = generateUrl('/apps/integration_trackmania/pbs/raw')
			axios.post(url, reqData, reqConfig).then((response) => {
				this.$options.rawPbsToGetInfoOn = response.data
				this.$options.pbsWithInfo = []
				this.getPbsInfo(mapIdList)
			}).catch((error) => {
				const data = error.response?.data
				if (data?.error === 'token_refresh_failed' && data?.status_code === 401) {
					showError(
						t('integration_trackmania', 'Your Trackmania session has expired and cannot be refreshed anymore. Please reconnect'),
					)
					this.disconnect()
				} else {
					showError(t('integration_trackmania', 'Failed to get data'))
					this.loadingData = false
				}
				console.debug(error)
			}).then(() => {
			})
		},
		getPbsInfo(mapIdList = null) {
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
			Promise.all(chunks.map(c => this.getPbsChunkInfo(c)))
				.then(results => {
					if (results.some(result => result.code === 'ERR_CANCELED')) {
						console.debug('At least one request has been canceled, do nothing')
						return
					}
					console.debug('aaaaaa promise.all results', results)
					console.debug('----- all done', this.$options.pbsWithInfo)
					this.zoneNames = this.getZoneNames(this.$options.pbsWithInfo[0])
					// TODO extend instead of replace
					if (mapIdList !== null) {
						this.mergePbs(mapIdList)
					} else {
						this.pbs = formatPbs(this.$options.pbsWithInfo)
					}
					this.$options.rawPbsToGetInfoOn = null
					this.$options.pbsWithInfo = null
				})
				.catch(error => {
					console.error(error)
				})
				.then(() => {
					this.loadingData = false
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
							otherRecord: info.otherRecord,
							bestKnownPosition: info.bestKnownPosition,
						}
					}
					return c
				})
				this.$options.pbsWithInfo.push(...chunkWithInfo)
				this.infoLoadingPercent = parseInt(this.$options.pbsWithInfo.length / this.$options.rawPbsToGetInfoOn.length * 100)
				console.debug('----- ONE done', this.infoLoadingPercent)
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
			})
		},
		mergePbs(mapIdList) {
			this.pbs = this.pbs.filter(pb => !mapIdList.includes(pb.mapInfo.mapId))
			this.pbs.push(...formatPbs(this.$options.pbsWithInfo))
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
</style>
