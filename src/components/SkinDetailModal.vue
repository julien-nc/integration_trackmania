<template>
	<NcModal size="normal"
		:name="t('integration_trackmania', 'Skin of {name}', { name: displayName })"
		@close="$emit('close')">
		<div class="modal-content-wrapper skin-modal-wrapper">
			<img :src="thumbnailUrl"
				:alt="skin.name"
				class="skin-thumbnail">
			<span class="skin-name">
				{{ skin.name }}
			</span>
			<span class="skin-owner">
				{{ t('integration_trackmania', 'Equipped by {name}', { name: displayName }) }}
			</span>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'

import { generateUrl } from '@nextcloud/router'

export default {
	name: 'SkinDetailModal',

	components: {
		NcModal,
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
		return {}
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

	.skin-name {
		margin-top: 8px;
		font-weight: bold;
	}

	.skin-owner {
		color: var(--color-text-maxcontrast);
	}
}
</style>
