<template>
  <v-card variant="outlined">
    <v-card-title class="d-flex justify-space-between align-center">
      <span class="text-subtitle-1">Редактирование документа ГОСТ</span>
      <v-btn
        color="info"
        prepend-icon="mdi-check-circle"
        @click="validateGost"
        :loading="validating"
        variant="outlined"
        size="small"
      >
        Проверить по ГОСТ
      </v-btn>
    </v-card-title>
    <v-card-text>
      <v-form ref="formRef" v-model="formValid">
        <!-- Заголовок -->
        <v-text-field
          v-model="formData.title"
          label="Заголовок"
          variant="outlined"
          :rules="[rules.required]"
          :error-messages="fieldErrors.title"
          @input="clearFieldError('title')"
          class="mb-3"
        />

        <!-- Основание -->
        <v-textarea
          v-model="formData.basis"
          label="Основание"
          variant="outlined"
          :rules="[rules.required]"
          :error-messages="fieldErrors.basis"
          @input="clearFieldError('basis')"
          rows="2"
          class="mb-3"
        />

        <!-- Пункты приказа (динамический список) -->
        <div class="mb-3">
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-subtitle-2">Пункты документа</span>
            <v-btn
              color="primary"
              prepend-icon="mdi-plus"
              size="small"
              variant="outlined"
              @click="addBodyItem"
            >
              Добавить пункт
            </v-btn>
          </div>
          <v-card
            v-for="(item, index) in formData.body_items"
            :key="index"
            variant="outlined"
            class="mb-2"
          >
            <v-card-text>
              <div class="d-flex gap-2 align-start">
                <v-text-field
                  v-model="item.no"
                  label="Номер"
                  variant="outlined"
                  density="compact"
                  style="max-width: 100px"
                  :rules="[rules.required]"
                  :error-messages="getBodyItemError(index, 'no')"
                  @input="clearBodyItemError(index, 'no')"
                />
                <v-textarea
                  v-model="item.text"
                  label="Текст пункта"
                  variant="outlined"
                  density="compact"
                  :rules="[rules.required]"
                  :error-messages="getBodyItemError(index, 'text')"
                  @input="clearBodyItemError(index, 'text')"
                  rows="2"
                  class="flex-grow-1"
                />
                <v-btn
                  icon="mdi-delete"
                  size="small"
                  variant="text"
                  color="error"
                  @click="removeBodyItem(index)"
                  :disabled="formData.body_items.length <= 1"
                />
              </div>
            </v-card-text>
          </v-card>
          <v-alert
            v-if="bodyItemsErrors.length > 0"
            type="error"
            density="compact"
            class="mt-2"
          >
            <div v-for="(error, idx) in bodyItemsErrors" :key="idx">
              {{ error }}
            </div>
          </v-alert>
        </div>

        <!-- Подписант -->
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formData.signer_role"
              label="Должность подписанта"
              variant="outlined"
              :rules="[rules.required]"
              :error-messages="fieldErrors.signer_role"
              @input="clearFieldError('signer_role')"
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formData.signer_name"
              label="ФИО подписанта"
              variant="outlined"
              :rules="[rules.required]"
              :error-messages="fieldErrors.signer_name"
              @input="clearFieldError('signer_name')"
            />
          </v-col>
        </v-row>

        <!-- Адресаты ознакомления (опционально) -->
        <div class="mb-3">
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-subtitle-2">Адресаты ознакомления (необязательно)</span>
            <v-btn
              color="primary"
              prepend-icon="mdi-plus"
              size="small"
              variant="outlined"
              @click="addRecipient"
            >
              Добавить адресата
            </v-btn>
          </div>
          <v-chip
            v-for="(recipient, index) in formData.recipients"
            :key="index"
            closable
            @click:close="removeRecipient(index)"
            class="mr-2 mb-2"
          >
            {{ recipient }}
          </v-chip>
          <v-text-field
            v-if="showRecipientInput"
            v-model="newRecipient"
            label="Адресат"
            variant="outlined"
            density="compact"
            @keyup.enter="addRecipient"
            @blur="addRecipient"
            autofocus
            class="mt-2"
          />
        </div>

        <!-- Приложение (опционально) -->
        <v-textarea
          v-model="formData.appendix"
          label="Приложение (необязательно)"
          variant="outlined"
          rows="2"
          class="mb-3"
        />

        <!-- Общие ошибки валидации -->
        <v-alert
          v-if="validationErrors.length > 0"
          type="error"
          density="compact"
          class="mb-3"
        >
          <div v-for="(error, index) in validationErrors" :key="index">
            {{ error }}
          </div>
        </v-alert>

        <!-- Сообщение об успешной валидации -->
        <v-alert
          v-if="validationSuccess"
          type="success"
          density="compact"
          class="mb-3"
        >
          Документ соответствует требованиям ГОСТ
        </v-alert>

        <!-- Кнопки действий -->
        <div class="d-flex justify-end gap-2 mt-4">
          <v-btn
            variant="text"
            @click="$emit('cancel')"
            :disabled="saving"
          >
            Отмена
          </v-btn>
          <v-btn
            color="primary"
            @click="saveDocument"
            :loading="saving"
            :disabled="!formValid"
          >
            Сохранить
          </v-btn>
        </div>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { documentsApi } from '../api/documents'
import { useToast } from '../composables/useToast'

interface Props {
  documentId: number
  initialData?: {
    org_name?: string
    title?: string
    basis?: string
    body_items?: Array<{ no: string; text: string }>
    signer_name?: string
    signer_role?: string
    appendix?: string
    recipients?: string[]
  }
}

const props = defineProps<Props>()
const emit = defineEmits<{
  saved: []
  cancel: []
}>()

