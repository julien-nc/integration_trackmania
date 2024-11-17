<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div id="trackmania_prefs" class="section">
		<h2>
			<TrackmaniaIcon class="icon" />
			{{ t('integration_trackmania', 'Trackmania integration') }}
		</h2>
		<NcNoteCard type="info">
			{{ t('integration_trackmania', 'You can create an OAuth app in:') }}:
			&nbsp;
			<a :href="oauthSettingsUrl" target="_blank" class="external">
				{{ oauthSettingsUrl }}
			</a>
		</NcNoteCard>
		<div id="trackmania-content">
			<div class="line">
				<NcTextField
					id="trackmania-client-id"
					class="input"
					:value.sync="state.client_id"
					:label="t('integration_trackmania', 'Client ID')"
					:show-trailing-button="!!state.client_id"
					@update:value="onInput"
					@trailing-button-click="state.client_id = '' ; onInput()">
					<KeyIcon :size="20" />
				</NcTextField>
			</div>
			<div class="line">
				<NcTextField
					id="trackmania-client-secret"
					class="input"
					:value.sync="state.client_secret"
					type="password"
					:label="t('integration_trackmania', 'Client secret')"
					:show-trailing-button="!!state.client_secret"
					@update:value="onInput"
					@trailing-button-click="state.client_secret = '' ; onInput()">
					<KeyIcon :size="20" />
				</NcTextField>
			</div>
		</div>
	</div>
</template>

<script>
import KeyIcon from 'vue-material-design-icons/Key.vue'

import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'

import { delay } from '../utils.js'

export default {
	name: 'AdminSettings',

	components: {
		TrackmaniaIcon,
		NcNoteCard,
		NcTextField,
		KeyIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_trackmania', 'admin-config'),
			oauthSettingsUrl: 'https://api.trackmania.com/manager/create',
			redirect_uri: window.location.protocol + '//' + window.location.host,
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				const values = {
					client_id: this.state.client_id,
				}
				if (this.state.client_secret !== 'dummyClientSecret') {
					values.client_secret = this.state.client_secret
				}
				this.saveOptions(values, true)
			}, 2000)()
		},
		async saveOptions(values, sensitive = false) {
			if (sensitive) {
				await confirmPassword()
			}
			const req = {
				values,
			}
			const url = sensitive
				? generateUrl('/apps/integration_trackmania/sensitive-admin-config')
				: generateUrl('/apps/integration_trackmania/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_trackmania', 'Trackmania admin options saved'))
				})
				.catch((error) => {
					showError(t('integration_trackmania', 'Failed to save Trackmania admin options'))
					console.error(error)
				})
		},
	},
}
</script>

<style scoped lang="scss">
#trackmania_prefs {
	#trackmania-content {
		margin-left: 40px;
	}

	h2,
	.line {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.line {
		> .input {
			width: 400px;
		}
	}
}
</style>
