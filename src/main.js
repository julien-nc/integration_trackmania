import { createApp } from 'vue'
import App from './App.vue'
import '@nextcloud/dialogs/style.css'

document.addEventListener('DOMContentLoaded', (event) => {
	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
