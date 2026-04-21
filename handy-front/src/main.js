import './assets/main.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import api from './plugins/axios'
import i18n from './plugins/i18n'
import { useAuthStore } from '@/stores/auth'

const app = createApp(App)

app.config.globalProperties.$api = api

// 1. Pinia MUST be installed first
const pinia = createPinia()
app.use(pinia)
app.use(i18n)

// 2. Initialize auth from localStorage BEFORE router
//    This guarantees userRole is available when the guard checks it
const authStore = useAuthStore()
authStore.initialize()

// 3. Router LAST — guards run using the data loaded above
app.use(router)

// 4. Mount
app.mount('#app')