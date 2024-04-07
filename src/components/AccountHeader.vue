<template>
	<div class="account-header">
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import OtherAccountSelect from './OtherAccountSelect.vue'

import { getFlagUrlFromCode } from '../utils.js'

export default {
	name: 'AccountHeader',

	components: {
		OtherAccountSelect,
		NcButton,
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
	},

	data() {
		return {
		}
	},

	computed: {
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
