<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="4">
        <v-card>
          <v-card-title class="text-h5">Login</v-card-title>
          <v-card-text>
            <v-form @submit.prevent="onSubmit">
              <v-text-field
                v-model="email"
                label="Email"
                type="email"
                autocomplete="email"
                required
              />
              <v-text-field
                v-model="password"
                label="Password"
                type="password"
                autocomplete="current-password"
                required
              />
              <v-btn
                type="submit"
                color="primary"
                class="mt-4"
                :loading="auth.loading"
                block
              >
                Login
              </v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const onSubmit = async () => {
  // TODO: добавить обработку ошибок и валидацию
  await auth.login({ email: email.value, password: password.value })

  const redirect = (route.query.redirect as string) || '/'
  router.push(redirect)
}
</script>



