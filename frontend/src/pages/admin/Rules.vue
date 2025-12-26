<template>
  <div>
    <DataTable
      :headers="headers"
      :items="rules"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по названию"
      create-button-text="Создать правило"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:item.enabled="{ item }">
        <v-switch
          :model-value="item.enabled"
          @update:model-value="toggleRule(item.id)"
          hide-details
          density="compact"
          color="success"
        />
      </template>
      <template v-slot:item.event_type="{ item }">
        <v-chip size="small" color="primary">
          {{ getEventTypeLabel(item.conditions_json?.event_type) }}
        </v-chip>
      </template>
      <template v-slot:item.updated_at="{ item }">
        {{ formatDate(item.updated_at || item.created_at) }}
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
        <v-btn
          :icon="item.enabled ? 'mdi-toggle-switch' : 'mdi-toggle-switch-off'"
          size="small"
          variant="text"
          :color="item.enabled ? 'success' : 'grey'"
          @click="toggleRule(item.id)"
          :title="item.enabled ? 'Выключить' : 'Включить'"
        />
      </template>
    </DataTable>

    <v-dialog v-model="dialog" max-width="900" scrollable>
      <v-card>
        <v-card-title>
          {{ editing ? 'Редактировать правило' : 'Создать правило' }}
        </v-card-title>
        <v-card-text>
          <v-form ref="formRef" v-model="valid">
            <v-text-field
              v-model="form.name"
              label="Название"
              :rules="[validationRules.required]"
              variant="outlined"
              class="mb-3"
            />

            <v-switch
              v-model="form.enabled"
              label="Включено"
              color="success"
              class="mb-3"
            />

            <v-select
              v-model="form.event_type"
              :items="eventTypeOptions"
              label="Тип события"
              :rules="[validationRules.required]"
              variant="outlined"
              class="mb-3"
              @update:model-value="onEventTypeChange"
            />

            <v-divider class="my-4" />

            <div class="d-flex justify-space-between align-center mb-2">
              <h3>Условия (conditions_json)</h3>
              <v-btn
                size="small"
                variant="text"
                prepend-icon="mdi-file-document-outline"
                @click="loadConditionTemplate"
              >
                Загрузить шаблон
              </v-btn>
            </div>
            <v-textarea
              v-model="conditionsJsonText"
              label="JSON условия"
              :rules="[validationRules.validJson]"
              variant="outlined"
              rows="8"
              :error-messages="conditionsJsonError"
              @update:model-value="onConditionsJsonChange"
              class="mb-3"
            />
            <v-alert
              v-if="conditionsJsonError"
              type="error"
              density="compact"
              class="mb-3"
            >
              {{ conditionsJsonError }}
            </v-alert>

            <v-divider class="my-4" />

            <div class="d-flex justify-space-between align-center mb-2">
              <h3>Действия (actions_json)</h3>
              <v-btn
                size="small"
                variant="text"
                prepend-icon="mdi-file-document-outline"
                @click="loadActionTemplate"
              >
                Загрузить шаблон
              </v-btn>
            </div>
            <v-textarea
              v-model="actionsJsonText"
              label="JSON действий (массив)"
              :rules="[validationRules.validJson, validationRules.minActions]"
              variant="outlined"
              rows="8"
              :error-messages="actionsJsonError"
              @update:model-value="onActionsJsonChange"
              class="mb-3"
            />
            <v-alert
              v-if="actionsJsonError"
              type="error"
              density="compact"
              class="mb-3"
            >
              {{ actionsJsonError }}
            </v-alert>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Отмена</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="!valid" @click="save">
            Сохранить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить правило?</v-card-title>
        <v-card-text>
          Вы уверены, что хотите удалить правило "{{ deletingItem?.name }}"?
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Отмена</v-btn>
          <v-btn color="error" :loading="deleting" @click="deleteItem">Удалить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { rulesApi, type RuleDTO, EVENT_TYPES, CONDITION_TEMPLATE, ACTION_TEMPLATE } from '../../api/rules'
import DataTable from '../../components/admin/DataTable.vue'

const search = ref('')
const loading = ref(false)
const rules = ref<RuleDTO[]>([])
const pagination = ref({ current_page: 1, per_page: 20, total: 0 })

const headers = [
  { title: 'Название', key: 'name' },
  { title: 'Включено', key: 'enabled', width: '120px' },
  { title: 'Тип события', key: 'event_type' },
  { title: 'Обновлено', key: 'updated_at' },
  { title: 'Действия', key: 'actions', sortable: false, width: '180px' },
]

const dialog = ref(false)
const editing = ref(false)
const saving = ref(false)
const valid = ref(false)
const formRef = ref()

const form = ref({
  name: '',
  enabled: true,
  event_type: '',
  conditions_json: {} as any,
  actions_json: [] as any[],
})

const conditionsJsonText = ref('')
const conditionsJsonError = ref('')
const actionsJsonText = ref('')
const actionsJsonError = ref('')

const eventTypeOptions = EVENT_TYPES.map((et) => ({
  title: et.title,
  value: et.value,
}))

const validationRules = {
  required: (v: string) => !!v || 'Обязательное поле',
  validJson: (v: string) => {
    if (!v) return true
    try {
      JSON.parse(v)
      return true
    } catch (e) {
      return 'Некорректный JSON'
    }
  },
  minActions: (v: string) => {
    if (!v) return 'Необходимо указать хотя бы одно действие'
    try {
      const parsed = JSON.parse(v)
      if (!Array.isArray(parsed)) {
        return 'Должен быть массив'
      }
      if (parsed.length === 0) {
        return 'Необходимо указать хотя бы одно действие'
      }
      return true
    } catch (e) {
      return true // JSON validation will catch this
    }
  },
}

