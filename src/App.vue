<template>
	<NcContent app-name="integration_trackmania">
		<NcAppContent
			:show-details="false"
			@update:showDetails="a = 2">
			<!--template #list>
			</template-->
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
						<NcProgressBar :value="infoLoadingPercent" size="medium" />
					</div>
				</template>
			</NcEmptyContent>
			<MainContent v-else-if="hasData"
				:pbs="pbs"
				:zone-names="zoneNames"
				:config-state="tableState"
				@disconnect="disconnect"
				@reload="reloadData" />
			<NcEmptyContent v-else
				class="main-empty-content"
				:name="t('integration_trackmania', 'Failed to get the data')">
				<template #icon>
					<TrackmaniaIcon />
				</template>
			</NcEmptyContent>
		</NcAppContent>
	</NcContent>
</template>

<script>
import CogIcon from 'vue-material-design-icons/Cog.vue'

import TrackmaniaIcon from './components/icons/TrackmaniaIcon.vue'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'

import PersonalSettings from './components/PersonalSettings.vue'
import MainContent from './components/MainContent.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { formatPbs } from './utils.js'

export default {
	name: 'App',

	components: {
		MainContent,
		TrackmaniaIcon,
		PersonalSettings,
		CogIcon,
		NcAppContent,
		NcContent,
		NcEmptyContent,
		NcLoadingIcon,
		NcProgressBar,
	},

	props: {
	},

	data() {
		return {
			userState: loadState('integration_trackmania', 'user-config'),
			tableState: loadState('integration_trackmania', 'table-config'),
			loadingData: false,
			zoneNames: null,
			pbs: [],
			infoLoadingPercent: 0,
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
	},

	beforeDestroy() {
		unsubscribe('get-nb-players', this.getNbPlayers)
		unsubscribe('toggle-favorite', this.toggleFavorite)
		unsubscribe('save-options', this.saveOptions)
	},

	methods: {
		onConnected(userName, accountId) {
			this.userState.user_name = userName
			this.userState.account_id = accountId
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
		reloadData() {
			this.pbs = []
			this.getPbs()
		},
		/**
		 * first get records and then map info by chunks
		 */
		getPbs() {
			this.infoLoadingPercent = 0
			this.loadingData = true
			const url = generateUrl('/apps/integration_trackmania/pbs/raw')
			axios.get(url).then((response) => {
				this.$options.rawPbs = response.data
				this.$options.pbsWithInfo = []
				this.getPbsInfo()
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
			})
		},
		getPbsInfo() {
			const rawPbs = this.$options.rawPbs
			const chunks = []
			let i = 0
			while (i < rawPbs.length) {
				let j = 0
				const currentChunk = []
				while (j < 100 && i < rawPbs.length) {
					currentChunk.push(rawPbs[i])
					i++
					j++
				}
				chunks.push(currentChunk)
			}
			Promise.all(chunks.map(c => this.getPbsChunkInfo(c)))
				.then(result => {
					console.debug('----- all done', this.$options.pbsWithInfo)
					this.zoneNames = this.getZoneNames(this.$options.pbsWithInfo[0])
					this.pbs = formatPbs(this.$options.pbsWithInfo)
					this.$options.rawPbs = null
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
			const req = {
				pbTimesByMapId,
			}
			return axios.post(url, req).then((response) => {
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
						}
					}
					return c
				})
				this.$options.pbsWithInfo.push(...chunkWithInfo)
				this.infoLoadingPercent = parseInt(this.$options.pbsWithInfo.length / this.$options.rawPbs.length * 100)
				console.debug('----- ONE done', this.infoLoadingPercent)
			}).catch((error) => {
				console.error(error)
			})
		},
		/**
		 * get all at once
		 */
		getPbsAndInfo() {
			this.loadingData = true
			const url = generateUrl('/apps/integration_trackmania/pbs')
			axios.get(url).then((response) => {
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
		getZoneNames(onePb) {
			return Object.keys(onePb.recordPosition.zones)
		},
		getNbPlayers(pb) {
			const url = generateUrl('/apps/integration_trackmania/map/{mapUid}/finish-count', { mapUid: pb.mapInfo.uid })
			axios.get(url).then((response) => {
				this.$set(pb.mapInfo, 'nb_players', response.data)
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
			this.$set(realPb.mapInfo, 'formattedFavorite', '…')
			const url = pb.mapInfo.favorite
				? generateUrl('/apps/integration_trackmania/map/favorite/{mapUid}/remove', { mapUid: pb.mapInfo.uid })
				: generateUrl('/apps/integration_trackmania/map/favorite/{mapUid}/add', { mapUid: pb.mapInfo.uid })
			axios.post(url).then((response) => {
				this.$set(realPb.mapInfo, 'favorite', !pb.mapInfo.favorite)
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to add/remove favorite map')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			}).then(() => {
				this.$set(realPb.mapInfo, 'formattedFavorite', realPb.mapInfo.favorite ? '⭐' : '☆')
			})
		},
		saveOptions(values) {
			// Object.assign(this.tableState, values)
			Object.keys(values).forEach(k => {
				this.$set(this.tableState, k, values[k])
			})
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
	},
}
</script>

<style scoped lang="scss">
// TODO in global css loaded by main
body {
	min-height: 100%;
	height: auto;
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
}
</style>
