<template>
	<div id="trackmania_prefs" class="section">
		<h2 v-if="showTitle">
			<TrackmaniaIcon class="icon" />
			{{ t('integration_trackmania', 'Trackmania integration') }}
		</h2>
		<br>
		<div id="trackmania-content">
			<div id="trackmania-connect-block">
				<p v-if="!connected" class="settings-hint">
					{{ t('integration_trackmania', 'Your login/password is not stored in Nextcloud.') }}
				</p>
				<div v-show="!connected"
					class="line">
					<label
						for="trackmania-login">
						<AccountIcon :size="20" class="icon" />
						{{ t('integration_trackmania', 'Login') }}
					</label>
					<input id="trackmania-login"
						v-model="login"
						type="text"
						:placeholder="t('integration_trackmania', 'Trackmania login')"
						@keyup.enter="connectWithCredentials">
				</div>
				<div v-show="!connected"
					class="line">
					<label
						for="trackmania-password">
						<LockIcon :size="20" class="icon" />
						{{ t('integration_trackmania', 'Password') }}
					</label>
					<input id="trackmania-password"
						v-model="password"
						type="password"
						:placeholder="t('integration_trackmania', 'Trackmania password')"
						@keyup.enter="connectWithCredentials">
				</div>
				<NcButton v-if="!connected"
					id="trackmania-connect"
					:disabled="loading === true || !login || !password"
					:class="{ loading }"
					@click="connectWithCredentials">
					<template #icon>
						<OpenInNewIcon />
					</template>
					{{ t('integration_trackmania', 'Connect to Trackmania') }}
				</NcButton>
				<div v-if="connected" class="line">
					<label class="trackmania-connected">
						<CheckIcon :size="20" class="icon" />
						{{ t('integration_trackmania', 'Connected as {user}', { user: connectedDisplayName }) }}
					</label>
					<NcButton id="trackmania-rm-cred" @click="onLogoutClick">
						<template #icon>
							<CloseIcon />
						</template>
						{{ t('integration_trackmania', 'Disconnect from Trackmania') }}
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import LockIcon from 'vue-material-design-icons/Lock.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

import TrackmaniaIcon from './icons/TrackmaniaIcon.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		TrackmaniaIcon,
		NcButton,
		OpenInNewIcon,
		CloseIcon,
		CheckIcon,
		LockIcon,
		AccountIcon,
	},

	props: {
		showTitle: {
			type: Boolean,
			default: true,
		},
		config: {
			type: Object,
			default: () => null,
		},
	},

	data() {
		return {
			state: this.config !== null ? { ...this.config } : loadState('integration_trackmania', 'user-config'),
			loading: false,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_trackmania/oauth-redirect'),
			login: '',
			password: '',
		}
	},

	computed: {
		connected() {
			return !!this.state.core_token
				&& !!this.state.user_name
		},
		connectedDisplayName() {
			return this.state.user_name
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onLogoutClick() {
			this.state.core_token = ''
			this.login = ''
			this.password = ''
			this.saveOptions({ core_token: '' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_trackmania/config')
			axios.put(url, req)
				.then((response) => {
					if (response.data.user_name !== undefined) {
						this.state.user_name = response.data.user_name
						if (this.login && this.password && response.data.user_name === '') {
							showError(t('integration_trackmania', 'Invalid login/password'))
						} else if (response.data.user_name) {
							showSuccess(t('integration_trackmania', 'Successfully connected to Trackmania!'))
							this.state.user_id = response.data.user_id
							this.state.user_name = response.data.user_name
							this.state.core_token = 'dumdum'
							this.$emit('connected', this.state.user_name, this.state.user_id)
						}
					} else {
						showSuccess(t('integration_trackmania', 'Trackmania options saved'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_trackmania', 'Failed to save Trackmania options')
						+ ': ' + (error.response?.request?.responseText ?? ''),
					)
					console.error(error)
				})
				.then(() => {
					this.loading = false
				})
		},
		connectWithCredentials() {
			this.loading = true
			this.saveOptions({
				login: this.login,
				password: this.password,
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
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 300px;
		}
	}
}
</style>
