import '@nextcloud/dialogs/style.css'

document.addEventListener('DOMContentLoaded', async (event) => {
	const { createApp } = await import('vue')
	const { default: App } = await import('./App.vue')

	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
