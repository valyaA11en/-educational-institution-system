<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 mb-4">Панель директора</h1>
      </v-col>
    </v-row>

    <v-row v-if="loading">
      <v-col cols="12" class="text-center">
        <v-progress-circular indeterminate size="64"></v-progress-circular>
      </v-col>
    </v-row>

    <template v-else>
      <!-- Общая статистика -->
      <v-row>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6 mb-2">Всего рисков</div>
              <div class="text-h4">{{ data.total_risks.total }}</div>
              <div class="text-caption text-medium-emphasis mt-2">
                Красные: {{ data.total_risks.red }}, Желтые: {{ data.total_risks.yellow }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6 mb-2">Групп с проблемами</div>
              <div class="text-h4">{{ data.groups_with_problems.groups_with_problems }}</div>
              <div class="text-caption text-medium-emphasis mt-2">
                {{ data.groups_with_problems.percent }}% от {{ data.groups_with_problems.total_groups }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6 mb-2">Документов на согласовании</div>
              <div class="text-h4">{{ data.documents.total_pending }}</div>
              <div class="text-caption text-medium-emphasis mt-2">
                Просрочено: {{ data.documents.overdue }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-h6 mb-2">Преподавателей</div>
              <div class="text-h4">{{ data.teacher_workload.length }}</div>
              <div class="text-caption text-medium-emphasis mt-2">
                Активных
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Графики -->
      <v-row>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Распределение рисков</v-card-title>
            <v-card-text>
              <div v-if="data.total_risks.total > 0" style="height: 300px;">
                <canvas ref="risksChart"></canvas>
              </div>
              <v-alert v-else type="success">Нет активных рисков</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Нагрузка преподавателей</v-card-title>
            <v-card-text>
              <div v-if="data.teacher_workload.length > 0" style="height: 300px;">
                <canvas ref="workloadChart"></canvas>
              </div>
              <v-alert v-else type="info">Нет данных о нагрузке</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Документы -->
      <v-row>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
              <span>Документы на согласовании</span>
              <v-chip color="warning">{{ data.documents.pending }}</v-chip>
            </v-card-title>
            <v-card-text>
              <v-alert type="info">
                Всего документов на согласовании: {{ data.documents.pending }}
                <template v-if="data.documents.overdue > 0">
                  <br>Просрочено: {{ data.documents.overdue }}
                </template>
              </v-alert>
              <v-btn
                color="primary"
                block
                class="mt-4"
                @click="$router.push('/documents?status=pending')"
              >
                Перейти к документам
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Быстрые действия</v-card-title>
            <v-card-text>
              <v-list density="compact">
                <v-list-item @click="$router.push('/analytics/risks')">
                  <template #prepend>
                    <v-icon>mdi-chart-line</v-icon>
                  </template>
                  <v-list-item-title>Аналитика рисков</v-list-item-title>
                  <template #append>
                    <v-icon>mdi-arrow-right</v-icon>
                  </template>
                </v-list-item>
                <v-list-item @click="$router.push('/analytics/topics')">
                  <template #prepend>
                    <v-icon>mdi-school</v-icon>
                  </template>
                  <v-list-item-title>Аналитика по темам</v-list-item-title>
                  <template #append>
                    <v-icon>mdi-arrow-right</v-icon>
                  </template>
                </v-list-item>
                <v-list-item @click="$router.push('/admin/groups')">
                  <template #prepend>
                    <v-icon>mdi-account-group</v-icon>
                  </template>
                  <v-list-item-title>Управление группами</v-list-item-title>
                  <template #append>
                    <v-icon>mdi-arrow-right</v-icon>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue'
import { panelsApi, type PrincipalPanelData } from '@/api/panels'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const loading = ref(true)
const data = ref<PrincipalPanelData>({
  total_risks: { red: 0, yellow: 0, total: 0 },
  groups_with_problems: { total_groups: 0, groups_with_problems: 0, percent: 0 },
  teacher_workload: [],
  documents: { pending: 0, overdue: 0, total_pending: 0 },
})

const risksChart = ref<HTMLCanvasElement | null>(null)
const workloadChart = ref<HTMLCanvasElement | null>(null)
let risksChartInstance: Chart | null = null
let workloadChartInstance: Chart | null = null

async function loadData() {
  loading.value = true
  try {
    const response = await panelsApi.getPrincipalPanel()
    data.value = response.data
    await nextTick()
    renderCharts()
  } catch (error) {
    console.error('Failed to load principal panel:', error)
  } finally {
    loading.value = false
  }
}

function renderCharts() {
  // График рисков
  if (risksChart.value && data.value.total_risks.total > 0) {
    if (risksChartInstance) {
      risksChartInstance.destroy()
    }
    risksChartInstance = new Chart(risksChart.value, {
      type: 'pie',
      data: {
        labels: ['Красные', 'Желтые'],
        datasets: [{
          data: [data.value.total_risks.red, data.value.total_risks.yellow],
          backgroundColor: ['rgba(244, 67, 54, 0.7)', 'rgba(255, 152, 0, 0.7)'],
          borderColor: ['rgba(244, 67, 54, 1)', 'rgba(255, 152, 0, 1)'],
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    })
  }

  // График нагрузки
  if (workloadChart.value && data.value.teacher_workload.length > 0) {
    if (workloadChartInstance) {
      workloadChartInstance.destroy()
    }
    const topTeachers = data.value.teacher_workload.slice(0, 10)
    workloadChartInstance = new Chart(workloadChart.value, {
      type: 'bar',
      data: {
        labels: topTeachers.map(t => t.teacher_fio || 'N/A'),
        datasets: [{
          label: 'Количество уроков',
          data: topTeachers.map(t => t.lessons_count),
          backgroundColor: 'rgba(25, 118, 210, 0.7)',
          borderColor: 'rgba(25, 118, 210, 1)',
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: {
          x: {
            beginAtZero: true,
          },
        },
      },
    })
  }
}

onMounted(() => {
  loadData()
})

watch(() => data.value, () => {
  nextTick(() => {
    renderCharts()
  })
}, { deep: true })
</script>


