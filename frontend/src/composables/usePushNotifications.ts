import { ref } from 'vue'
import apiClient from '../api/client'

const subscription = ref<PushSubscription | null>(null)
const isSupported = ref(false)
const permission = ref<NotificationPermission>('default')

export interface PushSubscription {
  endpoint: string
  keys: {
    p256dh: string
    auth: string
  }
}

export function usePushNotifications() {
  const checkSupport = (): boolean => {
    isSupported.value = 'serviceWorker' in navigator && 'PushManager' in window
    if (isSupported.value) {
      permission.value = Notification.permission
    }
    return isSupported.value
  }

  const requestPermission = async (): Promise<NotificationPermission> => {
    if (!checkSupport()) {
      throw new Error('Push notifications are not supported')
    }

    const result = await Notification.requestPermission()
    permission.value = result
    return result
  }

  const subscribe = async (): Promise<PushSubscription | null> => {
    if (!checkSupport()) {
      throw new Error('Push notifications are not supported')
    }

    if (permission.value !== 'granted') {
      const perm = await requestPermission()
      if (perm !== 'granted') {
        throw new Error('Notification permission denied')
      }
    }

    // Get service worker registration
    const registration = await navigator.serviceWorker.ready

    // Subscribe to push
    const pushSubscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(getVapidPublicKey()),
    })

    const subData: PushSubscription = {
      endpoint: pushSubscription.endpoint,
      keys: {
        p256dh: arrayBufferToBase64(pushSubscription.getKey('p256dh')!),
        auth: arrayBufferToBase64(pushSubscription.getKey('auth')!),
      },
    }

    // Send subscription to backend
    try {
      await apiClient.post('/v1/push/subscribe', {
        endpoint: subData.endpoint,
        keys: subData.keys,
        userAgent: {
          userAgent: navigator.userAgent,
          platform: navigator.platform,
          language: navigator.language,
        },
      })

      subscription.value = subData
      return subData
    } catch (error) {
      console.error('Failed to subscribe to push notifications:', error)
      throw error
    }
  }

  const unsubscribe = async (): Promise<void> => {
    if (!subscription.value) {
      return
    }

    try {
      // Unsubscribe from push manager
      const registration = await navigator.serviceWorker.ready
      const pushSubscription = await registration.pushManager.getSubscription()
      if (pushSubscription) {
        await pushSubscription.unsubscribe()
      }

      // Remove from backend
      await apiClient.post('/v1/push/unsubscribe', {
        endpoint: subscription.value.endpoint,
      })

      subscription.value = null
    } catch (error) {
      console.error('Failed to unsubscribe from push notifications:', error)
      throw error
    }
  }

  const getVapidPublicKey = (): string => {
    // Get from env or config
    return import.meta.env.VITE_VAPID_PUBLIC_KEY || ''
  }

  const urlBase64ToUint8Array = (base64String: string): Uint8Array => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')

    const rawData = window.atob(base64)
    const outputArray = new Uint8Array(rawData.length)

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i)
    }
    return outputArray
  }

  const arrayBufferToBase64 = (buffer: ArrayBuffer): string => {
    const bytes = new Uint8Array(buffer)
    let binary = ''
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i])
    }
    return window.btoa(binary)
  }

  return {
    subscription,
    isSupported,
    permission,
    checkSupport,
    requestPermission,
    subscribe,
    unsubscribe,
  }
}

