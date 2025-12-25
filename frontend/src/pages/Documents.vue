<template>
  <div>
    <v-card>
      <v-card-title>
        <span class="text-h5">Документы</span>
      </v-card-title>
      <v-card-text>
        <v-data-table
          :headers="headers"
          :items="documents"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
          @update:items-per-page="onItemsPerPageChange"
        >
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ getStatusText(item.status) }}
            </v-chip>
          </template>
          <template v-slot:item.date="{ item }">
            {{ formatDate(item.date) }}
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

const headers = [
  { title: 'Номер', key: 'number', sortable: true },
  { title: 'Тип', key: 'type', sortable: true },
  { title: 'Статус', key: 'status', sortable: true },
  { title: 'Дата', key: 'date', sortable: true },
  { title: 'Действия', key: 'actions', sortable: false, width: '100px' },
]

const loadDocuments = async () => {
  loading.value = true
  try {
    const response: DocumentListResponse = await documentsApi.list({
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    })
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

const onItemsPerPageChange = (itemsPerPage: number) => {
  pagination.value.per_page = itemsPerPage
  pagination.value.current_page = 1
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

