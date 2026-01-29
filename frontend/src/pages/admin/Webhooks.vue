<template>
  <div>
    <DataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :pagination="pagination"
      search-label="Поиск по названию или URL"
      create-button-text="Добавить webhook"
      @search="onSearch"
      @page-change="onPageChange"
      @create="openDialog()"
    >
      <template v-slot:item.enabled="{ item }">
        <v-chip :color="item.enabled ? 'success' : 'error'" size="small">
          {{ item.enabled ? 'Включен' : 'Выключен' }}
        </v-chip>
      </template>
      <template v-slot:item.event_types="{ item }">
        <div class="d-flex flex-wrap ga-1">
          <v-chip
            v-for="eventType in item.event_types"
            :key="eventType"
            size="small"
            variant="outlined"
          >
            {{ getEventTypeLabel(eventType) }}
          </v-chip>
        </div>
      </template>
      <template v-slot:item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openDialog(item)" />
        <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)" />
      </template>
    </DataTable>

    <FormDialog
      v-model="dialog"
      :title="editing ? 'Редактировать webhook' : 'Создать webhook'"
      :saving="saving"
      max-width="700"
      @save="save"
      @cancel="dialog = false"
    >
      <template v-slot:form="{ rules }">
        <v-text-field
          v-model="form.name"
          label="Название"
          :rules="[rules.required]"
          variant="outlined"
        />
        <v-text-field
          v-model="form.url"
          label="URL"
          :rules="[rules.required, (v: string) => {
            if (!v) return true
            try {
              new URL(v)
              return true
            } catch {
              return 'Некорректный URL'
            }
          }]"
          variant="outlined"
          hint="Полный URL для отправки webhook"
          persistent-hint
        />
        <v-text-field
          v-model="form.secret"
          label="Secret"
          :rules="editing ? [] : [rules.required, (v: string) => !v || v.length >= 16 || 'Минимум 16 символов']"
          variant="outlined"
          hint="Секретный ключ для подписи запросов (минимум 16 символов). При создании можно оставить пустым - будет сгенерирован автоматически."
          persistent-hint
          :append-inner-icon="showSecret ? 'mdi-eye-off' : 'mdi-eye'"
          :type="showSecret ? 'text' : 'password'"
          @click:append-inner="showSecret = !showSecret"
        />
        <v-switch
          v-model="form.enabled"
          label="Включен"
          color="primary"
        />
        <v-select
          v-model="form.event_types"
          :items="eventTypeOptions"
          label="Типы событий"
          multiple
          chips
          :rules="[rules.required]"
          variant="outlined"
          hint="Выберите типы событий, на которые должен реагировать webhook"
          persistent-hint
        />
      </template>
    </FormDialog>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить webhook?</v-card-title>
        <v-card-text>Вы уверены, что хотите удалить webhook "{{ deletingItem?.name }}"?</v-card-text>
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
import { useCrudTable } from '../../composables/useCrudTable'
import type { WebhookEndpointDTO } from '../../api/webhooks'
import { EVENT_TYPES } from '../../api/rules'
import DataTable from '../../components/admin/DataTable.vue'
import FormDialog from '../../components/admin/FormDialog.vue'

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Название', key: 'name' },
  { title: 'URL', key: 'url' },
  { title: 'Статус', key: 'enabled' },
  { title: 'Типы событий', key: 'event_types' },
  { title: 'Действия', key: 'actions', sortable: false, width: '120px' },
]

const {
  items,
  loading,
  saving,
  deleting,
  pagination,
  load,
  create,
  update,
  remove,
  onSearch,
  onPageChange,
} = useCrudTable<WebhookEndpointDTO>({
  resourceUrl: '/v1/admin/webhooks',
})

const dialog = ref(false)
const editing = ref(false)
const showSecret = ref(false)
const form = ref({
  name: '',
  url: '',
  secret: '',
  enabled: true,
  event_types: [] as string[],
})
const deleteDialog = ref(false)
const deletingItem = ref<WebhookEndpointDTO | null>(null)

const eventTypeOptions = EVENT_TYPES.map((et) => ({
  title: et.title,
  value: et.value,
}))

const getEventTypeLabel = (eventType: string) => {
  return EVENT_TYPES.find((et) => et.value === eventType)?.title || eventType
}

const originalSecret = ref('')

const openDialog = (item?: WebhookEndpointDTO) => {
  editing.value = !!item
  if (item) {
    form.value = {
      name: item.name,
      url: item.url,
      secret: '', // Don't show existing secret
      enabled: item.enabled,
      event_types: [...item.event_types],
    }
    originalSecret.value = item.secret
    deletingItem.value = item
  } else {
    form.value = {
      name: '',
      url: '',
      secret: '',
      enabled: true,
      event_types: [],
    }
    originalSecret.value = ''
    deletingItem.value = null
  }
  showSecret.value = false
  dialog.value = true
}

const save = async () => {
  try {
    const payload: any = {
      name: form.value.name,
      url: form.value.url,
      enabled: form.value.enabled,
      event_types: form.value.event_types,
    }

    // Only include secret if it's provided and changed
    if (form.value.secret && form.value.secret !== originalSecret.value) {
      payload.secret = form.value.secret
    }

    if (editing.value && deletingItem.value) {
      await update(deletingItem.value.id, payload)
    } else {
      // For new webhooks, backend will generate secret if not provided
      if (form.value.secret) {
        payload.secret = form.value.secret
      }
      await create(payload)
    }
    dialog.value = false
    deletingItem.value = null
  } catch (error) {
    console.error('Failed to save webhook:', error)
  }
}

const confirmDelete = (item: WebhookEndpointDTO) => {
  deletingItem.value = item
  deleteDialog.value = true
}

const deleteItem = async () => {
  if (!deletingItem.value) return
  try {
    await remove(deletingItem.value.id)
    deleteDialog.value = false
    deletingItem.value = null
  } catch (error) {
    console.error('Failed to delete webhook:', error)
  }
}

onMounted(() => {
  load()
})
</script>

