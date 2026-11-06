<template>
	<NcModal size="normal"
		:name="t('integration_trackmania', 'Map details for map {mapName}', { mapName: pb.mapInfo.cleanName })"
		@close="$emit('close')">
		<div class="modal-content-wrapper">
			<NcButton class="map-name-button">
				<!-- eslint-disable vue/no-v-html -->
				<span
					:title="pb.mapInfo.cleanName"
					class="map-name"
					v-html="pb.mapInfo.htmlName" />
				<!-- eslint-enable -->
			</NcButton>
			<span :title="pb.mapInfo.author">
				{{ t('integration_trackmania', 'By {authorName}', { authorName: pb.mapInfo.authorName ?? '??' }) }}
			</span>
			<img :src="thumbnailUrl"
				class="thumbnail">
			<div class="mapInfo">
				<span class="lineLabel">
					{{ t('integration_trackmania', 'Map Id') }}
				</span>
				<span>
					{{ pb.mapInfo.mapId }}
				</span>
				<span class="lineLabel">
					{{ t('integration_trackmania', 'Map Uid') }}
				</span>
				<span>
					{{ pb.mapInfo.uid }}
				</span>
				<template v-if="pb.mapInfo.nb_players">
					<span class="lineLabel">
						{{ t('integration_trackmania', 'My position') }}
					</span>
					<span>
						{{ pb.recordPosition.zones.World }} / {{ pb.mapInfo.nb_players }} ({{ positionPercent }})
					</span>
				</template>
				<span class="lineLabel">
					{{ t('integration_trackmania', 'My time') }}
				</span>
				<span>
					{{ pb.record.recordScore.formattedTime }}
				</span>
				<span class="lineLabel">
					{{ t('integration_trackmania', 'My medal') }}
				</span>
				<span
					:title="formattedBestMedal"
					class="medal">
					<span>{{ pb.record.formattedMedal }}</span>
					<img :src="getMedalImageUrl(pb.record.medal)">
				</span>
				<template v-for="medalTime in medalTimes"
					:key="medalTime.label">
					<span class="lineLabel">
						{{ medalTime.label }}
					</span>
					<span class="medalTime">
						{{ medalTime.formattedTime }}
						(<span
							class="medalDelta"
							:class="{ success: medalTime.delta < 0, error: medalTime.delta > 0 }">
							{{ medalTime.formattedDelta }}
						</span>)
					</span>
				</template>
				<template v-if="isWorldRecordHolder || pb.worldRecord || worldRecordLoading">
					<span class="lineLabel">
						{{ t('integration_trackmania', 'World record') }}
					</span>
					<span v-if="isWorldRecordHolder" class="wrapperHolder">
						{{ t('integration_trackmania', 'You are the world record holder') }}
					</span>
					<span v-else-if="pb.worldRecord" class="worldRecord">
						{{ pb.worldRecord.formattedTime }}
						<span
							class="wrDelta"
							:class="{ success: pb.worldRecord.delta <= 0, error: pb.worldRecord.delta > 0 }">
							{{ pb.worldRecord.formattedDelta }}
						</span>
						<span
							v-if="pb.worldRecord.name"
							:title="t('integration_trackmania', 'World record holder')">
							{{ t('integration_trackmania', 'by {holderName}', { holderName: pb.worldRecord.name }) }}
						</span>
						<span
							class="wrDate"
							:title="t('integration_trackmania', 'World record date')">
							{{ pb.worldRecord.formattedDate }}
						</span>
					</span>
					<span v-else-if="worldRecordLoading" class="wrapperHolder">
						<NcLoadingIcon :size="20" /></span>
				</template>
			</div>
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
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

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
		NcLoadingIcon,
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

	emits: [
		'close',
		'world-record-fetched',
	],

	data() {
		return {
			otherGhostUrl: '',
			accountIdForGhostUrl: '',
			ghostUrl: '',
			worldRecordLoading: false,
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
		isWorldRecordHolder() {
			return this.pb.recordPosition?.zones?.World === 1
		},
		medalTimes() {
			const medalTimes = this.pb.mapInfo.medalTimes ?? {}
			return [medalTimes.author, medalTimes.gold, medalTimes.silver, medalTimes.bronze].filter(t => t)
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
		this.getWorldRecord()
	},

	methods: {
		getMedalImageUrl(medal) {
			return getMedalImageUrl(medal)
		},
		getWorldRecord() {
			if (this.pb.worldRecord || this.isWorldRecordHolder) {
				return
			}
			this.worldRecordLoading = true
			const url = generateUrl('/apps/integration_trackmania/map/{mapUid}/world-record', { mapUid: this.pb.mapInfo.uid })
			axios.get(url).then((response) => {
				if (response.data) {
					this.$emit('world-record-fetched', this.pb, response.data)
				}
				this.worldRecordLoading = false
			}).catch((error) => {
				showError(
					t('integration_trackmania', 'Failed to get world record')
					+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.error(error)
				this.worldRecordLoading = false
			})
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
		border-radius: var(--tm-radius-small);
		border: 1px solid var(--tm-ring);
	}
	.map-name {
		font-weight: bold;
	}
	.map-name-button {
		background-color: #909090;
	}
	.mapInfo {
		display: grid;
		grid-template-columns: auto auto;
		justify-content: center;
		align-items: baseline;
		column-gap: 12px;
		row-gap: 6px;

		.lineLabel {
			text-align: end;
			color: var(--color-text-maxcontrast);
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
			white-space: nowrap;
		}
		.medalDelta {
			margin-inline-start: 4px;
		}
		.worldRecord {
			display: flex;
			gap: 4px;
			white-space: nowrap;
		}
		.wrapperHolder {
			white-space: nowrap;
		}
	}
	.success {
		color: var(--tm-green);
	}
	.error {
		color: var(--tm-red);
	}
}
</style>
