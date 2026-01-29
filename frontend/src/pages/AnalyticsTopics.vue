<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <div class="d-flex justify-space-between align-center mb-4">
          <h1>Аналитика по темам</h1>
          <v-btn
            color="primary"
            prepend-icon="mdi-refresh"
            @click="loadTopics"
            :loading="loading"
          >
            Обновить
          </v-btn>
        </div>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-title>Фильтры</v-card-title>
          <v-card-text>
            <v-select
              v-model="filters.subjectId"
              :items="subjectOptions"
              label="Предмет"
              clearable
              @update:model-value="loadTopics"
              class="mb-4"
            ></v-select>
            <v-select
              v-model="filters.termId"
              :items="termOptions"
              label="Период"
              clearable
              @update:model-value="loadTopics"
              class="mb-4"
            ></v-select>
            <v-text-field
              v-model.number="filters.failPercent"
              label="Мин. % неуспевающих"
              type="number"
              min="0"
              max="100"
              clearable
              @update:model-value="loadTopics"
            ></v-text-field>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="9">
        <v-card>
          <v-card-title>Статистика по темам</v-card-title>
          <v-card-text>
            <div v-if="loading" class="text-center py-8">
              <v-progress-circular indeterminate size="64"></v-progress-circular>
            </div>

            <v-data-table
              v-else
              :headers="headers"
              :items="topics"
              :items-per-page="itemsPerPage"
              :server-items-length="totalItems"
              @update:page="handlePageChange"
              @update:items-per-page="handleItemsPerPageChange"
              item-value="id"
            >
              <template #item.subject="{ item }">
                {{ item.subject?.name || 'N/A' }}
              </template>
              <template #item.topic="{ item }">
                {{ item.topic?.title || 'N/A' }}
              </template>
              <template #item.fail_percent="{ item }">
                <v-chip
                  :color="getFailPercentColor(item.fail_percent)"
                  size="small"
                >
                  {{ item.fail_percent }}%
                </v-chip>
              </template>
              <template #item.students="{ item }">
                {{ item.students_total }} (неуспевающих: {{ item.students_failed }})
              </template>
              <template #item.actions="{ item }">
                <v-btn
                  icon="mdi-chevron-down"
                  size="small"
                  variant="text"
                  @click="toggleStudents(item)"
                ></v-btn>
              </template>
            </v-data-table>

            <!-- Drill-down: список студентов -->
            <v-expand-transition>
              <div v-if="expandedTopic">
                <v-divider class="my-4"></v-divider>
                <h3 class="mb-4">Студенты по теме: {{ expandedTopic.topic?.title }}</h3>
                <div v-if="loadingStudents" class="text-center py-4">
                  <v-progress-circular indeterminate></v-progress-circular>
                </div>
                <v-data-table
                  v-else-if="topicStudents.length > 0"
                  :headers="studentHeaders"
                  :items="topicStudents"
                  density="compact"
                >
                  <template #item.avg_grade="{ item }">
                    <v-chip
                      :color="getGradeColor(item.avg_grade)"
                      size="small"
                    >
                      {{ item.avg_grade.toFixed(2) }}
                    </v-chip>
                  </template>
                  <template #item.status="{ item }">
                    <v-chip
                      :color="item.avg_grade <= 2 ? 'error' : 'success'"
                      size="small"
                    >
                      {{ item.avg_grade <= 2 ? 'Неуспевающий' : 'Успевающий' }}
                    </v-chip>
                  </template>
                </v-data-table>
                <v-alert v-else type="info">Нет данных о студентах</v-alert>
              </div>
            </v-expand-transition>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { analyticsApi } from '@/api/analytics'
import api from '@/api/client'

interface TopicPerformanceStat {
  id: number
  subject_id: number
  ktp_topic_id: number
  term_id: number | null
  students_total: number
  students_failed: number
  fail_percent: number
  calculated_at: string
  subject?: {
    id: number
    name: string
  }
  topic?: {
    id: number
    title: string
  }
}

