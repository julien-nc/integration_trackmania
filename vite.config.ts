/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	main: 'src/main.js',
	personalSettings: 'src/personalSettings.js'
}, {
	config: {
		// resolve: { dedupe: ['vue'] },
		css: {
			modules: {
				localsConvention: 'camelCase',
			},
		},
	},
})
