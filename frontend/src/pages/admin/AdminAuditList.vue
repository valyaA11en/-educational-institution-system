<template>
  <v-card>
    <v-card-title class="d-flex justify-space-between align-center">
      <span>Журнал аудита</span>
      <v-btn
        color="success"
        prepend-icon="mdi-file-excel"
        @click="handleExport"
        :loading="exporting"
      >
        Экспорт XLSX
      </v-btn>
    </v-card-title>

    <v-card-text>
      <!-- Filters -->
      <v-row>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.entity"
            label="Сущность"
            density="compact"
            variant="outlined"
            clearable
            @update:model-value="loadData"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.action"
            label="Действие"
            density="compact"
            variant="outlined"
            clearable
            @update:model-value="loadData"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model.number="filters.userId"
            label="ID пользователя"
            type="number"
            density="compact"
            variant="outlined"
            clearable
            @update:model-value="loadData"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.search"
            label="Поиск"
            density="compact"
            variant="outlined"
            clearable
            prepend-inner-icon="mdi-magnify"
            @update:model-value="handleSearch"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.dateFrom"
            label="Дата от"
            type="date"
            density="compact"
            variant="outlined"
            clearable
            @update:model-value="loadData"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.dateTo"
            label="Дата до"
            type="date"
            density="compact"
            variant="outlined"
            clearable
            @update:model-value="loadData"
          />
        </v-col>
      </v-row>

      <!-- Table -->
      <v-data-table
        :headers="headers"
        :items="logs"
        :loading="loading"
        :items-per-page="filters.per_page || 50"
        :page="currentPage"
        @update:page="handlePageChange"
        class="elevation-1"
      >
        <template v-slot:item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <template v-slot:item.user_fio="{ item }">
          <div>
            <div>{{ item.user_fio || '-' }}</div>
            <div v-if="item.user_email" class="text-caption text-grey">
              {{ item.user_email }}
            </div>
          </div>
        </template>

        <template v-slot:item.entity_id="{ item }">
          <v-btn
            v-if="item.entity_id"
            variant="text"
            size="small"
            color="primary"
            @click="goToEntity(item.entity, item.entity_id)"
          >
            {{ item.entity_id }}
          </v-btn>
          <span v-else>-</span>
        </template>

        <template v-slot:item.detail="{ item }">
          <v-btn
            variant="text"
            size="small"
            color="primary"
            @click="goToDetail(item.id)"
          >
            Подробнее
          </v-btn>
        </template>
      </v-data-table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="d-flex justify-center mt-4">
        <v-pagination
          v-model="currentPage"
          :length="totalPages"
          @update:model-value="handlePageChange"
        />
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { auditApi, type AuditLogDTO, type AuditLogFilters } from '@/api/audit'

const router = useRouter()

const loading = ref(false)
const exporting = ref(false)
const logs = ref<AuditLogDTO[]>([])
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)

const filters = ref<AuditLogFilters>({
  per_page: 50,
  page: 1,
})

const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Дата', key: 'created_at', width: '180px' },
  { title: 'Пользователь', key: 'user_fio', width: '200px' },
  { title: 'Действие', key: 'action', width: '200px' },
  { title: 'Сущность', key: 'entity', width: '150px' },
  { title: 'ID сущности', key: 'entity_id', width: '120px' },
  { title: 'IP', key: 'ip', width: '150px' },
  { title: '', key: 'detail', width: '120px', sortable: false },
]

const loadData = async () => {
  loading.value = true
  try {
    filters.value.page = currentPage.value
    const response = await auditApi.list(filters.value)
    logs.value = response.data
    currentPage.value = response.current_page
    totalPages.value = response.last_page
    total.value = response.total
  } catch (error) {
    console.error('Failed to load audit logs:', error)
  } finally {
    loading.value = false
  }
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadData()
}

const handleSearch = (() => {
  let timeout: NodeJS.Timeout | null = null
  return (value: string | null) => {
    if (timeout) clearTimeout(timeout)
    timeout = setTimeout(() => {
      filters.value.search = value || undefined
      currentPage.value = 1
      loadData()
    }, 500)
  }
})()

const handleExport = async () => {
  exporting.value = true
  try {
    await auditApi.export(filters.value)
  } catch (error) {
    console.error('Failed to export audit logs:', error)
  } finally {
    exporting.value = false
  }
}

const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const goToDetail = (id: number) => {
  router.push({ name: 'admin-audit-detail', params: { id } })
}

const goToEntity = (entity: string, entityId: number) => {
  // Map entity types to routes
  const entityRoutes: Record<string, string> = {
    'App\\Models\\User': 'admin-users',
    'App\\Models\\Group': 'admin-groups',
    'App\\Models\\Subject': 'admin-subjects',
    'App\\Models\\Document': 'document-view',
    'App\\Models\\Assignment': 'tasks', // TODO: add assignment detail route
    'App\\Models\\Lesson': 'lesson-journal',
  }

  const routeName = entityRoutes[entity]
  if (routeName) {
    router.push({ name: routeName, params: { id: entityId } })
  }
}

onMounted(() => {
  loadData()
})
</script>

