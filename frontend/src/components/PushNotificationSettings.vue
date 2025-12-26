<template>
  <v-card>
    <v-card-title>Push-уведомления</v-card-title>
    <v-card-text>
      <div v-if="!isSupported" class="text-body-2 text-grey mb-4">
        Ваш браузер не поддерживает push-уведомления
      </div>
      <div v-else>
        <div class="text-body-2 mb-4">
          Статус: 
          <v-chip :color="getPermissionColor(permission)" size="small" class="ml-2">
            {{ getPermissionText(permission) }}
          </v-chip>
        </div>
        <v-btn
          v-if="permission === 'default'"
          color="primary"
          @click="enableNotifications"
          :loading="loading"
        >
          Включить уведомления
        </v-btn>
        <v-btn
          v-else-if="permission === 'granted' && !subscription"
          color="primary"
          @click="subscribe"
          :loading="loading"
        >
          Подписаться на уведомления
        </v-btn>
        <v-btn
          v-else-if="subscription"
          color="error"
          @click="unsubscribe"
          :loading="loading"
        >
          Отписаться от уведомлений
        </v-btn>
        <v-btn
          v-else-if="permission === 'denied'"
          color="primary"
          variant="outlined"
          @click="openSettings"
        >
          Открыть настройки браузера
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePushNotifications } from '../composables/usePushNotifications'

const { isSupported, permission, subscription, checkSupport, requestPermission, subscribe: subscribePush, unsubscribe: unsubscribePush } = usePushNotifications()
const loading = ref(false)

const enableNotifications = async () => {
  loading.value = true
  try {
    await requestPermission()
    if (permission.value === 'granted') {
      await subscribe()
    }
  } catch (error) {
    console.error('Failed to enable notifications:', error)
    alert('Не удалось включить уведомления')
  } finally {
    loading.value = false
  }
}

const subscribe = async () => {
  loading.value = true
  try {
    await subscribePush()
  } catch (error) {
    console.error('Failed to subscribe:', error)
    alert('Не удалось подписаться на уведомления')
  } finally {
    loading.value = false
  }
}

const unsubscribe = async () => {
  loading.value = true
  try {
    await unsubscribePush()
  } catch (error) {
    console.error('Failed to unsubscribe:', error)
    alert('Не удалось отписаться от уведомлений')
  } finally {
    loading.value = false
  }
}

const openSettings = () => {
  // Browser settings can't be opened programmatically
  alert('Пожалуйста, разрешите уведомления в настройках браузера')
}

const getPermissionColor = (perm: NotificationPermission) => {
  switch (perm) {
    case 'granted':
      return 'success'
    case 'denied':
      return 'error'
    default:
      return 'warning'
  }
}

const getPermissionText = (perm: NotificationPermission) => {
  switch (perm) {
    case 'granted':
      return 'Разрешено'
    case 'denied':
      return 'Запрещено'
    default:
      return 'Не запрошено'
  }
}

onMounted(() => {
  checkSupport()
})
</script>


