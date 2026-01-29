<template>
  <div>
    <v-card>
      <v-card-title>
        <div class="d-flex justify-space-between align-center">
          <span class="text-h5">Мои тикеты</span>
          <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">
            Создать тикет
          </v-btn>
        </div>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Статус"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadTickets"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.priority"
              :items="priorityOptions"
              label="Приоритет"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadTickets"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-checkbox
              v-model="filters.overdue"
              label="Только просроченные"
              density="compact"
              @update:model-value="loadTickets"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="tickets"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
          @click:row="openTicket"
          class="cursor-pointer"
        >
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ getStatusLabel(item.status) }}
            </v-chip>
          </template>
          <template v-slot:item.priority="{ item }">
            <v-chip :color="getPriorityColor(item.priority)" size="small">
              {{ getPriorityLabel(item.priority) }}
            </v-chip>
          </template>
          <template v-slot:item.sla="{ item }">
            <div class="d-flex flex-column">
              <SlaTimer
                v-if="item.first_response_due_at"
                :due-at="item.first_response_due_at"
                :responded-at="item.first_response_at"
                label="Ответ"
                size="small"
              />
              <SlaTimer
                v-if="item.resolution_due_at"
                :due-at="item.resolution_due_at"
                :resolved-at="item.resolved_at"
                label="Решение"
                size="small"
                class="mt-1"
              />
            </div>
          </template>
          <template v-slot:item.is_overdue="{ item }">
            <v-chip v-if="item.is_overdue" color="error" size="small">
              Просрочен
            </v-chip>
          </template>
          <template v-slot:item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Create Ticket Dialog -->
    <v-dialog v-model="createDialog" max-width="800" scrollable>
      <v-card>
        <v-card-title>Создать тикет</v-card-title>
        <v-card-text>
          <v-form ref="formRef" v-model="valid">
            <v-text-field
              v-model="form.title"
              label="Заголовок"
              :rules="[rules.required]"
              variant="outlined"
              class="mb-3"
            />
            <v-textarea
              v-model="form.description"
              label="Описание"
              :rules="[rules.required]"
              variant="outlined"
              rows="4"
              class="mb-3"
            />
            <v-text-field
              v-model="form.category"
              label="Категория"
              :rules="[rules.required]"
              variant="outlined"
              class="mb-3"
            />
            <v-select
              v-model="form.priority"
              :items="priorityOptions"
              label="Приоритет"
              :rules="[rules.required]"
              variant="outlined"
              class="mb-3"
            />
            <v-select
              v-model="form.assigned_to"
              :items="userOptions"
              label="Назначить (опционально)"
              variant="outlined"
              clearable
              class="mb-3"
            />
            <div class="mb-3">
              <label class="text-body-2 mb-2 d-block">Вложения</label>
              <v-alert type="info" density="compact" class="mb-2">
                Файлы можно будет прикрепить после создания тикета
              </v-alert>
              <!-- FileUploader will be available after ticket creation -->
            </div>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="createDialog = false">Отмена</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="!valid" @click="createTicket">
            Создать
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ticketsApi, type TicketDTO } from '../api/tickets'
import SlaTimer from '../components/SlaTimer.vue'

const router = useRouter()

const loading = ref(false)
const tickets = ref<TicketDTO[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const filters = ref({
  status: null as string | null,
  priority: null as string | null,
  overdue: false,
})

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Заголовок', key: 'title' },
  { title: 'Категория', key: 'category' },
  { title: 'Статус', key: 'status' },
  { title: 'Приоритет', key: 'priority' },
  { title: 'SLA', key: 'sla', sortable: false },
  { title: 'Просрочен', key: 'is_overdue' },
  { title: 'Создан', key: 'created_at' },
]

const statusOptions = [
  { title: 'Открыт', value: 'open' },
  { title: 'В работе', value: 'in_progress' },
  { title: 'Решен', value: 'resolved' },
  { title: 'Закрыт', value: 'closed' },
]

const priorityOptions = [
  { title: 'Низкий', value: 'low' },
  { title: 'Обычный', value: 'normal' },
  { title: 'Средний', value: 'medium' },
  { title: 'Высокий', value: 'high' },
  { title: 'Критический', value: 'critical' },
]

const userOptions = ref<{ title: string; value: number }[]>([])

const createDialog = ref(false)
const saving = ref(false)
const valid = ref(false)
const formRef = ref()

const form = ref({
  title: '',
  description: '',
  category: '',
  priority: 'normal' as 'low' | 'normal' | 'medium' | 'high' | 'critical',
  assigned_to: null as number | null,
  attachments: [] as number[],
})

const rules = {
  required: (v: string) => !!v || 'Обязательное поле',
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    open: 'info',
    in_progress: 'warning',
    resolved: 'success',
    closed: 'grey',
  }
  return colors[status] || 'grey'
}

const getStatusLabel = (status: string) => {
  return statusOptions.find((s) => s.value === status)?.title || status
}

const getPriorityColor = (priority: string) => {
  const colors: Record<string, string> = {
    low: 'grey',
    normal: 'blue',
    medium: 'orange',
    high: 'red',
    critical: 'error',
  }
  return colors[priority] || 'grey'
}

const getPriorityLabel = (priority: string) => {
  return priorityOptions.find((p) => p.value === priority)?.title || priority
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

const loadTickets = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.priority) params.priority = filters.value.priority
    if (filters.value.overdue) params.overdue = true

    const response = await ticketsApi.list(params)
    tickets.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load tickets:', error)
  } finally {
    loading.value = false
  }
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadTickets()
}

const openTicket = (event: any, row: TicketDTO) => {
  router.push({ name: 'ticket-view', params: { id: row.id } })
}

const openCreateDialog = () => {
  form.value = {
    title: '',
    description: '',
    category: '',
    priority: 'normal',
    assigned_to: null,
    attachments: [],
  }
  createDialog.value = true
}

const createTicket = async () => {
  if (!formRef.value?.validate()) {
    return
  }

  saving.value = true
  try {
    await ticketsApi.create({
      title: form.value.title,
      description: form.value.description,
      category: form.value.category,
      priority: form.value.priority,
      assigned_to: form.value.assigned_to || undefined,
      attachments: form.value.attachments,
    })
    createDialog.value = false
    await loadTickets()
  } catch (error) {
    console.error('Failed to create ticket:', error)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadTickets()
})
</script>

<style scoped>
.cursor-pointer :deep(.v-data-table__tr) {
  cursor: pointer;
}
</style>

