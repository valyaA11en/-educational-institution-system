<template>
  <div>
    <v-card>
      <v-card-title class="text-h5">Жалобы на сообщения</v-card-title>
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
              @update:model-value="loadReports"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.threadId"
              label="ID чата"
              type="number"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="loadReports"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="reports"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
        >
          <template v-slot:item.thread="{ item }">
            {{ item.thread?.name || `ID: ${item.thread_id}` }}
          </template>
          <template v-slot:item.message="{ item }">
            <div class="text-truncate" style="max-width: 300px">
              {{ item.message?.text || 'Сообщение удалено' }}
            </div>
          </template>
          <template v-slot:item.reported_by="{ item }">
            {{ item.reporter?.fio || `ID: ${item.reported_by}` }}
          </template>
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ getStatusText(item.status) }}
            </v-chip>
          </template>
          <template v-slot:item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>
          <template v-slot:item.actions="{ item }">
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  v-bind="props"
                />
              </template>
              <v-list>
                <v-list-item @click="updateReportStatus(item, 'reviewed')">
                  <v-list-item-title>Пометить как просмотренное</v-list-item-title>
                </v-list-item>
                <v-list-item @click="updateReportStatus(item, 'closed')">
                  <v-list-item-title>Закрыть</v-list-item-title>
                </v-list-item>
                <v-list-item @click="goToChat(item)">
                  <v-list-item-title>Перейти в чат</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { chatApi, type ChatReportDTO } from '../../api/chat'
import { useToast } from '../../composables/useToast'

const router = useRouter()
const { showToast } = useToast()

const loading = ref(false)
const reports = ref<ChatReportDTO[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const filters = ref({
  status: null as string | null,
  threadId: null as number | null,
})

const statusOptions = [
  { title: 'Открыто', value: 'open' },
  { title: 'Просмотрено', value: 'reviewed' },
  { title: 'Закрыто', value: 'closed' },
]

const headers = [
  { title: 'Чат', key: 'thread', sortable: false },
  { title: 'Сообщение', key: 'message', sortable: false },
  { title: 'Жалобу подал', key: 'reported_by', sortable: false },
  { title: 'Причина', key: 'reason', sortable: false },
  { title: 'Статус', key: 'status', sortable: true },
  { title: 'Дата', key: 'created_at', sortable: true },
  { title: 'Действия', key: 'actions', sortable: false, width: '100px' },
]

const loadReports = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.threadId) params.threadId = filters.value.threadId

    const response = await chatApi.getReports(params)
    reports.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load reports:', error)
    showToast('Ошибка загрузки жалоб', 'error')
  } finally {
    loading.value = false
  }
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadReports()
}

const updateReportStatus = async (report: ChatReportDTO, status: 'reviewed' | 'closed') => {
  try {
    await chatApi.updateReport(report.id, status)
    showToast('Статус обновлен', 'success')
    await loadReports()
  } catch (error: any) {
    console.error('Failed to update report:', error)
    showToast(error.response?.data?.message || 'Ошибка обновления статуса', 'error')
  }
}

const goToChat = (report: ChatReportDTO) => {
  router.push({ name: 'chat', query: { threadId: report.thread_id } })
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleString('ru-RU')
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    open: 'error',
    reviewed: 'warning',
    closed: 'success',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    open: 'Открыто',
    reviewed: 'Просмотрено',
    closed: 'Закрыто',
  }
  return texts[status] || status
}

onMounted(() => {
  loadReports()
})
</script>









