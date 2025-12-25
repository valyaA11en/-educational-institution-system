<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="4">
        <v-card>
          <v-card-title class="text-h5 text-center pa-4">
            Вход в систему
          </v-card-title>
          <v-card-text>
            <v-form @submit.prevent="onSubmit" ref="formRef">
              <v-text-field
                v-model="emailOrPhone"
                label="Email или телефон"
                type="text"
                autocomplete="username"
                :rules="[rules.required]"
                required
                prepend-inner-icon="mdi-account"
                class="mb-2"
              />
              <v-text-field
                v-model="password"
                label="Пароль"
                type="password"
                autocomplete="current-password"
                :rules="[rules.required]"
                required
                prepend-inner-icon="mdi-lock"
                class="mb-2"
              />
              <v-alert
                v-if="error"
                type="error"
                density="compact"
                class="mb-4"
              >
                {{ error }}
              </v-alert>
              <v-btn
                type="submit"
                color="primary"
                class="mt-4"
                :loading="auth.loading"
                block
                size="large"
              >
                Войти
              </v-btn>
            </v-form>

            <!-- DEV-only quick login buttons -->
            <template v-if="isDev">
              <v-divider class="my-4" />
              <div class="text-caption text-center text-grey mb-2">
                DEV: Быстрый вход
              </div>
              <v-row dense>
                <v-col cols="6">
                  <v-btn
                    color="primary"
                    variant="outlined"
                    size="small"
                    block
                    @click="quickLogin('admin@test.local')"
                  >
                    Admin
                  </v-btn>
                </v-col>
                <v-col cols="6">
                  <v-btn
                    color="primary"
                    variant="outlined"
                    size="small"
                    block
                    @click="quickLogin('teacher1@test.local')"
                  >
                    Teacher
                  </v-btn>
                </v-col>
                <v-col cols="6">
                  <v-btn
                    color="primary"
                    variant="outlined"
                    size="small"
                    block
                    @click="quickLogin('student1@test.local')"
                  >
                    Student
                  </v-btn>
                </v-col>
                <v-col cols="6">
                  <v-btn
                    color="primary"
                    variant="outlined"
                    size="small"
                    block
                    @click="quickLogin('parent1@test.local')"
                  >
                    Parent
                  </v-btn>
                </v-col>
              </v-row>
            </template>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const emailOrPhone = ref('')
const password = ref('')
const error = ref('')
const formRef = ref()

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const isDev = computed(() => import.meta.env.DEV)

const DEMO_PASSWORD = 'Password123!'

const rules = {
  required: (v: string) => !!v || 'Обязательное поле',
}

const quickLogin = async (email: string) => {
  emailOrPhone.value = email
  password.value = DEMO_PASSWORD
  error.value = ''

  try {
    await auth.login({
      emailOrPhone: email,
      password: DEMO_PASSWORD,
    })

    const redirect = (route.query.redirect as string) || '/'
    router.push(redirect)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Ошибка входа. Проверьте данные.'
    console.error('Login error:', err)
  }
}

const onSubmit = async () => {
  error.value = ''
  
  const { valid } = await formRef.value.validate()
  if (!valid) {
    return
  }

  try {
    await auth.login({
      emailOrPhone: emailOrPhone.value,
      password: password.value,
    })

    const redirect = (route.query.redirect as string) || '/'
    router.push(redirect)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Ошибка входа. Проверьте данные.'
    console.error('Login error:', err)
  }
}
</script>

