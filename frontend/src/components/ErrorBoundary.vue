<template>
  <div v-if="hasError" class="error-boundary">
    <v-container class="fill-height">
      <v-row align="center" justify="center">
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title class="text-h5">
              {{ t('errors.generic') }}
            </v-card-title>
            <v-card-text>
              <p class="text-body-1 mb-4">
                {{ errorMessage }}
              </p>
              <v-alert
                v-if="errorDetails"
                type="error"
                variant="tonal"
                class="mb-4"
              >
                <pre class="text-caption">{{ errorDetails }}</pre>
              </v-alert>
              <div class="d-flex gap-2">
                <v-btn
                  color="primary"
                  @click="handleRetry"
                >
                  {{ t('common.refresh') }}
                </v-btn>
                <v-btn
                  variant="outlined"
                  @click="handleGoHome"
                >
                  {{ t('common.back') }}
                </v-btn>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </div>
  <slot v-else />
</template>

<script setup lang="ts">
import { ref, onErrorCaptured, provide } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useNotificationsStore } from '../stores/notifications'

const { t } = useI18n()

const router = useRouter()
const notifications = useNotificationsStore()

const hasError = ref(false)
const errorMessage = ref('')
const errorDetails = ref<string | null>(null)

onErrorCaptured((err: Error, instance, info) => {
  console.error('Error caught by boundary:', err, info)
  
  hasError.value = true
  errorMessage.value = err.message || 'Произошла непредвиденная ошибка'
  errorDetails.value = process.env.NODE_ENV === 'development' 
    ? `${err.stack}\n\nComponent: ${info}` 
    : null

  // Show toast notification
  notifications.showToast(t('errors.generic'), 'error')

  // Prevent error from propagating
  return false
})

const handleRetry = () => {
  hasError.value = false
  errorMessage.value = ''
  errorDetails.value = null
  window.location.reload()
}

const handleGoHome = () => {
  router.push('/')
  hasError.value = false
  errorMessage.value = ''
  errorDetails.value = null
}

// Provide error handler for child components
provide('errorHandler', {
  handleError: (err: Error) => {
    hasError.value = true
    errorMessage.value = err.message
  },
})
</script>

<style scoped>
.error-boundary {
  min-height: 100vh;
}

pre {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-size: 0.75rem;
}
</style>

