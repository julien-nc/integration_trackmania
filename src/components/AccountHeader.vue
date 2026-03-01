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
			<NcButton @click="$emit('reload-filtered', )">
				<template #icon>
					<ReloadIcon />
				</template>
				{{ t('integration_trackmania', 'Reload data for current filtered record list') }}
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
			<OtherAccountSelect :other-account="otherAccount"
				@update:other-account="$emit('update:other-account', $event)" />
		</div>
	</div>
</template>

<script>
import ReloadIcon from 'vue-material-design-icons/Reload.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import OtherAccountSelect from './OtherAccountSelect.vue'

import moment from '@nextcloud/moment'

import { getFlagUrlFromCode } from '../utils.js'

export default {
	name: 'AccountHeader',

	components: {
		OtherAccountSelect,
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

	data() {
		return {
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
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		getFlagUrlFromCode(code) {
			return getFlagUrlFromCode(code)
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
	}
}
</style>