const { showToast } = useToast()

const formRef = ref()
const formValid = ref(false)
const saving = ref(false)
const validating = ref(false)
const validationErrors = ref<string[]>([])
const validationSuccess = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const bodyItemsErrors = ref<string[]>([])
const showRecipientInput = ref(false)
const newRecipient = ref('')

const formData = ref({
  org_name: props.initialData?.org_name || '',
  title: props.initialData?.title || '',
  basis: props.initialData?.basis || '',
  body_items: props.initialData?.body_items && props.initialData.body_items.length > 0
    ? [...props.initialData.body_items]
    : [{ no: '1', text: '' }],
  signer_name: props.initialData?.signer_name || '',
  signer_role: props.initialData?.signer_role || '',
  appendix: props.initialData?.appendix || '',
  recipients: props.initialData?.recipients ? [...props.initialData.recipients] : [],
})

const rules = {
  required: (v: any) => !!v || 'Обязательное поле',
}

// Следим за изменениями и очищаем сообщения об успехе
watch(() => formData.value, () => {
  validationSuccess.value = false
  validationErrors.value = []
}, { deep: true })

const clearFieldError = (field: string) => {
  if (fieldErrors.value[field]) {
    delete fieldErrors.value[field]
  }
}

const getBodyItemError = (index: number, field: 'no' | 'text'): string[] => {
  const errorKey = `body_items[${index}].${field}`
  return fieldErrors.value[errorKey] || []
}

const clearBodyItemError = (index: number, field: 'no' | 'text') => {
  const errorKey = `body_items[${index}].${field}`
  if (fieldErrors.value[errorKey]) {
    delete fieldErrors.value[errorKey]
  }
}

const addBodyItem = () => {
  const nextNo = formData.value.body_items.length + 1
  formData.value.body_items.push({ no: String(nextNo), text: '' })
}

const removeBodyItem = (index: number) => {
  if (formData.value.body_items.length > 1) {
    formData.value.body_items.splice(index, 1)
    // Перенумеровать пункты
    formData.value.body_items.forEach((item, idx) => {
      item.no = String(idx + 1)
    })
  }
}

const addRecipient = () => {
  if (newRecipient.value.trim()) {
    if (!formData.value.recipients.includes(newRecipient.value.trim())) {
      formData.value.recipients.push(newRecipient.value.trim())
    }
    newRecipient.value = ''
    showRecipientInput.value = false
  } else {
    showRecipientInput.value = false
  }
}

const removeRecipient = (index: number) => {
  formData.value.recipients.splice(index, 1)
}

const validateGost = async () => {
  validating.value = true
  validationErrors.value = []
  fieldErrors.value = {}
  bodyItemsErrors.value = []
  validationSuccess.value = false

  try {
    const result = await documentsApi.validateGost(props.documentId)
    
    if (result.valid) {
      validationSuccess.value = true
      showToast('Документ соответствует требованиям ГОСТ', 'success')
    } else {
      validationErrors.value = result.errors || []
      
      // Парсим ошибки и распределяем по полям
      result.errors?.forEach((error: string) => {
        // Ошибки вида "data_json.title не может быть пустым"
        const match = error.match(/data_json\.(\w+)/)
        if (match) {
          const field = match[1]
          if (!fieldErrors.value[field]) {
            fieldErrors.value[field] = []
          }
          fieldErrors.value[field].push(error)
        }
        
        // Ошибки body_items
        const bodyMatch = error.match(/data_json\.body_items\[(\d+)\]\.(\w+)/)
        if (bodyMatch) {
          const index = parseInt(bodyMatch[1])
          const field = bodyMatch[2]
          const errorKey = `body_items[${index}].${field}`
          if (!fieldErrors.value[errorKey]) {
            fieldErrors.value[errorKey] = []
          }
          fieldErrors.value[errorKey].push(error)
        }
        
        // Общие ошибки body_items
        if (error.includes('body_items') && !bodyMatch) {
          bodyItemsErrors.value.push(error)
        }
      })
      
      showToast('Обнаружены ошибки валидации ГОСТ', 'error')
    }
  } catch (error: any) {
    console.error('Failed to validate GOST:', error)
    showToast('Ошибка при проверке по ГОСТ', 'error')
  } finally {
    validating.value = false
  }
}

const saveDocument = async () => {
  if (!formRef.value?.validate()) {
    return
  }

  saving.value = true
  validationErrors.value = []
  fieldErrors.value = {}

  try {
    // Подготавливаем data_json для сохранения
    const dataJson = {
      org_name: formData.value.org_name,
      title: formData.value.title,
      basis: formData.value.basis,
      body_items: formData.value.body_items,
      signer_name: formData.value.signer_name,
      signer_role: formData.value.signer_role,
      ...(formData.value.appendix && { appendix: formData.value.appendix }),
      ...(formData.value.recipients.length > 0 && { recipients: formData.value.recipients }),
    }

    await documentsApi.update(props.documentId, { data_json: dataJson })
    showToast('Документ сохранен', 'success')
    emit('saved')
  } catch (error: any) {
    console.error('Failed to save document:', error)
    
    // Обработка ошибок валидации
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const errors = error.response.data.errors
      if (errors.data_json) {
        validationErrors.value = Array.isArray(errors.data_json)
          ? errors.data_json
          : [errors.data_json]
      }
    }
    
    showToast('Ошибка при сохранении документа', 'error')
  } finally {
    saving.value = false
  }
}

// Инициализация: если recipients пуст, показываем поле ввода
watch(() => formData.value.recipients, (recipients) => {
  if (recipients.length === 0 && !showRecipientInput.value) {
    showRecipientInput.value = true
  }
}, { immediate: true })
</script>


