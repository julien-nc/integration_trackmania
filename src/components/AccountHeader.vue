<template>
	<div class="account-header">
		<NcNoteCard v-if="!userState?.has_oauth_credentials"
			type="info">
			{{ t('integration_trackmania', 'Your administrator didn\'t set any Trackmania OAuth app credentials. The map author names won\'t be displayed') }}
		</NcNoteCard>
		<div class="header">
			<NcButton @click="$emit('reload')">
				<template #icon>
					<ReloadIcon />
				</template>
				{{ t('integration_trackmania', 'Reload data') }}
			</NcButton>
			<NcButton @click="$emit('disconnect')">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('integration_trackmania', 'Disconnect') }}
			</NcButton>
			<span v-if="lastUpdateTime" class="load-info">
				{{ t('integration_trackmania', 'Last update: {time} (loaded in {duration})', { time: formattedUpdateTime, duration: formattedDuration }) }}
			</span>
		</div>
		<div class="accounts">
			<div class="connected-as">
				{{ t('integration_trackmania', 'Connected as {name}', { name: userState.user_name }) }}
				<img v-if="userState.user_flag_code"
					class="account-flag"
					:src="getFlagUrlFromCode(userState.user_flag_code)"
					:title="userState.user_zone_name">
				<span>({{ userState.account_id }})</span>
			</div>
			<img v-if="skin"
				class="skin-thumb"
				:src="skinThumbnailUrl"
				:alt="skin.name"
				:title="skin.name"
				@click="openSkinModal(skin)"
				@error="onSkinError(false)">
			<img v-if="otherSkin"
				class="skin-thumb"
				:src="otherSkinThumbnailUrl"
				:alt="otherSkin.name"
				:title="otherSkin.name"
				@click="openSkinModal(otherSkin)"
				@error="onSkinError(true)">
			<OtherAccountSelect :other-account="otherAccount"
				@update:other-account="$emit('update:other-account', $event)" />
		</div>
		<SkinDetailModal v-if="modalSkin"
			:skin="modalSkin"
			:display-name="modalSkinDisplayName"
			@close="modalSkin = null" />
	</div>
</template>

<script>
import ReloadIcon from 'vue-material-design-icons/Reload.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import OtherAccountSelect from './OtherAccountSelect.vue'
import SkinDetailModal from './SkinDetailModal.vue'

import moment from '@nextcloud/moment'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import { getFlagUrlFromCode } from '../utils.js'

export default {
	name: 'AccountHeader',

	components: {
		OtherAccountSelect,
		SkinDetailModal,
		NcButton,
		NcNoteCard,
		ReloadIcon,
		CloseIcon,
	},

	props: {
		otherAccount: {
			type: Object,
			required: true,
		},
		userState: {
			type: Object,
			required: true,
		},
		lastUpdateTime: {
			type: Date,
			default: null,
		},
		lastLoadDurationMs: {
			type: Number,
			default: null,
		},
	},

	emits: ['reload', 'disconnect', 'update:other-account'],

	data() {
		return {
			skin: null,
			otherSkin: null,
			modalSkin: null,
			isModalSkinOther: false,
		}
	},

	computed: {
		formattedUpdateTime() {
			if (!this.lastUpdateTime) {
				return ''
			}
			return moment(this.lastUpdateTime).format('LLL')
		},
		formattedDuration() {
			if (this.lastLoadDurationMs === null) {
				return ''
			}
			const seconds = Math.round(this.lastLoadDurationMs / 100) / 10
			return seconds + 's'
		},
		skinThumbnailUrl() {
			return this.skinThumbnailUrlFor(this.skin)
		},
		otherSkinThumbnailUrl() {
			return this.skinThumbnailUrlFor(this.otherSkin)
		},
		modalSkinDisplayName() {
			return this.isModalSkinOther ? (this.otherAccount?.name ?? this.modalSkin.skinId) : this.userState.user_name
		},
	},

	watch: {
		otherAccount(newVal) {
			if (newVal?.id) {
				this.getSkin(newVal.id)
			} else {
				this.otherSkin = null
			}
		},
	},

	mounted() {
		this.getSkin()
		if (this.otherAccount?.id) {
			this.getSkin(this.otherAccount.id)
		}
	},

	methods: {
		getFlagUrlFromCode(code) {
			return getFlagUrlFromCode(code)
		},
		skinThumbnailUrlFor(skin) {
			return skin
				? generateUrl('/apps/integration_trackmania/skin/{skinId}/thumbnail', { skinId: skin.skinId })
				: ''
		},
		async getSkin(accountId = '') {
			let url = generateUrl('/apps/integration_trackmania/skin')
			if (accountId) {
				url += '?accountId=' + encodeURIComponent(accountId)
			}
			try {
				const response = await axios.get(url)
				const skin = response.data?.skinId ? response.data : null
				this.setSkin(accountId, skin)
			} catch (error) {
				// the skin is a nice-to-have, the equipped list may just not exist upstream
				console.debug('Failed to get the equipped skin', error)
				this.setSkin(accountId, null)
			}
		},
		setSkin(accountId, skin) {
			if (accountId) {
				this.otherSkin = skin
			} else {
				this.skin = skin
			}
		},
		openSkinModal(skin) {
			// remember which account the skin belongs to, the IDs may collide between the two accounts
			this.isModalSkinOther = skin === this.otherSkin
			this.modalSkin = skin
		},
		onSkinError(isOtherSkin) {
			// e.g. no TGA conversion possible on the server, do not show a broken image
			console.debug('Failed to display a skin thumbnail')
			if (isOtherSkin) {
				this.otherSkin = null
			} else {
				this.skin = null
			}
		},
	},
}
</script>

<style scoped lang="scss">
.account-header {
	padding-left: 30px;

	.header {
		margin-bottom: 24px;
		display: flex;
		gap: 8px;
		align-items: center;

		.load-info {
			margin-left: 8px;
			color: var(--color-text-maxcontrast);
			font-size: 0.9em;
		}
	}

	.accounts {
		display: flex;
		align-items: center;
		gap: 12px;

		.account-flag {
			height: 16px;
			width: auto;
		}

		.connected-as {
			display: flex;
			align-items: center;
			gap: 4px;
		}

		.skin-thumb {
			height: 72px;
			width: 72px;
			object-fit: contain;
			border-radius: 8px;
			border: 1px solid var(--color-border);
			background: var(--color-background-dark);
			cursor: pointer;
		}
	}
}
</style>
