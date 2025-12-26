<template>
  <v-app>
    <router-view />
    <PWAInstaller />
    <!-- Global notifications toast -->
    <v-snackbar
      v-model="notifications.toastVisible"
      :timeout="4000"
      :color="getToastColor(notifications.toastType)"
      location="bottom right"
    >
      {{ notifications.toastMessage }}
    </v-snackbar>
  </v-app>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useNotificationsStore } from './stores/notifications'
import { usePWA } from './composables/usePWA'
import PWAInstaller from './components/PWAInstaller.vue'

const notifications = useNotificationsStore()
const { registerServiceWorker } = usePWA()

const getToastColor = (type: 'success' | 'error' | 'info' | 'warning') => {
  const colors = {
    success: 'success',
    error: 'error',
    info: 'primary',
    warning: 'warning',
  }
  return colors[type] || 'primary'
}

onMounted(() => {
  // Register service worker for PWA
  registerServiceWorker()
})
</script>

<style>
html,
body,
#app {
  margin: 0;
  padding: 0;
  height: 100%;
}
</style>

