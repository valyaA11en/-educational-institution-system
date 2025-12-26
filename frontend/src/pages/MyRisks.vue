<template>
  <div>
    <v-card>
      <v-card-title>
        <span class="text-h5">Мои риски</span>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col
            v-for="risk in risks"
            :key="risk.id"
            cols="12"
            md="6"
            lg="4"
          >
            <v-card
              :color="getLevelColor(risk.level)"
              variant="tonal"
              class="h-100"
            >
              <v-card-title class="d-flex justify-space-between align-center">
                <span>{{ getRiskTypeLabel(risk.risk_type) }}</span>
                <v-chip :color="getLevelColor(risk.level)" size="small">
                  {{ getLevelLabel(risk.level) }}
                </v-chip>
              </v-card-title>
              <v-card-text>
                <div class="mb-2">
                  <strong>Оценка риска:</strong> {{ risk.score.toFixed(2) }}
                </div>
                <div class="mb-2">
                  <strong>Рассчитано:</strong> {{ formatDateTime(risk.calculated_at) }}
                </div>
                <v-btn
                  size="small"
                  variant="outlined"
                  prepend-icon="mdi-information"
                  @click="openDetails(risk)"
                  class="mt-2"
                >
                  Подробнее
                </v-btn>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-alert
          v-if="risks.length === 0 && !loading"
          type="info"
          class="mt-4"
        >
          У вас нет активных рисков
        </v-alert>
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

const loading = ref(false)
const risks = ref<RiskDTO[]>([])

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

const loadRisks = async () => {
  loading.value = true
  try {
    const response = await analyticsApi.getMyRisks()
    risks.value = response.data || []
  } catch (error) {
    console.error('Failed to load risks:', error)
  } finally {
    loading.value = false
  }
}

const openDetails = (risk: RiskDTO) => {
  selectedRisk.value = risk
  detailsDialog.value = true
}

onMounted(() => {
  loadRisks()
})
</script>


