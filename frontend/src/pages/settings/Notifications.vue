<template>
  <div>
    <v-card>
      <v-card-title class="text-h5">Настройки уведомлений</v-card-title>
      <v-card-text>
        <v-alert
          v-if="!isPushSupported"
          type="warning"
          class="mb-4"
        >
          Push-уведомления не поддерживаются в вашем браузере
        </v-alert>

        <v-alert
          v-if="pushError"
          type="error"
          class="mb-4"
        >
          {{ pushError }}
        </v-alert>

        <div class="mb-4">
          <div class="text-subtitle-1 mb-2">Push-уведомления</div>
          <div class="text-body-2 text-grey mb-4">
            Получайте уведомления даже когда приложение закрыто
          </div>

          <v-btn
            v-if="!isSubscribed"
            color="primary"
            :loading="subscribing"
            :disabled="!isPushSupported"
            @click="enablePush"
          >
            <v-icon start>mdi-bell-outline</v-icon>
            Включить push-уведомления
          </v-btn>

          <div v-else class="d-flex align-center gap-2">
            <v-chip color="success" prepend-icon="mdi-check-circle">
              Push-уведомления включены
            </v-chip>
            <v-btn
              variant="outlined"
              color="error"
              size="small"
              :loading="unsubscribing"
              @click="disablePush"
            >
              Отключить
            </v-btn>
          </div>
        </div>

        <v-divider class="my-4" />

        <div>
          <div class="text-subtitle-1 mb-2">Статус подписки</div>
          <v-list density="compact">
            <v-list-item>
              <v-list-item-title>Поддержка браузера</v-list-item-title>
              <template #append>
                <v-chip :color="isPushSupported ? 'success' : 'error'" size="small">
                  {{ isPushSupported ? 'Поддерживается' : 'Не поддерживается' }}
                </v-chip>
              </template>
            </v-list-item>
            <v-list-item>
              <v-list-item-title>Разрешение уведомлений</v-list-item-title>
              <template #append>
                <v-chip :color="notificationPermission === 'granted' ? 'success' : 'warning'" size="small">
                  {{ getPermissionText(notificationPermission) }}
                </v-chip>
              </template>
            </v-list-item>
            <v-list-item v-if="subscription">
              <v-list-item-title>Подписка активна</v-list-item-title>
              <template #append>
                <v-chip color="success" size="small">Да</v-chip>
              </template>
            </v-list-item>
          </v-list>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { pushApi } from '../../api/push'
import { useToast } from '../../composables/useToast'
import { usePWA } from '../../composables/usePWA'

const { showToast } = useToast()
const { registration } = usePWA()

const isPushSupported = ref(false)
const notificationPermission = ref<NotificationPermission>('default')
const subscription = ref<PushSubscription | null>(null)
const subscribing = ref(false)
const unsubscribing = ref(false)
const pushError = ref<string | null>(null)

const isSubscribed = computed(() => subscription.value !== null)

const checkPushSupport = () => {
  isPushSupported.value = 'serviceWorker' in navigator && 'PushManager' in window
  if ('Notification' in window) {
    notificationPermission.value = Notification.permission
  }
}

const getPermissionText = (permission: NotificationPermission): string => {
  const texts: Record<NotificationPermission, string> = {
    granted: 'Разрешено',
    denied: 'Запрещено',
    default: 'Не запрошено',
  }
  return texts[permission] || permission
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

const enablePush = async () => {
  if (!isPushSupported.value) {
    pushError.value = 'Push-уведомления не поддерживаются'
    return
  }

  subscribing.value = true
  pushError.value = null

  try {
    // Request notification permission
    if (Notification.permission === 'default') {
      const permission = await Notification.requestPermission()
      notificationPermission.value = permission

      if (permission !== 'granted') {
        pushError.value = 'Разрешение на уведомления не предоставлено'
        subscribing.value = false
        return
      }
    } else if (Notification.permission === 'denied') {
      pushError.value = 'Уведомления запрещены. Разрешите их в настройках браузера.'
      subscribing.value = false
      return
    }

    // Get service worker registration
    if (!registration.value) {
      const reg = await navigator.serviceWorker.ready
      registration.value = reg
    }

    if (!registration.value) {
      throw new Error('Service Worker не зарегистрирован')
    }

    // Subscribe to push
    const pushSubscription = await registration.value.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(
        import.meta.env.VITE_VAPID_PUBLIC_KEY || '',
      ),
    })

    // Get subscription details
    const subscriptionData = pushSubscription.toJSON()

    if (!subscriptionData.keys || !subscriptionData.endpoint) {
      throw new Error('Не удалось получить данные подписки')
    }

    // Send to backend
    await pushApi.subscribe({
      endpoint: subscriptionData.endpoint,
      keys: {
        p256dh: subscriptionData.keys.p256dh || '',
        auth: subscriptionData.keys.auth || '',
      },
    })

    subscription.value = pushSubscription
    showToast('Push-уведомления включены', 'success')
  } catch (error: any) {
    console.error('Failed to enable push:', error)
    pushError.value = error.message || 'Ошибка при включении push-уведомлений'
    showToast(pushError.value, 'error')
  } finally {
    subscribing.value = false
  }
}

const disablePush = async () => {
  if (!subscription.value) return

  unsubscribing.value = true
  pushError.value = null

  try {
    // Unsubscribe from push
    await subscription.value.unsubscribe()

    // Remove from backend
    const subscriptionData = subscription.value.toJSON()
    if (subscriptionData.endpoint) {
      await pushApi.unsubscribe(subscriptionData.endpoint)
    }

    subscription.value = null
    showToast('Push-уведомления отключены', 'success')
  } catch (error: any) {
    console.error('Failed to disable push:', error)
    pushError.value = error.message || 'Ошибка при отключении push-уведомлений'
    showToast(pushError.value, 'error')
  } finally {
    unsubscribing.value = false
  }
}

const checkExistingSubscription = async () => {
  if (!registration.value) {
    const reg = await navigator.serviceWorker.ready
    registration.value = reg
  }

  if (!registration.value) return

  try {
    const existingSubscription = await registration.value.pushManager.getSubscription()
    if (existingSubscription) {
      subscription.value = existingSubscription
    }
  } catch (error) {
    console.error('Failed to check existing subscription:', error)
  }
}

onMounted(() => {
  checkPushSupport()
  checkExistingSubscription()
})
</script>

