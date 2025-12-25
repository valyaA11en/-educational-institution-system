<template>
  <div>
    <v-card>
      <v-card-title>
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Правила</span>
          <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">
            Создать правило
          </v-btn>
        </div>
      </v-card-title>
      <v-card-text>
        <v-data-table
          :headers="headers"
          :items="rules"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
          @update:items-per-page="onItemsPerPageChange"
        >
          <template v-slot:item.enabled="{ item }">
            <v-switch
              :model-value="item.enabled"
              @update:model-value="toggleRule(item.id)"
              hide-details
              density="compact"
            />
          </template>
          <template v-slot:item.scope="{ item }">
            <v-chip size="small">{{ getScopeText(item.scope) }}</v-chip>
          </template>
          <template v-slot:item.actions="{ item }">
            <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEditDialog(item)" />
            <v-btn icon="mdi-delete" size="small" variant="text" @click="deleteRule(item.id)" />
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialogOpen" max-width="800">
      <v-card>
        <v-card-title>{{ dialogMode === 'create' ? 'Создать правило' : 'Редактировать правило' }}</v-card-title>
        <v-card-text>
          <v-form ref="formRef">
            <v-text-field v-model="form.name" label="Название" required />
            <v-select
              v-model="form.scope"
              :items="scopeOptions"
              label="Область действия"
              required
            />
            <v-switch v-model="form.enabled" label="Включено" />
            
            <v-divider class="my-4" />
            <h3>Условия</h3>
            <div v-for="(condition, idx) in form.conditions_json" :key="idx" class="d-flex align-center mb-2">
              <v-text-field v-model="condition.field" label="Поле" density="compact" class="mr-2" />
              <v-select
                v-model="condition.operator"
                :items="operatorOptions"
                label="Оператор"
                density="compact"
                class="mr-2"
              />
              <v-text-field v-model="condition.value" label="Значение" density="compact" class="mr-2" />
              <v-btn icon="mdi-delete" size="small" @click="removeCondition(idx)" />
            </div>
            <v-btn prepend-icon="mdi-plus" @click="addCondition">Добавить условие</v-btn>

            <v-divider class="my-4" />
            <h3>Действия</h3>
            <div v-for="(action, idx) in form.actions_json" :key="idx" class="mb-2">
              <v-select
                v-model="action.type"
                :items="actionTypeOptions"
                label="Тип действия"
                density="compact"
                class="mb-2"
              />
              <v-textarea
                v-if="action.type === 'send_notification'"
                v-model="action.message"
                label="Сообщение"
                density="compact"
              />
              <v-btn icon="mdi-delete" size="small" @click="removeAction(idx)" />
            </div>
            <v-btn prepend-icon="mdi-plus" @click="addAction">Добавить действие</v-btn>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="dialogOpen = false">Отмена</v-btn>
          <v-btn color="primary" @click="saveRule">Сохранить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { rulesApi, type RuleDTO, type RuleListResponse } from '../../api/rules'

const loading = ref(false)
const rules = ref<RuleDTO[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const dialogOpen = ref(false)
const dialogMode = ref<'create' | 'edit'>('create')
const formRef = ref()
const form = ref<Partial<RuleDTO>>({
  name: '',
  enabled: true,
  scope: 'global',
  conditions_json: [],
  actions_json: [],
})

const headers = [
  { title: 'Название', key: 'name' },
  { title: 'Включено', key: 'enabled' },
  { title: 'Область', key: 'scope' },
  { title: 'Создано', key: 'created_at' },
  { title: 'Действия', key: 'actions', sortable: false },
]

const scopeOptions = [
  { title: 'Глобальная', value: 'global' },
  { title: 'Организация', value: 'org' },
  { title: 'Семестр', value: 'term' },
]

const operatorOptions = [
  { title: 'Равно', value: 'equals' },
  { title: 'Не равно', value: 'not_equals' },
  { title: 'Содержит', value: 'contains' },
  { title: 'Больше', value: 'greater_than' },
  { title: 'Меньше', value: 'less_than' },
  { title: 'В списке', value: 'in' },
  { title: 'Не в списке', value: 'not_in' },
]

const actionTypeOptions = [
  { title: 'Отправить уведомление', value: 'send_notification' },
  { title: 'Создать тикет', value: 'create_ticket' },
  { title: 'Обновить статус', value: 'update_status' },
  { title: 'Назначить роль', value: 'assign_role' },
  { title: 'Записать в лог', value: 'log_event' },
]

const loadRules = async () => {
  loading.value = true
  try {
    const response: RuleListResponse = await rulesApi.list({
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    })
    rules.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load rules:', error)
  } finally {
    loading.value = false
  }
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadRules()
}

const onItemsPerPageChange = (itemsPerPage: number) => {
  pagination.value.per_page = itemsPerPage
  pagination.value.current_page = 1
  loadRules()
}

const openCreateDialog = () => {
  dialogMode.value = 'create'
  form.value = {
    name: '',
    enabled: true,
    scope: 'global',
    conditions_json: [],
    actions_json: [],
  }
  dialogOpen.value = true
}

const openEditDialog = (rule: RuleDTO) => {
  dialogMode.value = 'edit'
  form.value = { ...rule }
  dialogOpen.value = true
}

const saveRule = async () => {
  if (dialogMode.value === 'create') {
    await rulesApi.create(form.value as any)
  } else {
    await rulesApi.update(form.value.id!, form.value)
  }
  dialogOpen.value = false
  loadRules()
}

const deleteRule = async (id: number) => {
  if (confirm('Удалить правило?')) {
    await rulesApi.delete(id)
    loadRules()
  }
}

const toggleRule = async (id: number) => {
  await rulesApi.toggle(id)
  loadRules()
}

const addCondition = () => {
  form.value.conditions_json!.push({ field: '', operator: 'equals', value: '' })
}

const removeCondition = (idx: number) => {
  form.value.conditions_json!.splice(idx, 1)
}

const addAction = () => {
  form.value.actions_json!.push({ type: 'send_notification' })
}

const removeAction = (idx: number) => {
  form.value.actions_json!.splice(idx, 1)
}

const getScopeText = (scope: string) => {
  return scopeOptions.find(o => o.value === scope)?.title || scope
}

onMounted(() => {
  loadRules()
})
</script>

