<template>
	<NcModal size="normal"
		@close="$emit('close')">
		<div class="modal-content-wrapper">
			<NcButton class="map-name-button">
				<span
					:title="pb.mapInfo.cleanName"
					class="map-name"
					v-html="pb.mapInfo.htmlName" />
			</NcButton>
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
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { generateUrl } from '@nextcloud/router'
import { emit } from '@nextcloud/event-bus'
import { getFormattedBestMedal, getMedalImageUrl } from '../utils.js'

export default {
	name: 'MapDetailModal',

	components: {
		NcModal,
		NcButton,
	},

	props: {
		pb: {
			type: Object,
			required: true,
		},
	},

	emit: [
		'close',
	],

	data() {
		return {
		}
	},

	computed: {
		thumbnailUrl() {
			const directUrl = this.pb.mapInfo.thumbnailUrl
			const thumbnailId = directUrl.split('/').at(-1)
			return generateUrl('/apps/integration_trackmania/thumbnail/{id}', { id: thumbnailId })
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
	},

	methods: {
		getMedalImageUrl(medal) {
			return getMedalImageUrl(medal)
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
		color: var(--color-error);
	}
	.success {
		color: var(--color-success);
	}
}
</style>
