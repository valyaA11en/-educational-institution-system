<template>
  <div>
    <v-card>
      <v-card-title>
        <span class="text-h5">Создать документ</span>
      </v-card-title>
      <v-card-text>
        <v-form ref="form" @submit.prevent="createDocument">
          <v-select
            v-model="form.template_id"
            :items="templateOptions"
            label="Шаблон"
            variant="outlined"
            required
            :loading="loadingTemplates"
          />
          <v-text-field
            v-model="form.type"
            label="Тип документа"
            variant="outlined"
            required
            class="mt-3"
          />
          <div class="mt-4">
            <div class="text-subtitle-2 mb-2">Данные документа</div>
            <div v-for="(value, key) in form.data_json" :key="key" class="mb-2">
              <v-text-field
                v-model="form.data_json[key]"
                :label="formatKey(key)"
                variant="outlined"
                density="compact"
              />
            </div>
            <v-btn
              color="primary"
              variant="outlined"
              size="small"
              prepend-icon="mdi-plus"
              @click="addDataField"
              class="mt-2"
            >
              Добавить поле
            </v-btn>
          </div>
          <v-card-actions class="pa-0 mt-4">
            <v-spacer />
            <v-btn variant="text" @click="$router.back()">Отмена</v-btn>
            <v-btn color="primary" type="submit" :loading="saving">Создать</v-btn>
          </v-card-actions>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { documentsApi } from '../api/documents'

const router = useRouter()
const form = ref({
  type: '',
  template_id: null as number | null,
  data_json: {} as Record<string, any>,
})
const saving = ref(false)
const loadingTemplates = ref(false)
const templateOptions = ref<Array<{ title: string; value: number }>>([])
const newFieldKey = ref('')
const newFieldValue = ref('')

const loadTemplates = async () => {
  loadingTemplates.value = true
  try {
    const templates = await documentsApi.templates()
    templateOptions.value = templates.map(t => ({
      title: `${t.name} (${t.type})`,
      value: t.id,
    }))
    
    if (templates.length > 0 && !form.value.template_id) {
      form.value.template_id = templates[0].id
      form.value.type = templates[0].type
      
      // Load schema and initialize data_json
      if (templates[0].schema_json) {
        const schema = templates[0].schema_json as Record<string, any>
        Object.keys(schema).forEach(key => {
          form.value.data_json[key] = ''
        })
      }
    }
  } catch (error) {
    console.error('Failed to load templates:', error)
  } finally {
    loadingTemplates.value = false
  }
}

const addDataField = () => {
  if (newFieldKey.value) {
    form.value.data_json[newFieldKey.value] = newFieldValue.value
    newFieldKey.value = ''
    newFieldValue.value = ''
  }
}

const createDocument = async () => {
  if (!form.value.template_id) return

  saving.value = true
  try {
    const document = await documentsApi.create({
      type: form.value.type,
      template_id: form.value.template_id,
      data_json: form.value.data_json,
    })
    router.push({ name: 'document-view', params: { id: document.id } })
  } catch (error) {
    console.error('Failed to create document:', error)
    alert('Ошибка при создании документа')
  } finally {
    saving.value = false
  }
}

const formatKey = (key: string) => {
  return key
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

onMounted(() => {
  loadTemplates()
})
</script>









