<template>
	<NcModal size="normal"
		:name="t('integration_trackmania', 'Skin of {name}', { name: displayName })"
		@close="$emit('close')">
		<div class="modal-content-wrapper skin-modal-wrapper">
			<template v-if="skin.hasThumbnail && !broken">
				<img :src="thumbnailUrl"
					:alt="skin.name"
					class="skin-thumbnail"
					@error="broken = true">
				<span class="skin-name">
					{{ skin.name }}
				</span>
			</template>
			<template v-else>
				<NcEmptyContent
					:name="skin.name"
					:description="t('integration_trackmania', 'No preview available for this skin')">
					<template #icon>
						<TrackmaniaIcon class="skin-icon" />
					</template>
				</NcEmptyContent>
				<span class="skin-owner">
					{{ t('integration_trackmania', 'Equipped by {name}', { name: displayName }) }}
				</span>
			</template>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import { generateUrl } from '@nextcloud/router'

import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

export default {
	name: 'SkinDetailModal',

	components: {
		NcModal,
		NcEmptyContent,
		TrackmaniaIcon,
	},

	props: {
		skin: {
			type: Object,
			required: true,
		},
		displayName: {
			type: String,
			required: true,
		},
	},

	emits: [
		'close',
	],

	data() {
		return {
			// becomes true when the thumbnail cannot be displayed, e.g. dead upstream storage object
			broken: false,
		}
	},

	computed: {
		thumbnailUrl() {
			return generateUrl('/apps/integration_trackmania/skin/{skinId}/thumbnail', { skinId: this.skin.skinId })
		},
	},

	watch: {
	},

	methods: {},
}
</script>

<style scoped lang="scss">
.skin-modal-wrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 16px;

	.skin-thumbnail {
		max-width: 500px;
		max-height: 500px;
		width: 100%;
	}

	.skin-icon {
		width: 150px;
	}

	.skin-name {
		margin-top: 8px;
		font-weight: bold;
	}

	.skin-owner {
		color: var(--color-text-maxcontrast);
	}
}
</style>
