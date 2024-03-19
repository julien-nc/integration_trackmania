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
						<NcProgressBar :value="infoLoadingPercent"
							type="linear"
							size="medium" />
					</div>
				</template>
			</NcEmptyContent>
			<MainContent v-else-if="hasData"
				:pbs="pbs"
				:zone-names="zoneNames"
				:config-state="tableState"
				@disconnect="disconnect"
				@reload="reloadData">
				<template #extra>
					<div class="accounts">
						<div class="connected-as">
							{{ t('integration_trackmania', 'Connected as {name}', { name: userState.user_name }) }}
							<img v-if="userState.user_flag_code"
								class="account-flag"
								:src="getFlagUrl(userState.user_flag_code)"
								:title="userState.user_zone_name">
							<span>({{ userState.account_id }})</span>
						</div>
						<div class="other-account">
							<h3>
								<strong>{{ t('integration_trackmania', 'Compare yourself with another player') }}</strong>
							</h3>
							<NcSelect
								:value="selectedOtherAccount"
								:options="otherAccountOptions"
								class="other-account-select"
								:multiple="false"
								:label-outside="true"
								label="name"
								:filter-by="() => true"
								:loading="searchingOtherAccount"
								:aria-label-combobox="t('integration_trackmania', 'Search account')"
								:placeholder="t('integration_trackmania', 'Search on trackmania.io by player name')"
								:append-to-body="false"
								@input="onOtherAccountSelectChange"
								@search="onOtherAccountSearch">
								<template #option="option">
									<div class="account-option">
										<img v-if="option.flagUrl"
											class="account-flag"
											:src="option.flagUrl"
											:title="option.zoneDisplayName">
										<span>{{ option.name }}</span>
									</div>
								</template>
								<template #selected-option="option">
									<div class="account-option">
										<img v-if="option.flagUrl"
											class="account-flag"
											:src="option.flagUrl"
											:title="option.zoneDisplayName">
										<span>{{ option.name }}</span>
									</div>
								</template>
							</NcSelect>
							<NcTextField
								:value.sync="tableState.other_account_id"
								type="text"
								:label="t('integration_trackmania', 'Account ID')"
								:show-trailing-button="!!tableState.other_account_id"
								class="other-account-input"
								:placeholder="t('integration_trackmania', 'account ID')"
								@keyup.enter="onAccountIdSubmit"
								@trailing-button-click="clearOtherAccount" />
						</div>
					</div>
				</template>
			</MainContent>
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
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import PersonalSettings from './components/PersonalSettings.vue'
import MainContent from './components/MainContent.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import debounce from 'debounce'

