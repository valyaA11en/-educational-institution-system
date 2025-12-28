<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <div class="d-flex justify-space-between align-center mb-4">
          <div>
            <h1>История обучения</h1>
            <p v-if="student" class="text-subtitle-1">{{ student.fio }}</p>
          </div>
          <v-btn
            color="primary"
            :loading="exporting"
            @click="exportPDF"
            prepend-icon="mdi-file-pdf-box"
          >
            Экспорт PDF
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
              v-model="filters.event_type"
              :items="eventTypes"
              label="Тип события"
              multiple
              clearable
              chips
            ></v-select>
            <v-text-field
              v-model="filters.dateFrom"
              label="С"
              type="date"
              class="mt-4"
            ></v-text-field>
            <v-text-field
              v-model="filters.dateTo"
              label="По"
              type="date"
              class="mt-4"
            ></v-text-field>
            <v-btn
              color="primary"
              block
              class="mt-4"
              @click="loadTimeline"
            >
              Применить
            </v-btn>
            <v-btn
              variant="outlined"
              block
              class="mt-2"
              @click="resetFilters"
            >
              Сбросить
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="9">
        <v-card v-if="loading">
          <v-card-text>
            <v-progress-circular indeterminate></v-progress-circular>
          </v-card-text>
        </v-card>

        <v-card v-else-if="timeline.length === 0">
          <v-card-text>
            <v-alert type="info">Нет событий за выбранный период</v-alert>
          </v-card-text>
        </v-card>

        <v-timeline v-else side="end" align="start" class="mt-0">
          <v-timeline-item
            v-for="event in timeline"
            :key="event.id"
            :dot-color="getEventColor(event.event_type)"
            size="small"
            :icon="getEventIcon(event.event_type)"
          >
            <template #opposite>
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(event.event_date) }}
              </div>
            </template>
            <v-card>
              <v-card-title class="text-h6">
                {{ event.title }}
              </v-card-title>
              <v-card-subtitle v-if="event.description">
                {{ event.description }}
              </v-card-subtitle>
              <v-card-text>
                <v-chip
                  size="small"
                  :color="getEventColor(event.event_type)"
                  class="mr-2"
                >
                  {{ getEventTypeLabel(event.event_type) }}
                </v-chip>
                <span class="text-caption text-medium-emphasis">
                  {{ formatDateTime(event.created_at) }}
                </span>
              </v-card-text>
            </v-card>
          </v-timeline-item>
        </v-timeline>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { timelineApi, type TimelineEvent } from '@/api/timeline'
import { studentExportApi } from '@/api/studentExport'
import { usersApi } from '@/api/users'

const route = useRoute()
const studentId = computed(() => parseInt(route.params.id as string))

const timeline = ref<TimelineEvent[]>([])
const student = ref<any>(null)
const loading = ref(false)
const exporting = ref(false)

const filters = ref({
  event_type: [] as string[],
  dateFrom: '',
  dateTo: '',
})

const eventTypes = [
  { title: 'Зачисление', value: 'enrollment.created' },
  { title: 'Посещаемость', value: 'attendance.marked' },
  { title: 'Оценка', value: 'grade.created' },
  { title: 'Изменение оценки', value: 'grade.updated' },
  { title: 'Задание сдано', value: 'assignment.submitted' },
  { title: 'Задание с опозданием', value: 'assignment.late' },
  { title: 'Риск', value: 'risk.updated' },
  { title: 'Документ', value: 'document.created' },
  { title: 'Результат конкурса', value: 'contest.result' },
  { title: 'Результат экзамена', value: 'exam.result' },
]

onMounted(async () => {
  await Promise.all([loadStudent(), loadTimeline()])
})

async function loadStudent() {
  try {
    const response = await usersApi.get(studentId.value)
    student.value = response
  } catch (error) {
    console.error('Failed to load student:', error)
  }
}

async function loadTimeline() {
  loading.value = true
  try {
    const params: any = {}
    if (filters.value.dateFrom) params.dateFrom = filters.value.dateFrom
    if (filters.value.dateTo) params.dateTo = filters.value.dateTo
    if (filters.value.event_type.length > 0) {
      params.event_type = filters.value.event_type
    }

    const response = await timelineApi.getTimeline(studentId.value, params)
    timeline.value = response.data.data
  } catch (error) {
    console.error('Failed to load timeline:', error)
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = {
    event_type: [],
    dateFrom: '',
    dateTo: '',
  }
  loadTimeline()
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function formatDateTime(dateTime: string) {
  return new Date(dateTime).toLocaleString('ru-RU')
}

function getEventColor(eventType: string): string {
  const colors: Record<string, string> = {
    'enrollment.created': 'blue',
    'attendance.marked': 'orange',
    'grade.created': 'green',
    'grade.updated': 'teal',
    'assignment.submitted': 'purple',
    'assignment.late': 'red',
    'risk.updated': 'red',
    'document.created': 'indigo',
    'contest.result': 'amber',
    'exam.result': 'cyan',
  }
  return colors[eventType] || 'grey'
}

function getEventIcon(eventType: string): string {
  const icons: Record<string, string> = {
    'enrollment.created': 'mdi-account-plus',
    'attendance.marked': 'mdi-calendar-check',
    'grade.created': 'mdi-star',
    'grade.updated': 'mdi-star-edit',
    'assignment.submitted': 'mdi-file-check',
    'assignment.late': 'mdi-clock-alert',
    'risk.updated': 'mdi-alert',
    'document.created': 'mdi-file-document',
    'contest.result': 'mdi-trophy',
    'exam.result': 'mdi-school',
  }
  return icons[eventType] || 'mdi-circle'
}

function getEventTypeLabel(eventType: string): string {
  const item = eventTypes.find((e) => e.value === eventType)
  return item?.title || eventType
}

async function exportPDF() {
  exporting.value = true
  try {
    await studentExportApi.exportTimeline(studentId.value, {
      dateFrom: filters.value.dateFrom,
      dateTo: filters.value.dateTo,
      event_type: filters.value.event_type,
    })
  } catch (error) {
    console.error('Failed to export PDF:', error)
  } finally {
    exporting.value = false
  }
}
</script>

