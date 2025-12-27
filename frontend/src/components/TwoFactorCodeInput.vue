<template>
  <v-card>
    <v-card-title class="text-h6">
      Двухфакторная аутентификация
    </v-card-title>
    <v-card-text>
      <v-form @submit.prevent="onSubmit" ref="formRef">
        <v-text-field
          v-model="code"
          label="Код из приложения"
          type="text"
          autocomplete="one-time-code"
          :rules="[rules.required, rules.length]"
          required
          prepend-inner-icon="mdi-shield-lock"
          class="mb-4"
          autofocus
          maxlength="6"
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
          :loading="loading"
          block
          size="large"
        >
          Подтвердить
        </v-btn>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  loading?: boolean
}>()

const emit = defineEmits<{
  submit: [code: string]
}>()

const code = ref('')
const error = ref('')
const formRef = ref()

const rules = {
  required: (v: string) => !!v || 'Обязательное поле',
  length: (v: string) => v.length === 6 || 'Код должен содержать 6 цифр',
}

const onSubmit = async () => {
  error.value = ''
  
  const { valid } = await formRef.value.validate()
  if (!valid) {
    return
  }

  emit('submit', code.value)
}

defineExpose({
  setError: (msg: string) => {
    error.value = msg
  },
  clearCode: () => {
    code.value = ''
  },
})
</script>


