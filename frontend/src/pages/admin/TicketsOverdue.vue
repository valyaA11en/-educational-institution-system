<template>
  <div>
    <v-card>
      <v-card-title>
        <span class="text-h5">Просроченные тикеты</span>
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
            <v-text-field
              v-model.number="filters.overdue_hours_min"
              label="Просрочено часов (мин)"
              type="number"
              variant="outlined"
              density="compact"
              @update:model-value="loadTickets"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              v-model.number="filters.overdue_hours_max"
              label="Просрочено часов (макс)"
              type="number"
              variant="outlined"
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
          <template v-slot:item.overdue_hours="{ item }">
            <v-chip color="error" size="small">
              {{ item.overdue_hours.toFixed(1) }} ч
            </v-chip>
          </template>
          <template v-slot:item.resolution_due_at="{ item }">
            {{ formatDate(item.resolution_due_at) }}
          </template>
          <template v-slot:item.assignee="{ item }">
            {{ item.assignee?.fio || '-' }}
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ticketsApi, type OverdueTicketDTO } from '../../api/tickets'

const router = useRouter()

const loading = ref(false)
const tickets = ref<OverdueTicketDTO[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const filters = ref({
  status: null as string | null,
  priority: null as string | null,
  overdue_hours_min: null as number | null,
  overdue_hours_max: null as number | null,
})

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Заголовок', key: 'title' },
  { title: 'Категория', key: 'category' },
  { title: 'Статус', key: 'status' },
  { title: 'Приоритет', key: 'priority' },
  { title: 'Просрочено', key: 'overdue_hours' },
  { title: 'Дедлайн', key: 'resolution_due_at' },
  { title: 'Назначен', key: 'assignee' },
]

const statusOptions = [
  { title: 'Открыт', value: 'open' },
  { title: 'В работе', value: 'in_progress' },
]

const priorityOptions = [
  { title: 'Низкий', value: 'low' },
  { title: 'Обычный', value: 'normal' },
  { title: 'Средний', value: 'medium' },
  { title: 'Высокий', value: 'high' },
  { title: 'Критический', value: 'critical' },
]

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    open: 'info',
    in_progress: 'warning',
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
  const labels: Record<string, string> = {
    low: 'Низкий',
    normal: 'Обычный',
    medium: 'Средний',
    high: 'Высокий',
    critical: 'Критический',
  }
  return labels[priority] || priority
}

const formatDate = (date?: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('ru-RU', {
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
    if (filters.value.overdue_hours_min !== null) params.overdue_hours_min = filters.value.overdue_hours_min
    if (filters.value.overdue_hours_max !== null) params.overdue_hours_max = filters.value.overdue_hours_max

    const response = await ticketsApi.overdue(params)
    tickets.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load overdue tickets:', error)
  } finally {
    loading.value = false
  }
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadTickets()
}

const openTicket = (event: any, row: OverdueTicketDTO) => {
  router.push({ name: 'ticket-view', params: { id: row.id } })
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


