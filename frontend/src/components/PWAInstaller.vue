<template>
  <v-card v-if="showInstallPrompt" class="ma-4">
    <v-card-text>
      <div class="d-flex align-center">
        <v-icon class="mr-3" color="primary" size="large">mdi-download</v-icon>
        <div class="flex-grow-1">
          <div class="text-subtitle-1 font-weight-bold">Установить PDO</div>
          <div class="text-caption text-grey">Установите приложение для быстрого доступа</div>
        </div>
        <v-btn color="primary" @click="install">Установить</v-btn>
        <v-btn icon="mdi-close" variant="text" @click="dismiss" class="ml-2" />
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePWA } from '../composables/usePWA'

const { isSupported, isInstalled, installApp } = usePWA()
const showInstallPrompt = ref(false)
const dismissed = ref(false)

const checkInstallPrompt = () => {
  if (dismissed.value || isInstalled.value || !isSupported.value) {
    return
  }

  // Check if deferred prompt is available
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    ;(window as any).deferredPrompt = e
    showInstallPrompt.value = true
  })

  // Check if already installed
  if (window.matchMedia('(display-mode: standalone)').matches) {
    showInstallPrompt.value = false
  }
}

const install = async () => {
  const installed = await installApp()
  if (installed) {
    showInstallPrompt.value = false
  }
}

const dismiss = () => {
  dismissed.value = true
  showInstallPrompt.value = false
  localStorage.setItem('pwa_install_dismissed', 'true')
}

onMounted(() => {
  if (localStorage.getItem('pwa_install_dismissed') !== 'true') {
    checkInstallPrompt()
  }
})
</script>


