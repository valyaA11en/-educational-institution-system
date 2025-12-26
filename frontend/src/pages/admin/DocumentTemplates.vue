<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h5">Шаблоны документов</span>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="showCreateDialog = true"
        >
          Создать шаблон
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-data-table
          :headers="headers"
          :items="templates"
          :loading="loading"
          @update:page="onPageChange"
        >
          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              @click="editTemplate(item)"
            />
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="deleteTemplate(item.id)"
            />
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="700">
      <v-card>
        <v-card-title>{{ editingTemplate ? 'Редактировать шаблон' : 'Создать шаблон' }}</v-card-title>
        <v-card-text>
          <v-form ref="form">
            <v-text-field
              v-model="form.name"
              label="Название"
              variant="outlined"
              required
            />
            <v-select
              v-model="form.type"
              :items="typeOptions"
              label="Тип"
              variant="outlined"
              required
            />
            <v-textarea
              v-model="schemaJsonText"
              label="Схема (JSON)"
              variant="outlined"
              rows="10"
              required
              hint="JSON объект с полями шаблона"
            />
            <v-text-field
              v-model="form.file_template_key"
              label="Ключ файла шаблона (S3)"
              variant="outlined"
              hint="Путь к файлу шаблона в хранилище"
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="cancelEdit">Отмена</v-btn>
          <v-btn color="primary" @click="saveTemplate" :loading="saving">Сохранить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { documentsApi, type DocTemplateDTO } from '../../api/documents'

const loading = ref(false)
const saving = ref(false)
const templates = ref<DocTemplateDTO[]>([])
const showCreateDialog = ref(false)
const editingTemplate = ref<DocTemplateDTO | null>(null)
const form = ref({
  name: '',
  type: '',
  schema_json: {} as Record<string, any>,
  file_template_key: null as string | null,
})
const schemaJsonText = ref('{}')

const typeOptions = [
  { title: 'Приказ', value: 'order' },
  { title: 'Решение', value: 'decision' },
  { title: 'Служебная записка', value: 'memo' },
  { title: 'Протокол', value: 'protocol' },
  { title: 'Заявление', value: 'statement' },
  { title: 'Ведомость', value: 'grade_sheet' },
]

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Название', key: 'name' },
  { title: 'Тип', key: 'type' },
  { title: 'Действия', key: 'actions', sortable: false, width: '150px' },
]

const loadTemplates = async () => {
  loading.value = true
  try {
    const response = await apiClient.get('/v1/admin/documents/templates')
    templates.value = response.data.data || response.data
  } catch (error) {
    console.error('Failed to load templates:', error)
  } finally {
    loading.value = false
  }
}

const editTemplate = (template: DocTemplateDTO) => {
  editingTemplate.value = template
  form.value = {
    name: template.name,
    type: template.type,
    schema_json: template.schema_json,
    file_template_key: template.file_template_key,
  }
  schemaJsonText.value = JSON.stringify(template.schema_json, null, 2)
  showCreateDialog.value = true
}

const cancelEdit = () => {
  editingTemplate.value = null
  form.value = {
    name: '',
    type: '',
    schema_json: {},
    file_template_key: null,
  }
  schemaJsonText.value = '{}'
  showCreateDialog.value = false
}

const saveTemplate = async () => {
  try {
    form.value.schema_json = JSON.parse(schemaJsonText.value)
  } catch (error) {
    alert('Ошибка в JSON схеме')
    return
  }

  saving.value = true
  try {
    if (editingTemplate.value) {
      await apiClient.patch(`/v1/admin/documents/templates/${editingTemplate.value.id}`, form.value)
    } else {
      await apiClient.post('/v1/admin/documents/templates', form.value)
    }
    cancelEdit()
    await loadTemplates()
  } catch (error) {
    console.error('Failed to save template:', error)
    alert('Ошибка при сохранении')
  } finally {
    saving.value = false
  }
}

const deleteTemplate = async (id: number) => {
  if (!confirm('Удалить шаблон?')) return

  try {
    await apiClient.delete(`/v1/admin/documents/templates/${id}`)
    await loadTemplates()
  } catch (error) {
    console.error('Failed to delete template:', error)
    alert('Ошибка при удалении')
  }
}

const onPageChange = (page: number) => {
  // TODO: implement pagination
}

onMounted(() => {
  loadTemplates()
})
</script>