import { formatPbs, getFlagUrl, getFlagCode, getZoneDisplayName } from './utils.js'

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
		NcTextField,
		NcSelect,
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
			otherAccountOptions: [],
			selectedOtherAccount: null,
			otherAccountSearchQuery: '',
			searchingOtherAccount: false,
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
				name: this.tableState.other_account_name,
				id: this.tableState.other_account_id,
				flagCode: this.tableState.other_account_flag_code,
				flagUrl: generateUrl('/apps/integration_trackmania/flag/{code}', { code: this.tableState.other_account_flag_code }),
				zoneDisplayName: this.tableState.other_account_zone_name,
			}
		}
	},

	beforeDestroy() {
		unsubscribe('get-nb-players', this.getNbPlayers)
		unsubscribe('toggle-favorite', this.toggleFavorite)
		unsubscribe('save-options', this.saveOptions)
	},

	methods: {
		getFlagUrl(code) {
			return generateUrl('/apps/integration_trackmania/flag/{code}', { code })
		},
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
		clearOtherAccount() {
			this.tableState.other_account_id = ''
			this.tableState.other_account_name = ''
			this.tableState.other_account_flag_code = ''
			this.tableState.other_account_zone_name = ''
			this.saveOptions({
				other_account_id: '',
				other_account_name: '',
				other_account_flag_code: '',
				other_account_zone_name: '',
			})
			this.reloadData()
		},
		onAccountIdSubmit() {
			this.selectedOtherAccount = null
			this.reloadData()
			this.tableState.other_account_name = ''
			this.saveOptions({
				other_account_id: this.tableState.other_account_id,
				other_account_name: '',
				other_account_flag_code: '',
				other_account_zone_name: '',
			}).then(() => {
				// look for account name
				const url = generateUrl('/apps/integration_trackmania/account/search/{name}', { name: this.tableState.other_account_id })
				axios.get(url).then((response) => {
					if (response.data.length === 1) {
						console.debug('aaaaa onAccountIdSubmit', response.data[0])
						this.selectedOtherAccount = {
							name: response.data[0].player.name,
							id: response.data[0].player.id,
							flagCode: getFlagCode(response.data[0].player.zone),
							flagUrl: getFlagUrl(response.data[0].player.zone),
							zoneDisplayName: getZoneDisplayName(response.data[0].player.zone),
						}
						this.tableState.other_account_name = this.selectedOtherAccount.name
						this.tableState.other_account_flag_code = this.selectedOtherAccount.flagCode
						this.tableState.other_account_zone_name = this.selectedOtherAccount.zoneDisplayName
					} else {
						this.tableState.other_account_name = t('integration_trackmania', 'Unknown player name')
						this.tableState.other_account_flag_code = 'WOR'
						this.tableState.other_account_zone_name = 'World'
					}
					this.saveOptions({
						other_account_name: this.tableState.other_account_name,
						other_account_flag_code: this.tableState.other_account_flag_code,
						other_account_zone_name: this.tableState.other_account_zone_name,
					})
				}).catch(error => {
					console.error(error)
				})
			})
		},
		onOtherAccountSelectChange(value) {
			this.selectedOtherAccount = value
			if (value === null) {
				this.clearOtherAccount()
				return
			}
			this.tableState.other_account_id = value.id
			this.tableState.other_account_name = value.name
			this.tableState.other_account_flag_code = value.flagCode
			this.tableState.other_account_zone_name = value.zoneDisplayName
			this.saveOptions({
				other_account_id: this.tableState.other_account_id,
				other_account_name: this.tableState.other_account_name,
				other_account_flag_code: this.tableState.other_account_flag_code,
				other_account_zone_name: this.tableState.other_account_zone_name,
			})
			this.reloadData()
		},
		onOtherAccountSearch: debounce(function(query) {
			this.otherAccountSearchQuery = query
			if (query !== '') {
				this.otherAccountSearch()
			}
		}, 500),
		otherAccountSearch() {
			this.searchingOtherAccount = true
			const url = generateUrl('/apps/integration_trackmania/account/search/{name}', { name: this.otherAccountSearchQuery })
			axios.get(url).then((response) => {
				this.otherAccountOptions = response.data.map(item => {
					return {
						...item.player,
						flagCode: getFlagCode(item.player.zone),
						flagUrl: getFlagUrl(item.player.zone),
						zoneDisplayName: getZoneDisplayName(item.player.zone),
					}
				})
				// this.$set(this, 'otherAccountOptions', response.data.map(item => item.player))
				console.debug('OPTIONs are now', this.otherAccountOptions)
			}).catch(error => {
				console.error(error)
			}).then(() => {
				this.searchingOtherAccount = false
			})
		},
		getOtherAccountDisplayName() {
			const url = generateUrl('/apps/integration_trackmania/account/{accountId}', { accountId: this.tableState.other_account_id })
			axios.get(url).then((response) => {
				this.otherAccountDisplayName = response.data.displayName
			})
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
			this.infoLoadingPercent = 0
			this.loadingData = true
			const req = {}
			if (mapIdList !== null) {
				req.mapIdList = mapIdList
			}
			const url = generateUrl('/apps/integration_trackmania/pbs/raw')
			axios.post(url, req).then((response) => {
				this.$options.rawPbsToGetInfoOn = response.data
				this.$options.pbsWithInfo = []
				this.getPbsInfo(mapIdList)
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
				.then(result => {
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
			const req = {
				pbTimesByMapId,
			}
			if (this.tableState.other_account_id) {
				req.otherAccountId = this.tableState.other_account_id
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
							otherRecord: info.otherRecord,
						}
					}
					return c
				})
				this.$options.pbsWithInfo.push(...chunkWithInfo)
				this.infoLoadingPercent = parseInt(this.$options.pbsWithInfo.length / this.$options.rawPbsToGetInfoOn.length * 100)
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
		mergePbs(mapIdList) {
			this.pbs = this.pbs.filter(pb => !mapIdList.includes(pb.mapInfo.mapId))
			this.pbs.push(...formatPbs(this.$options.pbsWithInfo))
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
			this.$set(realPb.mapInfo, 'favoriteLoading', true)
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
				this.$set(realPb.mapInfo, 'favoriteLoading', false)
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

.accounts {
	display: flex;
	align-items: center;
	gap: 12px;

	.other-account-select,
	.other-account-input {
		width: 350px;
		margin-bottom: 8px !important;
	}

	.account-option {
		display: flex;
		align-items: center;
		gap: 4px;
	}

	.account-flag {
		height: 16px;
		width: auto;
	}

	.connected-as {
		display: flex;
		align-items: center;
		gap: 4px;
	}
}
</style>
