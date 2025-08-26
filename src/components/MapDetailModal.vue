<template>
	<NcModal size="normal"
		:name="t('integration_trackmania', 'Map details for map {mapName}', { mapName: pb.mapInfo.cleanName })"
		@close="$emit('close')">
		<div class="modal-content-wrapper">
			<NcButton class="map-name-button">
				<span
					:title="pb.mapInfo.cleanName"
					class="map-name"
					v-html="pb.mapInfo.htmlName" />
			</NcButton>
			<span :title="pb.mapInfo.author">
				{{ t('integration_trackmania', 'By {authorName}', { authorName: pb.mapInfo.authorName }) }}
			</span>
			<img :src="thumbnailUrl"
				class="thumbnail">
			<span>
				{{ t('integration_trackmania', 'Map Id') }}: {{ pb.mapInfo.mapId }}
			</span>
			<span>
				{{ t('integration_trackmania', 'Map Uid') }}: {{ pb.mapInfo.uid }}
			</span>
			<span v-if="pb.mapInfo.nb_players">
				{{ t('integration_trackmania', 'My position') }}: {{ pb.recordPosition.zones.World }} / {{ pb.mapInfo.nb_players }} ({{ positionPercent }})
			</span>
			<span>
				{{ t('integration_trackmania', 'My time') }}: {{ pb.record.recordScore.formattedTime }}
			</span>
			<span :title="formattedBestMedal"
				class="medal">
				{{ t('integration_trackmania', 'My medal') }}:
				<span>{{ pb.record.formattedMedal }}</span>
				<img :src="getMedalImageUrl(pb.record.medal)">
			</span>
			<span :class="{ medalTime: true, success: pb.record.recordScore.time - pb.mapInfo.authorTime < 0 }">
				{{ pb.mapInfo.formattedAuthorTime }}
			</span>
			<span :class="{ medalTime: true, success: pb.record.recordScore.time - pb.mapInfo.goldTime < 0 }">
				{{ pb.mapInfo.formattedGoldTime }}
			</span>
			<span :class="{ medalTime: true, success: pb.record.recordScore.time - pb.mapInfo.silverTime < 0 }">
				{{ pb.mapInfo.formattedSilverTime }}
			</span>
			<span :class="{ medalTime: true, success: pb.record.recordScore.time - pb.mapInfo.bronzeTime < 0 }">
				{{ pb.mapInfo.formattedBronzeTime }}
			</span>
			<div v-if="otherGhostUrl" class="ghost-url">
				<label>{{ t('integration_trackmania', 'Ghost URL of {accountId}', { accountId: configState.other_account_name || configState.other_account_id }) }}</label>
				<a :href="otherGhostUrl">
					{{ otherGhostUrl }}
				</a>
			</div>
			<NcTextField
				v-model="accountIdForGhostUrl"
				type="text"
				:label="t('integration_trackmania', 'Get ghost of account')"
				:show-trailing-button="!!accountIdForGhostUrl"
				class="text-input"
				:placeholder="t('integration_trackmania', 'account ID')"
				@keyup.enter="onGetGhostUrl"
				@trailing-button-click="accountIdForGhostUrl = ''" />
			<a v-if="ghostUrl" :href="ghostUrl">
				{{ ghostUrl }}
			</a>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { getFormattedBestMedal, getMedalImageUrl } from '../utils.js'

export default {
	name: 'MapDetailModal',

	components: {
		NcModal,
		NcButton,
		NcTextField,
	},

	props: {
		pb: {
			type: Object,
			required: true,
		},
		configState: {
			type: Object,
			required: true,
		},
	},

	emit: [
		'close',
	],

	data() {
		return {
			otherGhostUrl: '',
			accountIdForGhostUrl: '',
			ghostUrl: '',
		}
	},

	computed: {
		thumbnailUrl() {
			return generateUrl('/apps/integration_trackmania/map/{mapId}/thumbnail', { mapId: this.pb.mapInfo.mapId })
		},
		positionPercent() {
			if (this.pb.mapInfo.nb_players) {
				const pc = (this.pb.recordPosition.zones.World / this.pb.mapInfo.nb_players * 100).toFixed(2)
				return t('integration_trackmania', 'Top {pc} %', { pc })
			}
			return ''
		},
		formattedBestMedal() {
			return getFormattedBestMedal(this.pb)
		},
	},

	watch: {
	},

	beforeMount() {
		if (!this.pb.mapInfo.nb_players) {
			emit('get-nb-players', this.pb)
		}
	},

	mounted() {
		this.getOtherGhostUrl()
	},

	methods: {
		getMedalImageUrl(medal) {
			return getMedalImageUrl(medal)
		},
		getOtherGhostUrl() {
			if (this.configState.other_account_id) {
				const url = generateUrl('/apps/integration_trackmania/map/{mapId}/record/{accountId}', { mapId: this.pb.mapInfo.mapId, accountId: this.configState.other_account_id })
				axios.get(url).then((response) => {
					this.otherGhostUrl = response.data[0]?.url
				}).catch((error) => {
					showError(
						t('integration_trackmania', 'Failed to get ghost URL for other account')
						+ ': ' + (error.response?.request?.responseText ?? ''),
					)
					console.error(error)
				})
			}
		},
		onGetGhostUrl() {
			const url = generateUrl('/apps/integration_trackmania/map/{mapId}/record/{accountId}', { mapId: this.pb.mapInfo.mapId, accountId: this.accountIdForGhostUrl })
			axios.get(url).then((response) => {
				this.ghostUrl = response.data[0]?.url
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to get ghost URL')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
.modal-content-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 16px;

	.ghost-url {
		display: flex;
		flex-direction: column;
		align-items: center;
		> label {
			font-weight: bold;
		}
		> a {
			text-align: center;
		}
	}
	.thumbnail {
		max-height: 350px;
		max-width: 80%;
	}
	.map-name {
		font-weight: bold;
	}
	.map-name-button {
		background-color: #909090;
	}
	.medal {
		display: flex;
		align-items: center;
		gap: 4px;
		img {
			width: 32px;
		}
	}
	.medalTime {
		color: var(--color-border-error, --color-error);
	}
	.success {
		color: var(--color-border-success, --color-success);
	}
}
</style>
