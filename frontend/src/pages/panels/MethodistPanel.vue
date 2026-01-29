<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 mb-4">Панель методиста</h1>
      </v-col>
    </v-row>

    <v-row v-if="loading">
      <v-col cols="12" class="text-center">
        <v-progress-circular indeterminate size="64"></v-progress-circular>
      </v-col>
    </v-row>

    <template v-else>
      <!-- КТП выполнение -->
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title>Выполнение КТП</v-card-title>
            <v-card-text>
              <div v-if="data.ktp_completion.length > 0" style="height: 400px;">
                <canvas ref="ktpChart"></canvas>
              </div>
              <v-alert v-else type="info">Нет данных о КТП</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Дисциплины риска -->
      <v-row>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Дисциплины риска</v-card-title>
            <v-card-text>
              <div v-if="data.discipline_risks.length > 0" style="height: 300px;">
                <canvas ref="disciplineRisksChart"></canvas>
              </div>
              <v-alert v-else type="success">Нет проблемных дисциплин</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
              <span>Задержки проверок</span>
              <v-chip color="error">{{ data.check_delays.length }}</v-chip>
            </v-card-title>
            <v-card-text>
              <v-list v-if="data.check_delays.length > 0" density="compact">
                <v-list-item
                  v-for="assignment in data.check_delays.slice(0, 5)"
                  :key="assignment.id"
                  @click="$router.push(`/assignments/${assignment.id}`)"
                >
                  <template #prepend>
                    <v-icon color="warning">mdi-clock-alert</v-icon>
                  </template>
                  <v-list-item-title>{{ assignment.title }}</v-list-item-title>
                  <v-list-item-subtitle>
                    {{ assignment.subject }} • {{ assignment.teacher }} • Просрочено на {{ assignment.days_overdue }} дн.
                  </v-list-item-subtitle>
                  <template #append>
                    <v-btn icon variant="text" size="small">
                      <v-icon>mdi-arrow-right</v-icon>
                    </v-btn>
                  </template>
                </v-list-item>
              </v-list>
              <v-alert v-else type="success">Нет задержек проверок</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Проваленные темы -->
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
              <span>Проваленные темы</span>
              <v-chip color="error">{{ data.failed_topics.length }}</v-chip>
            </v-card-title>
            <v-card-text>
              <v-data-table
                :headers="failedTopicsHeaders"
                :items="data.failed_topics"
                density="compact"
              >
                <template #item.fail_percent="{ item }">
                  <v-chip :color="getFailPercentColor(item.fail_percent)" size="small">
                    {{ item.fail_percent }}%
                  </v-chip>
                </template>
                <template #item.actions="{ item }">
                  <v-btn
                    icon
                    variant="text"
                    size="small"
                    @click="$router.push(`/analytics/topics?subjectId=${item.subject_id || ''}`)"
                  >
                    <v-icon>mdi-arrow-right</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue'
import { panelsApi, type MethodistPanelData } from '@/api/panels'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const loading = ref(true)
const data = ref<MethodistPanelData>({
  ktp_completion: [],
  failed_topics: [],
  check_delays: [],
  discipline_risks: [],
})

const ktpChart = ref<HTMLCanvasElement | null>(null)
const disciplineRisksChart = ref<HTMLCanvasElement | null>(null)
let ktpChartInstance: Chart | null = null
let disciplineRisksChartInstance: Chart | null = null

const failedTopicsHeaders = [
  { title: 'Предмет', key: 'subject', sortable: true },
  { title: 'Тема', key: 'topic', sortable: true },
  { title: '% Неуспевающих', key: 'fail_percent', sortable: true },
  { title: 'Студентов', key: 'students_failed', sortable: true },
  { title: 'Действия', key: 'actions', sortable: false },
]

function getFailPercentColor(percent: number): string {
  if (percent >= 50) return 'error'
  if (percent >= 30) return 'warning'
  return 'orange'
}

async function loadData() {
  loading.value = true
  try {
    const response = await panelsApi.getMethodistPanel()
    data.value = response.data
    await nextTick()
    renderCharts()
  } catch (error) {
    console.error('Failed to load methodist panel:', error)
  } finally {
    loading.value = false
  }
}

function renderCharts() {
  // График выполнения КТП
  if (ktpChart.value && data.value.ktp_completion.length > 0) {
    if (ktpChartInstance) {
      ktpChartInstance.destroy()
    }
    ktpChartInstance = new Chart(ktpChart.value, {
      type: 'bar',
      data: {
        labels: data.value.ktp_completion.map(k => `${k.subject} (${k.group})`),
        datasets: [{
          label: 'Процент выполнения',
          data: data.value.ktp_completion.map(k => k.completion_percent),
          backgroundColor: 'rgba(76, 175, 80, 0.7)',
          borderColor: 'rgba(76, 175, 80, 1)',
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            ticks: {
              callback: function(value) {
                return value + '%'
              },
            },
          },
        },
      },
    })
  }

  // График дисциплин риска
  if (disciplineRisksChart.value && data.value.discipline_risks.length > 0) {
    if (disciplineRisksChartInstance) {
      disciplineRisksChartInstance.destroy()
    }
    disciplineRisksChartInstance = new Chart(disciplineRisksChart.value, {
      type: 'bar',
      data: {
        labels: data.value.discipline_risks.map(d => d.subject_name || 'N/A'),
        datasets: [{
          label: 'Средний % неуспевающих',
          data: data.value.discipline_risks.map(d => d.avg_fail_percent),
          backgroundColor: 'rgba(244, 67, 54, 0.7)',
          borderColor: 'rgba(244, 67, 54, 1)',
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
            max: 100,
            ticks: {
              callback: function(value) {
                return value + '%'
              },
            },
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