const formatDate = (date?: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getEventTypeLabel = (eventType?: string) => {
  if (!eventType) return '-'
  return EVENT_TYPES.find((et) => et.value === eventType)?.title || eventType
}

const loadRules = async () => {
  loading.value = true
  try {
    const response = await rulesApi.list({
      q: search.value || undefined,
      per_page: pagination.value.per_page,
      page: pagination.value.current_page,
    })
    rules.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
    }
  } catch (error) {
    console.error('Failed to load rules:', error)
  } finally {
    loading.value = false
  }
}

const onSearch = (value: string) => {
  search.value = value
  pagination.value.current_page = 1
  loadRules()
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadRules()
}

const onEventTypeChange = () => {
  if (form.value.conditions_json) {
    form.value.conditions_json.event_type = form.value.event_type
    updateConditionsJsonText()
  }
}

const onConditionsJsonChange = () => {
  conditionsJsonError.value = ''
  try {
    const parsed = JSON.parse(conditionsJsonText.value)
    form.value.conditions_json = parsed
    if (parsed.event_type) {
      form.value.event_type = parsed.event_type
    }
  } catch (e: any) {
    conditionsJsonError.value = `Ошибка парсинга JSON: ${e.message}`
  }
}

const onActionsJsonChange = () => {
  actionsJsonError.value = ''
  try {
    const parsed = JSON.parse(actionsJsonText.value)
    if (!Array.isArray(parsed)) {
      actionsJsonError.value = 'Должен быть массив'
      return
    }
    if (parsed.length === 0) {
      actionsJsonError.value = 'Необходимо указать хотя бы одно действие'
      return
    }
    form.value.actions_json = parsed
  } catch (e: any) {
    actionsJsonError.value = `Ошибка парсинга JSON: ${e.message}`
  }
}

const updateConditionsJsonText = () => {
  try {
    conditionsJsonText.value = JSON.stringify(form.value.conditions_json, null, 2)
    conditionsJsonError.value = ''
  } catch (e: any) {
    conditionsJsonError.value = `Ошибка сериализации: ${e.message}`
  }
}

const updateActionsJsonText = () => {
  try {
    actionsJsonText.value = JSON.stringify(form.value.actions_json, null, 2)
    actionsJsonError.value = ''
  } catch (e: any) {
    actionsJsonError.value = `Ошибка сериализации: ${e.message}`
  }
}

const loadConditionTemplate = () => {
  const template = { ...CONDITION_TEMPLATE }
  if (form.value.event_type) {
    template.event_type = form.value.event_type
  }
  form.value.conditions_json = template
  updateConditionsJsonText()
}

const loadActionTemplate = () => {
  form.value.actions_json = [{ ...ACTION_TEMPLATE }]
  updateActionsJsonText()
}

const openDialog = (item?: RuleDTO) => {
  editing.value = !!item
  if (item) {
    form.value = {
      name: item.name,
      enabled: item.enabled,
      event_type: item.conditions_json?.event_type || '',
      conditions_json: item.conditions_json || {},
      actions_json: item.actions_json || [],
    }
    deletingItem.value = item
  } else {
    form.value = {
      name: '',
      enabled: true,
      event_type: '',
      conditions_json: {},
      actions_json: [],
    }
    deletingItem.value = null
  }
  updateConditionsJsonText()
  updateActionsJsonText()
  dialog.value = true
}

const save = async () => {
  if (!formRef.value?.validate()) {
    return
  }

  // Validate JSON before saving
  if (conditionsJsonError.value || actionsJsonError.value) {
    return
  }

  saving.value = true
  try {
    const payload: any = {
      name: form.value.name,
      enabled: form.value.enabled,
      conditions_json: form.value.conditions_json,
      actions_json: form.value.actions_json,
    }

    if (editing.value && deletingItem.value) {
      await rulesApi.update(deletingItem.value.id, payload)
    } else {
      await rulesApi.create(payload)
    }
    dialog.value = false
    deletingItem.value = null
    await loadRules()
  } catch (error: any) {
    console.error('Failed to save rule:', error)
    if (error.response?.data?.errors) {
      // Handle validation errors
      const errors = error.response.data.errors
      if (errors.conditions_json) {
        conditionsJsonError.value = errors.conditions_json.join(', ')
      }
      if (errors.actions_json) {
        actionsJsonError.value = errors.actions_json.join(', ')
      }
    }
  } finally {
    saving.value = false
  }
}

const deleteDialog = ref(false)
const deleting = ref(false)
const deletingItem = ref<RuleDTO | null>(null)

const confirmDelete = (item: RuleDTO) => {
  deletingItem.value = item
  deleteDialog.value = true
}

const deleteItem = async () => {
  if (!deletingItem.value) return

  deleting.value = true
  try {
    await rulesApi.delete(deletingItem.value.id)
    deleteDialog.value = false
    await loadRules()
  } catch (error) {
    console.error('Failed to delete rule:', error)
  } finally {
    deleting.value = false
    deletingItem.value = null
  }
}

const toggleRule = async (id: number) => {
  try {
    await rulesApi.toggle(id)
    await loadRules()
  } catch (error) {
    console.error('Failed to toggle rule:', error)
  }
}

onMounted(() => {
  loadRules()
})
</script>
