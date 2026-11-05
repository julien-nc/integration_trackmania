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
			<span v-if="lastUpdateTime" class="load-info">
				{{ t('integration_trackmania', 'Last update: {time} (loaded in {duration})', { time: formattedUpdateTime, duration: formattedDuration }) }}
			</span>
		</div>
		<div class="accounts">
			<div class="tm-card player-card">
				<h3 class="card-title">
					{{ t('integration_trackmania', 'My account') }}
				</h3>
				<NcChip class="player-name-chip"
					:no-close="true">
					<template #icon>
						<img v-if="userState.user_flag_code"
							class="chip-flag"
							:src="getFlagUrlFromCode(userState.user_flag_code)"
							:title="userState.user_zone_name"
							alt="">
					</template>
					{{ displayName }}
				</NcChip>
				<NcChip class="account-id-chip"
					:no-close="true">
					{{ userState.account_id }}
				</NcChip>
				<div class="skin-tiles">
					<div v-if="skin && skin.hasThumbnail"
						class="skin-thumb"
						:title="skin.name"
						@click="openSkinModal(skin)">
						<img :src="skinThumbnailUrl"
							:alt="skin.name"
							@error="onSkinError(false)">
					</div>
					<div v-else-if="skin && skin.hasSkin"
						class="skin-thumb skin-text-thumb"
						:title="skin.name"
						@click="openSkinModal(skin)">
						<span>{{ skin.name }}</span>
					</div>
					<div v-else-if="skin"
						class="skin-thumb skin-text-thumb skin-no-skin"
						:title="displayName">
						<span>{{ t('integration_trackmania', 'No skin equipped') }}</span>
					</div>
				</div>
				<NcButton @click="$emit('disconnect')">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('integration_trackmania', 'Disconnect') }}
				</NcButton>
			</div>
			<div class="tm-card compare-card">
				<h3 class="card-title">
					{{ t('integration_trackmania', 'Compare yourself with another player') }}
				</h3>
				<OtherAccountSelect :other-account="otherAccount"
					@update:other-account="$emit('update:other-account', $event)" />
				<div class="skin-tiles">
					<div v-if="otherSkin && otherSkin.hasThumbnail"
						class="skin-thumb"
						:title="otherSkin.name"
						@click="openSkinModal(otherSkin)">
						<img :src="otherSkinThumbnailUrl"
							:alt="otherSkin.name"
							@error="onSkinError(true)">
					</div>
					<div v-else-if="otherSkin && otherSkin.hasSkin"
						class="skin-thumb skin-text-thumb"
						:title="otherSkin.name"
						@click="openSkinModal(otherSkin)">
						<span>{{ otherSkin.name }}</span>
					</div>
					<div v-else-if="otherSkin"
						class="skin-thumb skin-text-thumb skin-no-skin"
						:title="otherAccount?.name">
						<span>{{ t('integration_trackmania', 'No skin equipped') }}</span>
					</div>
				</div>
			</div>
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
import NcChip from '@nextcloud/vue/components/NcChip'
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
		NcChip,
		NcNoteCard,
		ReloadIcon,
		CloseIcon,
	},

	props: {
		otherAccount: {
			type: Object,
			default: null,
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
		displayName() {
			return this.userState.user_display_name || this.userState.user_name
		},
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
			return this.isModalSkinOther ? (this.otherAccount?.name ?? this.modalSkin.skinId) : this.displayName
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
				const data = response.data
				// a missing equipped skin is a known upstream state: show the placeholder instead of hiding the block
				const skin = data?.hasSkin === true
					? data
					: (data?.hasSkin === false ? { hasSkin: false } : null)
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
			// the thumbnail cannot be converted or fetched, degrade to the no-preview tile
			console.debug('Failed to display a skin thumbnail')
			const skin = isOtherSkin ? this.otherSkin : this.skin
			if (skin) {
				skin.hasThumbnail = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.account-header {
	.header {
		justify-content: center;
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
		flex-wrap: wrap;
		gap: 16px;
		align-items: stretch;
		justify-content: center;
	}

	.player-card,
	.compare-card {
		padding: 20px;
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 16px;
		margin-bottom: 16px;

		.card-title {
			margin: 0;
			text-align: center;
			color: var(--color-main-text);
		}

		.chip-flag {
			height: 16px;
			width: auto;
		}

		:deep(.player-name-chip) {
			color: var(--color-main-text);
		}

		:deep(.account-id-chip) {
			font-family: var(--font-monospace, monospace);
			font-size: 0.85em;
			opacity: 0.9;
		}
	}

	.skin-tiles {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	.skin-thumb {
		height: 84px;
		width: 84px;
		border-radius: var(--tm-radius-small);
		border: 1px solid var(--tm-ring);
		background: rgba(0, 0, 0, 0.25);
		cursor: pointer;
		overflow: hidden;
		transition: transform 0.12s ease, border-color 0.12s ease, box-shadow 0.12s ease;

		img {
			height: 100%;
			width: 100%;
			object-fit: contain;
		}

		&:not(.skin-no-skin):hover {
			border-color: var(--tm-cyan);
			box-shadow: 0 0 14px rgba(31, 212, 232, 0.35);
			transform: translateY(-2px);
		}
	}

	.skin-text-thumb {
		display: flex;
		align-items: center;
		padding: 8px;
		font-size: 0.7em;
		text-align: center;
		word-break: break-word;
		color: var(--color-text-maxcontrast);
		background: var(--tm-tile-bg);
	}

	.skin-no-skin {
		cursor: default;
		border-style: dashed;
	}
}
</style>
