<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 mb-4">Панель куратора</h1>
      </v-col>
    </v-row>

    <v-row v-if="loading">
      <v-col cols="12" class="text-center">
        <v-progress-circular indeterminate size="64"></v-progress-circular>
      </v-col>
    </v-row>

    <template v-else>
      <!-- Статистика групп -->
      <v-row>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Средний балл по группам</v-card-title>
            <v-card-text>
              <div v-if="data.group_averages.length > 0" style="height: 300px;">
                <canvas ref="groupAveragesChart"></canvas>
              </div>
              <v-alert v-else type="info">Нет данных о средних баллах</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title>Риски по уровням</v-card-title>
            <v-card-text>
              <div v-if="totalRisks > 0" style="height: 300px;">
                <canvas ref="risksChart"></canvas>
              </div>
              <v-alert v-else type="info">Нет активных рисков</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Долги -->
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
              <span>Просроченные задания</span>
              <v-chip color="error">{{ totalOverdueAssignments }}</v-chip>
            </v-card-title>
            <v-card-text>
              <v-list v-if="data.overdue_assignments.length > 0">
                <template v-for="groupAssignments in data.overdue_assignments" :key="groupAssignments.group_id">
                  <v-list-subheader>Группа: {{ getGroupName(groupAssignments.group_id) }}</v-list-subheader>
                  <v-list-item
                    v-for="assignment in groupAssignments.assignments"
                    :key="assignment.id"
                    @click="$router.push(`/assignments/${assignment.id}`)"
                  >
                    <template #prepend>
                      <v-icon color="error">mdi-file-document-alert</v-icon>
                    </template>
                    <v-list-item-title>{{ assignment.title }}</v-list-item-title>
                    <v-list-item-subtitle>
                      {{ assignment.subject }} • Просрочено: {{ formatDate(assignment.due_at) }}
                    </v-list-item-subtitle>
                    <template #append>
                      <v-btn icon variant="text">
                        <v-icon>mdi-arrow-right</v-icon>
                      </v-btn>
                    </template>
                  </v-list-item>
                </template>
              </v-list>
              <v-alert v-else type="success">Нет просроченных заданий</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Риски -->
      <v-row>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
              <span>Красные риски</span>
              <v-chip color="error">{{ data.risks.red.length }}</v-chip>
            </v-card-title>
            <v-card-text>
              <v-list v-if="data.risks.red.length > 0" density="compact">
                <v-list-item
                  v-for="risk in data.risks.red"
                  :key="risk.id"
                  @click="$router.push(`/students/${risk.student_id}/timeline`)"
                >
                  <template #prepend>
                    <v-icon color="error">mdi-alert-circle</v-icon>
                  </template>
                  <v-list-item-title>{{ risk.student_fio }}</v-list-item-title>
                  <v-list-item-subtitle>
                    {{ risk.risk_type }} • Балл: {{ risk.score }}
                  </v-list-item-subtitle>
                  <template #append>
                    <v-btn icon variant="text" size="small">
                      <v-icon>mdi-arrow-right</v-icon>
                    </v-btn>
                  </template>
                </v-list-item>
              </v-list>
              <v-alert v-else type="success">Нет красных рисков</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
              <span>Желтые риски</span>
              <v-chip color="warning">{{ data.risks.yellow.length }}</v-chip>
            </v-card-title>
            <v-card-text>
              <v-list v-if="data.risks.yellow.length > 0" density="compact">
                <v-list-item
                  v-for="risk in data.risks.yellow"
                  :key="risk.id"
                  @click="$router.push(`/students/${risk.student_id}/timeline`)"
                >
                  <template #prepend>
                    <v-icon color="warning">mdi-alert-circle</v-icon>
                  </template>
                  <v-list-item-title>{{ risk.student_fio }}</v-list-item-title>
                  <v-list-item-subtitle>
                    {{ risk.risk_type }} • Балл: {{ risk.score }}
                  </v-list-item-subtitle>
                  <template #append>
                    <v-btn icon variant="text" size="small">
                      <v-icon>mdi-arrow-right</v-icon>
                    </v-btn>
                  </template>
                </v-list-item>
              </v-list>
              <v-alert v-else type="info">Нет желтых рисков</v-alert>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import { panelsApi, type CuratorPanelData } from '@/api/panels'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

const loading = ref(true)
const data = ref<CuratorPanelData>({
  groups: [],
  attendance: [],
  overdue_assignments: [],
  risks: { red: [], yellow: [] },
  group_averages: [],
})

const groupAveragesChart = ref<HTMLCanvasElement | null>(null)
const risksChart = ref<HTMLCanvasElement | null>(null)
let groupAveragesChartInstance: Chart | null = null
let risksChartInstance: Chart | null = null

const totalOverdueAssignments = computed(() => {
  return data.value.overdue_assignments.reduce((sum, group) => sum + group.count, 0)
})

const totalRisks = computed(() => {
  return data.value.risks.red.length + data.value.risks.yellow.length
})

function getGroupName(groupId: number): string {
  const group = data.value.groups.find(g => g.id === groupId)
  return group?.name || `Группа #${groupId}`
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('ru-RU')
}

async function loadData() {
  loading.value = true
  try {
    const response = await panelsApi.getCuratorPanel()
    data.value = response.data
    await nextTick()
    renderCharts()
  } catch (error) {
    console.error('Failed to load curator panel:', error)
  } finally {
    loading.value = false
  }
}

function renderCharts() {
  // График средних баллов
  if (groupAveragesChart.value && data.value.group_averages.length > 0) {
    if (groupAveragesChartInstance) {
      groupAveragesChartInstance.destroy()
    }
    groupAveragesChartInstance = new Chart(groupAveragesChart.value, {
      type: 'bar',
      data: {
        labels: data.value.group_averages.map(g => g.group_name),
        datasets: [{
          label: 'Средний балл',
          data: data.value.group_averages.map(g => g.average_grade || 0),
          backgroundColor: 'rgba(25, 118, 210, 0.7)',
          borderColor: 'rgba(25, 118, 210, 1)',
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            max: 5,
          },
        },
      },
    })
  }

  // График рисков
  if (risksChart.value && totalRisks.value > 0) {
    if (risksChartInstance) {
      risksChartInstance.destroy()
    }
    risksChartInstance = new Chart(risksChart.value, {
      type: 'pie',
      data: {
        labels: ['Красные', 'Желтые'],
        datasets: [{
          data: [data.value.risks.red.length, data.value.risks.yellow.length],
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


