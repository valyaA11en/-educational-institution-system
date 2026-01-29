import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from '../App.vue'
import router from '../router'
import { vuetify } from '../plugins/vuetify'
import { i18n } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useWsStore } from '../stores/ws'
import { can, canAny, canAll, hasRole, hasAnyRole } from '../helpers/permissions'

export function bootstrap() {
  console.log('🚀 Starting bootstrap...')
  const app = createApp(App)
  console.log('✅ Vue app created')

  const pinia = createPinia()
  app.use(pinia)
  console.log('✅ Pinia installed')
  
  app.use(router)
  console.log('✅ Router installed')
  
  app.use(vuetify)
  console.log('✅ Vuetify installed')
  
  app.use(i18n)
  console.log('✅ i18n installed')

  // Make permission helpers available globally
  app.config.globalProperties.$can = can
  app.config.globalProperties.$canAny = canAny
  app.config.globalProperties.$canAll = canAll
  app.config.globalProperties.$hasRole = hasRole
  app.config.globalProperties.$hasAnyRole = hasAnyRole
  console.log('✅ Permission helpers installed')

  console.log('📦 Mounting app to #app...')
  app.mount('#app')
  console.log('✅ App mounted successfully!')

  // Initialize auth store after mount
  setTimeout(() => {
    console.log('🔐 Initializing auth store...')
    const auth = useAuthStore()
    auth.init().then(async () => {
      console.log('✅ Auth store initialized')
      // Initialize WebSocket store only if authenticated and configured
      if (auth.isAuthenticated) {
        const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY
        const isValidKey = pusherKey && 
                           pusherKey !== 'local-key' && 
                           pusherKey !== 'app-key' && 
                           pusherKey.trim() !== ''
        
        if (isValidKey) {
          console.log('🔌 Initializing WebSocket...')
          const ws = useWsStore()
          await ws.init()
          console.log('✅ WebSocket initialized')
        } else {
          console.log('⚠️ WebSocket not configured, skipping initialization')
        }
      }
    }).catch(err => {
      console.error('❌ Auth store init failed:', err)
    })
  }, 100)
}

