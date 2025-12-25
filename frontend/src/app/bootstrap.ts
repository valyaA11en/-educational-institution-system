import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from '../router'
import { vuetify } from '../plugins/vuetify'
import { useAuthStore } from '../stores/auth'
import { useWsStore } from '../stores/ws'
import { can, canAny, canAll, hasRole, hasAnyRole } from '../helpers/permissions'

export function bootstrap() {
  const app = createApp(App)

  const pinia = createPinia()
  app.use(pinia)
  app.use(router)
  app.use(vuetify)

  // Make permission helpers available globally
  app.config.globalProperties.$can = can
  app.config.globalProperties.$canAny = canAny
  app.config.globalProperties.$canAll = canAll
  app.config.globalProperties.$hasRole = hasRole
  app.config.globalProperties.$hasAnyRole = hasAnyRole

  // Initialize auth store first
  const auth = useAuthStore()
  auth.init().then(async () => {
    // Initialize WebSocket store only if authenticated
    if (auth.isAuthenticated) {
      const ws = useWsStore()
      await ws.init()
    }
  })

  app.mount('#app')
}

