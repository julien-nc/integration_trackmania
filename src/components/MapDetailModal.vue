<template>
	<NcModal size="normal"
		@close="$emit('close')">
		<div class="modal-content-wrapper">
			<img :src="thumbnailUrl"
				class="thumbnail">
			<span>
				{{ pb.mapInfo.mapId }}
			</span>
			<span>
				{{ pb.mapInfo.uid }}
			</span>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import { generateUrl } from '@nextcloud/router'

export default {
	name: 'MapDetailModal',

	components: {
		NcModal,
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
	},

	watch: {
	},

	mounted() {
	},

	methods: {
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
}
</style>