interface TopicStudent {
  id: number
  fio: string
  avg_grade: number
  grades_count: number
}

const loading = ref(false)
const loadingStudents = ref(false)
const topics = ref<TopicPerformanceStat[]>([])
const topicStudents = ref<TopicStudent[]>([])
const expandedTopic = ref<TopicPerformanceStat | null>(null)
const totalItems = ref(0)
const itemsPerPage = ref(50)
const currentPage = ref(1)

const filters = ref({
  subjectId: null as number | null,
  termId: null as number | null,
  failPercent: null as number | null,
})

const subjectOptions = ref<Array<{ title: string; value: number }>>([])
const termOptions = ref<Array<{ title: string; value: number }>>([])

const headers = [
  { title: 'Предмет', key: 'subject', sortable: false },
  { title: 'Тема', key: 'topic', sortable: false },
  { title: '% Неуспевающих', key: 'fail_percent', sortable: true },
  { title: 'Студенты', key: 'students', sortable: false },
  { title: 'Дата расчета', key: 'calculated_at', sortable: false },
  { title: 'Действия', key: 'actions', sortable: false, width: '100px' },
]

const studentHeaders = [
  { title: 'ФИО', key: 'fio', sortable: true },
  { title: 'Средний балл', key: 'avg_grade', sortable: true },
  { title: 'Кол-во оценок', key: 'grades_count', sortable: true },
  { title: 'Статус', key: 'status', sortable: false },
]

onMounted(async () => {
  await Promise.all([loadSubjects(), loadTerms(), loadTopics()])
})

async function loadSubjects() {
  try {
    const response = await api.get('/v1/directory/subjects')
    subjectOptions.value = response.data.data.map((s: any) => ({
      title: s.name,
      value: s.id,
    }))
  } catch (error) {
    console.error('Failed to load subjects:', error)
  }
}

async function loadTerms() {
  try {
    const response = await api.get('/v1/directory/terms')
    termOptions.value = (response.data.data || []).map((t: any) => ({
      title: t.name || `Терм ${t.id}`,
      value: t.id,
    }))
  } catch (error) {
    console.error('Failed to load terms:', error)
  }
}

async function loadTopics(page = 1) {
  loading.value = true
  currentPage.value = page
  try {
    const params: any = {
      page,
      per_page: itemsPerPage.value,
    }
    if (filters.value.subjectId) params.subjectId = filters.value.subjectId
    if (filters.value.termId) params.termId = filters.value.termId
    if (filters.value.failPercent !== null) params.fail_percent = filters.value.failPercent

    const response = await analyticsApi.getTopics(params)
    topics.value = response.data || []
    totalItems.value = response.total || 0
  } catch (error) {
    console.error('Failed to load topics:', error)
  } finally {
    loading.value = false
  }
}

async function toggleStudents(topic: TopicPerformanceStat) {
  if (expandedTopic.value?.id === topic.id) {
    expandedTopic.value = null
    topicStudents.value = []
    return
  }

  expandedTopic.value = topic
  await loadTopicStudents(topic)
}

async function loadTopicStudents(topic: TopicPerformanceStat) {
  loadingStudents.value = true
  try {
    const response = await api.get(`/v1/analytics/topics/${topic.id}/students`)
    topicStudents.value = response.data.data || []
  } catch (error) {
    console.error('Failed to load topic students:', error)
    topicStudents.value = []
  } finally {
    loadingStudents.value = false
  }
}

function getFailPercentColor(failPercent: number): string {
  if (failPercent >= 50) return 'error'
  if (failPercent >= 30) return 'warning'
  if (failPercent >= 20) return 'orange'
  return 'success'
}

function getGradeColor(grade: number): string {
  if (grade >= 4.5) return 'success'
  if (grade >= 3.5) return 'info'
  if (grade >= 2.5) return 'warning'
  return 'error'
}

function handlePageChange(page: number) {
  loadTopics(page)
}

function handleItemsPerPageChange(perPage: number) {
  itemsPerPage.value = perPage
  loadTopics(1)
}
</script>

