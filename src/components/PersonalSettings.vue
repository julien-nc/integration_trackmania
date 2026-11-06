<template>
	<div id="trackmania_prefs" class="section">
		<h2 v-if="showTitle">
			<TrackmaniaIcon class="icon" />
			{{ t('integration_trackmania', 'Trackmania integration') }}
		</h2>
		<br>
		<div id="trackmania-content">
			<NcNoteCard v-if="!state?.has_oauth_credentials"
				type="info">
				{{ t('integration_trackmania', 'Your administrator didn\'t set any Trackmania OAuth app credentials. The map author names won\'t be displayed') }}
			</NcNoteCard>
			<div id="trackmania-connect-block">
				<NcNoteCard v-if="!connected"
					type="info"
					:heading="t('integration_trackmania', 'Use a service account')">
					<p>
						{{ t('integration_trackmania', 'You have to use a dedicated Trackmania account as a service account. You can create a new account with this link:') }}
						<a href="https://www.trackmania.com/player/service-account" target="_blank" class="external">
							https://www.trackmania.com/player/service-account
						</a>
					</p>
				</NcNoteCard>
				<NcNoteCard v-if="!connected"
					type="info">
					{{ t('integration_trackmania', 'Your login/password is not stored in Nextcloud.') }}
				</NcNoteCard>
				<div v-show="!connected"
					id="login-form">
					<NcTextField
						id="trackmania-login"
						v-model="login"
						:label="t('integration_trackmania', 'Login')"
						:placeholder="t('integration_trackmania', 'Trackmania service account login')"
						@keyup.enter="connectWithCredentials">
						<template #icon>
							<AccountIcon :size="20" />
						</template>
					</NcTextField>
					<NcPasswordField
						id="trackmania-password"
						v-model="password"
						:label="t('integration_trackmania', 'Password')"
						:placeholder="t('integration_trackmania', 'Trackmania service account password')"
						@keyup.enter="connectWithCredentials">
						<template #icon>
							<LockIcon :size="20" />
						</template>
					</NcPasswordField>
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
				</div>
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

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		TrackmaniaIcon,
		NcButton,
		NcNoteCard,
		NcTextField,
		NcPasswordField,
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

	emits: ['connected'],

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
			return this.state.user_display_name || this.state.user_name
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
						this.state.user_display_name = response.data.user_display_name || this.state.user_name
						if (this.login && this.password && response.data.user_name === '') {
							showError(t('integration_trackmania', 'Invalid login/password'))
						} else if (response.data.user_name) {
							showSuccess(t('integration_trackmania', 'Successfully connected to Trackmania!'))
							this.state.user_id = response.data.user_id
							this.state.user_name = response.data.user_name
							this.state.core_token = 'dumdum'
							this.$emit('connected', {
								user_name: this.state.user_name,
								user_display_name: this.state.user_display_name,
								user_id: this.state.user_id,
								user_flag_code: response.data.user_flag_code,
								user_zone_name: response.data.user_zone_name,
							})
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

	h2 {
		justify-content: start;
		display: flex;
		align-items: center;
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		display: flex;
		align-items: center;
	}

	.trackmania-connected {
		flex: auto;
	}

	// the login form block spans up to 800px wide so its children fill it
	#trackmania-connect-block {
		display: flex;
		flex-direction: column;
		max-width: 800px;
		gap: 8px;
		// NcNoteCard has a built-in vertical margin, the block gap handles the spacing
		.notecard {
			margin: 0;
		}
		// the login fields and the connect button live in their own narrow block
		#login-form {
			display: flex;
			flex-direction: column;
			max-width: 400px;
			// NcButton is a flex block filling its container, keep it content-sized and left-aligned
			#trackmania-connect {
				width: fit-content;
				margin-top: 12px;
			}
		}
	}
}
</style>
