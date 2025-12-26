<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h5">Риски студентов</span>
        <v-btn
          color="success"
          prepend-icon="mdi-file-excel"
          @click="exportToExcel"
          :loading="exporting"
        >
          Export Excel
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.groupId"
              :items="groupOptions"
              label="Группа"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadRisks"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.level"
              :items="levelOptions"
              label="Уровень риска"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadRisks"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.risk_type"
              :items="riskTypeOptions"
              label="Тип риска"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadRisks"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="risks"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
        >
          <template v-slot:item.user="{ item }">
            {{ item.user?.fio || `ID: ${item.user_id}` }}
          </template>
          <template v-slot:item.level="{ item }">
            <v-chip :color="getLevelColor(item.level)" size="small">
              {{ getLevelLabel(item.level) }}
            </v-chip>
          </template>
          <template v-slot:item.risk_type="{ item }">
            {{ getRiskTypeLabel(item.risk_type) }}
          </template>
          <template v-slot:item.score="{ item }">
            {{ item.score.toFixed(2) }}
          </template>
          <template v-slot:item.calculated_at="{ item }">
            {{ formatDate(item.calculated_at) }}
          </template>
          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-information"
              size="small"
              variant="text"
              @click="openDetails(item)"
            />
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="600">
      <v-card v-if="selectedRisk">
        <v-card-title>
          Детали риска
        </v-card-title>
        <v-card-text>
          <div class="mb-3">
            <strong>Студент:</strong> {{ selectedRisk.user?.fio || `ID: ${selectedRisk.user_id}` }}
          </div>
          <div class="mb-3">
            <strong>Тип риска:</strong> {{ getRiskTypeLabel(selectedRisk.risk_type) }}
          </div>
          <div class="mb-3">
            <strong>Уровень:</strong>
            <v-chip :color="getLevelColor(selectedRisk.level)" size="small" class="ml-2">
              {{ getLevelLabel(selectedRisk.level) }}
            </v-chip>
          </div>
          <div class="mb-3">
            <strong>Оценка:</strong> {{ selectedRisk.score.toFixed(2) }}
          </div>
          <div class="mb-3">
            <strong>Рассчитано:</strong> {{ formatDateTime(selectedRisk.calculated_at) }}
          </div>
          <v-divider class="my-3" />
          <div>
            <strong>Детали:</strong>
            <pre class="mt-2 pa-3" style="background-color: rgba(0, 0, 0, 0.05); border-radius: 4px; overflow-x: auto;">{{ formatDetails(selectedRisk.details_json) }}</pre>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="detailsDialog = false">Закрыть</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { analyticsApi, type RiskDTO } from '../api/analytics'
import { directoryApi } from '../api/directory'

const loading = ref(false)
const exporting = ref(false)
const risks = ref<RiskDTO[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const filters = ref({
  groupId: null as number | null,
  level: null as 'green' | 'yellow' | 'red' | null,
  risk_type: null as 'avg_low' | 'absences_high' | 'debts_high' | 'no_activity' | null,
})

const groupOptions = ref<{ title: string; value: number }[]>([])
const levelOptions = [
  { title: 'Зеленый', value: 'green' },
  { title: 'Желтый', value: 'yellow' },
  { title: 'Красный', value: 'red' },
]

const riskTypeOptions = [
  { title: 'Низкая средняя оценка', value: 'avg_low' },
  { title: 'Высокие пропуски', value: 'absences_high' },
  { title: 'Высокие долги', value: 'debts_high' },
  { title: 'Нет активности', value: 'no_activity' },
]

const headers = [
  { title: 'Студент', key: 'user' },
  { title: 'Тип риска', key: 'risk_type' },
  { title: 'Уровень', key: 'level' },
  { title: 'Оценка', key: 'score' },
  { title: 'Рассчитано', key: 'calculated_at' },
  { title: 'Действия', key: 'actions', sortable: false, width: '100px' },
]

const detailsDialog = ref(false)
const selectedRisk = ref<RiskDTO | null>(null)

const getLevelColor = (level: string) => {
  const colors: Record<string, string> = {
    green: 'success',
    yellow: 'warning',
    red: 'error',
  }
  return colors[level] || 'grey'
}

const getLevelLabel = (level: string) => {
  const labels: Record<string, string> = {
    green: 'Зеленый',
    yellow: 'Желтый',
    red: 'Красный',
  }
  return labels[level] || level
}

const getRiskTypeLabel = (riskType: string) => {
  const labels: Record<string, string> = {
    avg_low: 'Низкая средняя оценка',
    absences_high: 'Высокие пропуски',
    debts_high: 'Высокие долги',
    no_activity: 'Нет активности',
  }
  return labels[riskType] || riskType
}

const formatDate = (date?: string | null) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('ru-RU')
}

const formatDateTime = (date?: string | null) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatDetails = (details: Record<string, any> | null) => {
  if (!details) return 'Нет данных'
  return JSON.stringify(details, null, 2)
}

const loadGroups = async () => {
  try {
    const groups = await directoryApi.getGroups()
    groupOptions.value = groups.map((g) => ({
      title: g.name,
      value: g.id,
    }))
  } catch (error) {
    console.error('Failed to load groups:', error)
  }
}

const loadRisks = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    }
    if (filters.value.groupId) params.groupId = filters.value.groupId
    if (filters.value.level) params.level = filters.value.level
    if (filters.value.risk_type) params.risk_type = filters.value.risk_type

    const response = await analyticsApi.getRisks(params)
    risks.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load risks:', error)
  } finally {
    loading.value = false
  }
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadRisks()
}

const openDetails = (risk: RiskDTO) => {
  selectedRisk.value = risk
  detailsDialog.value = true
}

const exportToExcel = async () => {
  exporting.value = true
  try {
    const params: any = {}
    if (filters.value.groupId) params.groupId = filters.value.groupId
    if (filters.value.level) params.level = filters.value.level
    if (filters.value.risk_type) params.risk_type = filters.value.risk_type

    await analyticsApi.exportRisks(params)
  } catch (error) {
    console.error('Failed to export risks:', error)
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await loadGroups()
  await loadRisks()
})
</script>

