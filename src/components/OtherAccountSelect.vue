<template>
	<div class="other-account">
		<h3>
			<strong>{{ t('integration_trackmania', 'Compare yourself with another player') }}</strong>
		</h3>
		<NcSelect
			:model-value="otherAccount"
			:options="otherAccountOptions"
			class="other-account-select"
			:multiple="false"
			:label-outside="true"
			label="name"
			:filter-by="() => true"
			:loading="searchingOtherAccount"
			:aria-label-combobox="t('integration_trackmania', 'Search account')"
			:placeholder="t('integration_trackmania', 'Search on trackmania.io by player name')"
			:append-to-body="false"
			@update:model-value="onOtherAccountSelectChange"
			@search="onOtherAccountSearch">
			<template #option="option">
				<div class="account-option">
					<img v-if="option.flagUrl"
						class="account-flag"
						:src="option.flagUrl"
						:title="option.zoneDisplayName">
					<span>{{ option.name }}</span>
				</div>
			</template>
			<template #selected-option="option">
				<div class="account-option">
					<img v-if="option.flagUrl"
						class="account-flag"
						:src="option.flagUrl"
						:title="option.zoneDisplayName">
					<span>{{ option.name }}</span>
				</div>
			</template>
		</NcSelect>
		<NcTextField
			:model-value="otherAccount?.id ?? ''"
			type="text"
			:label="t('integration_trackmania', 'Account ID')"
			:show-trailing-button="!!otherAccount?.id"
			class="other-account-input"
			:placeholder="t('integration_trackmania', 'account ID')"
			@keyup.enter="onAccountIdSubmit"
			@trailing-button-click="clearOtherAccount" />
	</div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { getFlagCode, getFlagUrlFromZone, getFlagUrlFromCode, getZoneDisplayName } from '../utils.js'

export default {
	name: 'OtherAccountSelect',

	components: {
		NcTextField,
		NcSelect,
	},

	props: {
		otherAccount: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			otherAccountOptions: [],
			otherAccountSearchQuery: '',
			searchingOtherAccount: false,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		clearOtherAccount() {
			this.$emit('update:other-account', {})
		},
		onAccountIdSubmit(e) {
			const submittedAccountId = e.target.value
			console.debug('[trackmania] onAccountIdSubmit', submittedAccountId)
			// look for account name
			const url = generateUrl('/apps/integration_trackmania/account/search/{name}', { name: submittedAccountId })
			axios.get(url).then((response) => {
				if (response.data.length === 1) {
					console.debug('[trackmania] onAccountIdSubmit name search result', response.data[0])
					this.$emit('update:other-account', {
						id: response.data[0].player.id,
						name: response.data[0].player.name,
						flagCode: getFlagCode(response.data[0].player.zone),
						flagUrl: getFlagUrlFromZone(response.data[0].player.zone),
						zoneDisplayName: getZoneDisplayName(response.data[0].player.zone),
					})
				} else {
					this.$emit('update:other-account', {
						id: submittedAccountId,
						name: t('integration_trackmania', 'Unknown player name'),
						flagCode: 'WOR',
						flagUrl: getFlagUrlFromCode('WOR'),
						zoneDisplayName: 'World',
					})
				}
			}).catch(error => {
				console.error(error)
				this.$emit('update:other-account', {
					id: submittedAccountId,
					name: t('integration_trackmania', 'Unknown player name'),
					flagCode: 'WOR',
					flagUrl: getFlagUrlFromCode('WOR'),
					zoneDisplayName: 'World',
				})
			})
		},
		onOtherAccountSelectChange(value) {
			this.$emit('update:other-account', value ?? {})
		},
		onOtherAccountSearch: debounce(function(query) {
			this.otherAccountSearchQuery = query
			if (query !== '') {
				this.otherAccountSearch()
			}
		}, 500),
		otherAccountSearch() {
			this.searchingOtherAccount = true
			const url = generateUrl('/apps/integration_trackmania/account/search/{name}', { name: this.otherAccountSearchQuery })
			axios.get(url).then((response) => {
				this.otherAccountOptions = response.data.map(item => {
					return {
						...item.player,
						flagCode: getFlagCode(item.player.zone),
						flagUrl: getFlagUrlFromZone(item.player.zone),
						zoneDisplayName: getZoneDisplayName(item.player.zone),
					}
				})
				// this.otherAccountOptions = response.data.map(item => item.player)
				console.debug('OPTIONs are now', this.otherAccountOptions)
			}).catch(error => {
				console.error(error)
			}).then(() => {
				this.searchingOtherAccount = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
.other-account-select,
.other-account-input {
	width: 350px !important;
	margin-bottom: 8px !important;
}

.account-option {
	display: flex;
	align-items: center;
	gap: 4px;
}

.account-flag {
	height: 16px;
	width: auto;
}
</style>
