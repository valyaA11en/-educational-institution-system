<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>Безопасность</v-card-title>
          <v-card-text>
            <!-- 2FA Section -->
            <v-card variant="outlined" class="mb-4">
              <v-card-title class="text-h6">Двухфакторная аутентификация</v-card-title>
              <v-card-text>
                <div v-if="!twoFactorEnabled">
                  <p class="mb-4">
                    Двухфакторная аутентификация добавляет дополнительный уровень защиты вашего аккаунта.
                  </p>
                  <v-btn
                    color="primary"
                    @click="startSetup"
                    :loading="setupLoading"
                  >
                    Настроить 2FA
                  </v-btn>
                </div>

                <!-- Setup Flow -->
                <div v-if="setupStep === 'qr'">
                  <v-alert type="info" class="mb-4">
                    Отсканируйте QR-код в приложении для двухфакторной аутентификации (Google Authenticator, Authy и т.д.)
                  </v-alert>
                  <div class="text-center mb-4">
                    <img :src="qrCodeUrl" alt="QR Code" style="max-width: 300px" />
                  </div>
                  <v-alert type="warning" class="mb-4">
                    <strong>Сохраните резервные коды!</strong>
                    <div class="mt-2">
                      <div v-for="(code, index) in recoveryCodes" :key="index" class="font-mono">
                        {{ code }}
                      </div>
                    </div>
                  </v-alert>
                  <v-text-field
                    v-model="verificationCode"
                    label="Введите код из приложения"
                    type="text"
                    maxlength="6"
                    class="mb-4"
                  />
                  <v-btn
                    color="primary"
                    @click="enable2FA"
                    :loading="enableLoading"
                    :disabled="!verificationCode || verificationCode.length !== 6"
                  >
                    Включить 2FA
                  </v-btn>
                  <v-btn
                    variant="text"
                    @click="cancelSetup"
                    class="ml-2"
                  >
                    Отмена
                  </v-btn>
                </div>

                <!-- Enabled State -->
                <div v-if="twoFactorEnabled && setupStep === null">
                  <v-alert type="success" class="mb-4">
                    2FA включена для вашего аккаунта
                  </v-alert>
                  <v-dialog v-model="disableDialog" max-width="500">
                    <template v-slot:activator="{ props }">
                      <v-btn
                        color="error"
                        variant="outlined"
                        v-bind="props"
                      >
                        Отключить 2FA
                      </v-btn>
                    </template>
                    <v-card>
                      <v-card-title>Отключить 2FA</v-card-title>
                      <v-card-text>
                        <v-text-field
                          v-model="disableCode"
                          label="Введите код из приложения или резервный код"
                          type="text"
                          class="mb-4"
                        />
                        <v-alert v-if="disableError" type="error" density="compact" class="mb-4">
                          {{ disableError }}
                        </v-alert>
                      </v-card-text>
                      <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" @click="disableDialog = false">Отмена</v-btn>
                        <v-btn
                          color="error"
                          @click="disable2FA"
                          :loading="disableLoading"
                        >
                          Отключить
                        </v-btn>
                      </v-card-actions>
                    </v-card>
                  </v-dialog>
                </div>
              </v-card-text>
            </v-card>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { twoFactorApi } from '../api/2fa'

const setupStep = ref<'qr' | null>(null)
const qrCodeUrl = ref('')
const recoveryCodes = ref<string[]>([])
const verificationCode = ref('')
const setupLoading = ref(false)
const enableLoading = ref(false)
const disableDialog = ref(false)
const disableCode = ref('')
const disableLoading = ref(false)
const disableError = ref('')
const twoFactorEnabled = ref(false)

const loadStatus = async () => {
  try {
    const status = await twoFactorApi.status()
    twoFactorEnabled.value = status.enabled
  } catch (error) {
    console.error('Failed to load 2FA status:', error)
  }
}

onMounted(() => {
  loadStatus()
})

const startSetup = async () => {
  setupLoading.value = true
  try {
    const response = await twoFactorApi.setup()
    qrCodeUrl.value = response.qr_code_url
    recoveryCodes.value = response.recovery_codes
    setupStep.value = 'qr'
  } catch (error: any) {
    console.error('Setup error:', error)
  } finally {
    setupLoading.value = false
  }
}

const enable2FA = async () => {
  if (!verificationCode.value || verificationCode.value.length !== 6) {
    return
  }

  enableLoading.value = true
  try {
    const response = await twoFactorApi.enable(verificationCode.value)
    await loadStatus()
    setupStep.value = null
    recoveryCodes.value = response.recovery_codes
    verificationCode.value = ''
  } catch (error: any) {
    console.error('Enable error:', error)
    alert(error.response?.data?.message || 'Ошибка при включении 2FA')
  } finally {
    enableLoading.value = false
  }
}

const cancelSetup = () => {
  setupStep.value = null
  qrCodeUrl.value = ''
  recoveryCodes.value = []
  verificationCode.value = ''
}

const disable2FA = async () => {
  if (!disableCode.value) {
    return
  }

  disableLoading.value = true
  disableError.value = ''
  try {
    await twoFactorApi.disable(disableCode.value)
    await loadStatus()
    disableDialog.value = false
    disableCode.value = ''
  } catch (error: any) {
    disableError.value = error.response?.data?.message || 'Неверный код'
  } finally {
    disableLoading.value = false
  }
}
</script>

