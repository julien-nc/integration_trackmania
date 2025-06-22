/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
document.addEventListener('DOMContentLoaded', async (event) => {
	const { createApp } = await import('vue')
	const { default: PersonalSettings } = await import('./components/PersonalSettings.vue')

	const app = createApp(PersonalSettings)
	app.mixin({ methods: { t, n } })
	app.mount('#trackmania_prefs')
})
