import Vue from 'vue'
import App from './App.vue'

import VueClipboard from 'vue-clipboard2'

Vue.mixin({ methods: { t, n } })
Vue.use(VueClipboard)

document.addEventListener('DOMContentLoaded', (event) => {
	const View = Vue.extend(App)
	new View().$mount('#content')
})
