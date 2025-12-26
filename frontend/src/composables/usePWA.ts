import { ref, onMounted } from 'vue'

const isSupported = ref(false)
const isInstalled = ref(false)
const registration = ref<ServiceWorkerRegistration | null>(null)

export function usePWA() {
  const checkSupport = () => {
    isSupported.value = 'serviceWorker' in navigator && 'PushManager' in window
    return isSupported.value
  }

  const registerServiceWorker = async (): Promise<ServiceWorkerRegistration | null> => {
    if (!checkSupport()) {
      console.warn('Service Workers not supported')
      return null
    }

    try {
      // Get existing registration (Vite PWA plugin handles registration)
      if ('serviceWorker' in navigator) {
        const reg = await navigator.serviceWorker.ready
        registration.value = reg
        console.log('Service Worker ready:', reg)
        return reg
      }

      return null
    } catch (error) {
      console.error('Service Worker registration failed:', error)
      return null
    }
  }

  const checkInstallPrompt = () => {
    // Check if app is already installed
    if (window.matchMedia('(display-mode: standalone)').matches) {
      isInstalled.value = true
      return
    }

    // Check if beforeinstallprompt event is available
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault()
      // Store the event for later use
      ;(window as any).deferredPrompt = e
      isInstalled.value = false
    })
  }

  const installApp = async (): Promise<boolean> => {
    const deferredPrompt = (window as any).deferredPrompt
    if (!deferredPrompt) {
      return false
    }

    deferredPrompt.prompt()
    const { outcome } = await deferredPrompt.userChoice
    ;(window as any).deferredPrompt = null

    if (outcome === 'accepted') {
      isInstalled.value = true
      return true
    }

    return false
  }

  onMounted(() => {
    checkSupport()
    checkInstallPrompt()
    registerServiceWorker()
  })

  return {
    isSupported,
    isInstalled,
    registration,
    registerServiceWorker,
    installApp,
    checkSupport,
  }
}


