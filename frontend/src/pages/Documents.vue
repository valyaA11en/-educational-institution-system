<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h5">Документы</span>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="$router.push({ name: 'document-new' })"
        >
          Создать документ
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.search"
              label="Поиск по номеру"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="loadDocuments"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.type"
              :items="typeOptions"
              label="Тип"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadDocuments"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Статус"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadDocuments"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field
              v-model="filters.dateFrom"
              label="Дата от"
              type="date"
              variant="outlined"
              density="compact"
              @update:model-value="loadDocuments"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field
              v-model="filters.dateTo"
              label="Дата до"
              type="date"
              variant="outlined"
              density="compact"
              @update:model-value="loadDocuments"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="documents"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
        >
          <template v-slot:item.number="{ item }">
            {{ item.number || '-' }}
          </template>
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ getStatusText(item.status) }}
            </v-chip>
          </template>
          <template v-slot:item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>
          <template v-slot:item.creator="{ item }">
            {{ item.creator?.fio || `ID: ${item.created_by}` }}
          </template>
          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              @click="$router.push({ name: 'document-view', params: { id: item.id } })"
            />
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { documentsApi, type DocumentDTO, type DocumentListResponse } from '../api/documents'

const loading = ref(false)
const documents = ref<DocumentDTO[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const filters = ref({
  search: null as string | null,
  type: null as string | null,
  status: null as string | null,
  dateFrom: null as string | null,
  dateTo: null as string | null,
})

const typeOptions = [
  { title: 'Приказ', value: 'order' },
  { title: 'Решение', value: 'decision' },
  { title: 'Служебная записка', value: 'memo' },
  { title: 'Протокол', value: 'protocol' },
  { title: 'Заявление', value: 'statement' },
  { title: 'Ведомость', value: 'grade_sheet' },
]

const statusOptions = [
  { title: 'Черновик', value: 'draft' },
  { title: 'На проверке', value: 'on_review' },
  { title: 'Утвержден', value: 'approved' },
  { title: 'Подписан', value: 'signed' },
  { title: 'Архив', value: 'archived' },
]

const headers = [
  { title: 'Номер', key: 'number', sortable: true },
  { title: 'Тип', key: 'type', sortable: true },
  { title: 'Статус', key: 'status', sortable: true },
  { title: 'Дата', key: 'date', sortable: true },
  { title: 'Создатель', key: 'creator', sortable: false },
  { title: 'Действия', key: 'actions', sortable: false, width: '100px' },
]

const loadDocuments = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.type) params.type = filters.value.type
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.dateFrom) params.dateFrom = filters.value.dateFrom
    if (filters.value.dateTo) params.dateTo = filters.value.dateTo

    const response: DocumentListResponse = await documentsApi.list(params)
    documents.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load documents:', error)
  } finally {
    loading.value = false
  }
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadDocuments()
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('ru-RU')
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'grey',
    on_review: 'orange',
    approved: 'blue',
    signed: 'green',
    archived: 'default',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Черновик',
    on_review: 'На проверке',
    approved: 'Утвержден',
    signed: 'Подписан',
    archived: 'Архив',
  }
  return texts[status] || status
}

onMounted(() => {
  loadDocuments()
})
</script>
