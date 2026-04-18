// src/main.js
import './assets/main.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import api from './plugins/axios'
import i18n from './plugins/i18n' // ⬅️ YOU ARE MISSING THIS LINE!
import { useAuthStore } from './stores/auth'

const app = createApp(App)

app.config.globalProperties.$api = api

app.use(createPinia())
app.use(router)
app.use(i18n) // ✅ NOW IT WORKS!

const authStore = useAuthStore()
authStore.initialize()

app.mount('#app')