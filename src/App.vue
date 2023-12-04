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
					@connected="onConnected" />
			</div>
			<NcEmptyContent v-else-if="loadingData"
				class="main-empty-content"
				:name="t('integration_trackmania', 'Loading your records')">
				<template #icon>
					<NcLoadingIcon />
				</template>
			</NcEmptyContent>
			<MainContent v-else-if="hasData"
				:zone-names="zoneNames"
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

import PersonalSettings from './components/PersonalSettings.vue'
import MainContent from './components/MainContent.vue'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

const state = loadState('integration_trackmania', 'user-config')

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
	},

	provide() {
		return {
			pbs: () => {
				return this.$options.pbs
			},
		}
	},

	props: {
	},

	data() {
		return {
			state,
			loadingData: false,
			hasData: false,
			zoneNames: null,
		}
	},

	computed: {
		connected() {
			return !!this.state.user_name && !!this.state.core_token
		},
	},

	watch: {
	},

	beforeMount() {
		// console.debug('state', this.state)
	},

	mounted() {
		this.$options.pbs = []
		if (this.connected) {
			this.getPbs()
		}
		subscribe('get-nb-players', this.getNbPlayers)
	},

	beforeDestroy() {
		unsubscribe('get-nb-players', this.getNbPlayers)
	},

	methods: {
		onConnected(userName, accountId) {
			this.state.user_name = userName
			this.state.account_id = accountId
			this.state.core_token = 'plop'
			this.getPbs()
		},
		reloadData() {
			this.hasData = false
			this.$options.pbs = []
			this.getPbs()
		},
		getPbs() {
			this.loadingData = true
			const url = generateUrl('/apps/integration_trackmania/pbs')
			axios.get(url).then((response) => {
				this.zoneNames = this.getZoneNames(response.data[0])
				this.$options.pbs = response.data
				this.hasData = true
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to get data')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
				)
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
				// pb.mapInfo.nb_players = response.data
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to get player count')
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
</style>
