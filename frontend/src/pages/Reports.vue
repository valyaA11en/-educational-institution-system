<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 mb-4">Отчёты</h1>
      </v-col>
    </v-row>

    <v-tabs v-model="activeTab" grow class="mb-4">
      <v-tab value="journal">Журнал</v-tab>
      <v-tab value="schedule">Расписание</v-tab>
      <v-tab value="grade-sheet">Ведомость</v-tab>
      <v-tab value="order">Приказы</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- Журнал -->
      <v-window-item value="journal">
        <v-card>
          <v-card-title>Отчёт по журналу</v-card-title>
          <v-card-text>
            <v-row dense>
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="filters.journal.group_id"
                  :items="groups"
                  item-title="name"
                  item-value="id"
                  label="Группа"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="filters.journal.subject_id"
                  :items="subjects"
                  item-title="name"
                  item-value="id"
                  label="Предмет"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="filters.journal.date_from"
                  label="С"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="filters.journal.date_to"
                  label="По"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2" class="d-flex align-center">
                <v-btn color="primary" :loading="loading.journal" @click="fetchReport('journal')">
                  Получить отчёт
                </v-btn>
              </v-col>
            </v-row>
            <v-alert v-if="result.journal" type="info" class="mt-4" density="compact">
              <strong>Занятий:</strong> {{ result.journal.summary.lessons_count }} ·
              <strong>Оценок:</strong> {{ result.journal.summary.grades_count }} ·
              <strong>Посещений:</strong> {{ result.journal.summary.attendance_count }}
            </v-alert>
            <div class="mt-2">
              <v-btn
                v-if="result.journal && filters.journal.group_id"
                color="secondary"
                size="small"
                prepend-icon="mdi-printer"
                :loading="printing.journal"
                @click="printJournal"
              >
                Печать журнала
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-window-item>

      <!-- Расписание -->
      <v-window-item value="schedule">
        <v-card>
          <v-card-title>Отчёт по расписанию</v-card-title>
          <v-card-text>
            <v-row dense>
              <v-col cols="12" sm="6" md="2">
                <v-select
                  v-model="filters.schedule.version_id"
                  :items="versions"
                  item-title="id"
                  item-value="id"
                  label="Версия"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                >
                  <template #item="{ props, item }">
                    <v-list-item v-bind="props" :title="`Версия ${item.raw.id} (${item.raw.status})`" />
                  </template>
                  <template #selection="{ item }">
                    {{ item.raw ? `Версия ${item.raw.id}` : '' }}
                  </template>
                </v-select>
              </v-col>
              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="filters.schedule.date_from"
                  label="С"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="filters.schedule.date_to"
                  label="По"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2">
                <v-select
                  v-model="filters.schedule.group_id"
                  :items="groups"
                  item-title="name"
                  item-value="id"
                  label="Группа"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2" class="d-flex align-center">
                <v-btn color="primary" :loading="loading.schedule" @click="fetchReport('schedule')">
                  Получить отчёт
                </v-btn>
              </v-col>
            </v-row>
            <v-alert v-if="result.schedule" type="info" class="mt-4" density="compact">
              <strong>Всего:</strong> {{ result.schedule.summary.total }} ·
              <strong>Показано:</strong> {{ result.schedule.summary.returned }}
            </v-alert>
            <div class="mt-2">
              <v-btn
                v-if="result.schedule && filters.schedule.group_id"
                color="secondary"
                size="small"
                prepend-icon="mdi-printer"
                :loading="printing.schedule"
                @click="printSchedule"
              >
                Печать расписания (группа)
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-window-item>

      <!-- Ведомость -->
      <v-window-item value="grade-sheet">
        <v-card>
          <v-card-title>Ведомость оценок</v-card-title>
          <v-card-text>
            <v-row dense>
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="filters.gradeSheet.group_id"
                  :items="groups"
                  item-title="name"
                  item-value="id"
                  label="Группа"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="filters.gradeSheet.subject_id"
                  :items="subjects"
                  item-title="name"
                  item-value="id"
                  label="Предмет"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2" class="d-flex align-center">
                <v-btn color="primary" :loading="loading.gradeSheet" @click="fetchReport('gradeSheet')">
                  Получить отчёт
                </v-btn>
              </v-col>
            </v-row>
            <v-alert v-if="result.gradeSheet" type="info" class="mt-4" density="compact">
              <strong>Оценок:</strong> {{ result.gradeSheet.grades.length }}
            </v-alert>
            <v-table v-if="result.gradeSheet && result.gradeSheet.grades.length" density="compact" class="mt-4">
              <thead>
                <tr>
                  <th>Студент</th>
                  <th>Предмет</th>
                  <th>Оценка</th>
                  <th>Вес</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="g in result.gradeSheet.grades.slice(0, 100)" :key="g.id">
                  <td>{{ g.student_fio }}</td>
                  <td>{{ g.subject_name ?? '—' }}</td>
                  <td>{{ g.value ?? '—' }}</td>
                  <td>{{ g.weight ?? '—' }}</td>
                </tr>
              </tbody>
            </v-table>
            <p v-if="result.gradeSheet && result.gradeSheet.grades.length > 100" class="text-caption mt-2">
              Показаны первые 100 из {{ result.gradeSheet.grades.length }}.
            </p>
          </v-card-text>
        </v-card>
      </v-window-item>

      <!-- Приказы -->
      <v-window-item value="order">
        <v-card>
          <v-card-title>Приказы</v-card-title>
          <v-card-text>
            <v-row dense>
              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="filters.order.date_from"
                  label="С"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="filters.order.date_to"
                  label="По"
                  type="date"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2">
                <v-select
                  v-model="filters.order.status"
                  :items="orderStatusItems"
                  item-title="title"
                  item-value="value"
                  label="Статус"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" md="2" class="d-flex align-center">
                <v-btn color="primary" :loading="loading.order" @click="fetchReport('order')">
                  Получить отчёт
                </v-btn>
              </v-col>
            </v-row>
            <v-alert v-if="result.order" type="info" class="mt-4" density="compact">
              <strong>Всего:</strong> {{ result.order.summary.total }} ·
              <strong>Показано:</strong> {{ result.order.summary.returned }}
            </v-alert>
            <v-table v-if="result.order && result.order.orders.length" density="compact" class="mt-4">
              <thead>
                <tr>
                  <th>№</th>
                  <th>Дата</th>
                  <th>Статус</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(o, idx) in result.order.orders.slice(0, 100)" :key="idx">
                  <td>{{ orderNumber(o) }}</td>
                  <td>{{ formatDate(orderDate(o)) }}</td>
                  <td>
                    <v-chip size="small">{{ orderStatus(o) }}</v-chip>
                  </td>
                </tr>
              </tbody>
            </v-table>
            <p v-if="result.order && result.order.orders.length > 100" class="text-caption mt-2">
              Показаны первые 100 из {{ result.order.orders.length }}.
            </p>
          </v-card-text>
        </v-card>
      </v-window-item>
    </v-window>
  </v-container>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { reportsApi } from '../api/reports'
import { printApi } from '../api/print'
import { referencesApi } from '../api/references'
import { scheduleApi } from '../api/schedule'
import type { ReportJournalData, ReportScheduleData, ReportGradeSheetData, ReportOrderData } from '../api/reports'

const activeTab = ref('journal')
const groups = ref<Array<{ id: number; name: string }>>([])
const subjects = ref<Array<{ id: number; name: string }>>([])
const versions = ref<Array<{ id: number; status: string }>>([])

const filters = reactive({
  journal: { group_id: undefined as number | undefined, subject_id: undefined as number | undefined, date_from: '', date_to: '' },
  schedule: { version_id: undefined as number | undefined, date_from: '', date_to: '', group_id: undefined as number | undefined },
  gradeSheet: { group_id: undefined as number | undefined, subject_id: undefined as number | undefined },
  order: { date_from: '', date_to: '', status: undefined as string | undefined },
})

const loading = reactive({ journal: false, schedule: false, gradeSheet: false, order: false })
const printing = reactive({ journal: false, schedule: false })
const result = reactive<{
  journal: ReportJournalData | null
  schedule: ReportScheduleData | null
  gradeSheet: ReportGradeSheetData | null
  order: ReportOrderData | null
}>({
  journal: null,
  schedule: null,
  gradeSheet: null,
  order: null,
})

const orderStatusItems = [
  { title: 'Черновик', value: 'draft' },
  { title: 'На согласовании', value: 'on_review' },
  { title: 'Утверждён', value: 'approved' },
  { title: 'Подписан', value: 'signed' },
  { title: 'В архиве', value: 'archived' },
]

function formatDate(s: string | undefined) {
  if (!s) return '—'
  return new Date(s).toLocaleDateString('ru-RU')
}

function orderDate(o: unknown): string | undefined {
  if (!o || typeof o !== 'object' || !('date' in o)) return undefined
  const d = (o as Record<string, unknown>).date
  return typeof d === 'string' ? d : undefined
}

function orderNumber(o: unknown): string {
  if (!o || typeof o !== 'object' || !('number' in o)) return '—'
  const n = (o as Record<string, unknown>).number
  return typeof n === 'string' ? n : '—'
}

function orderStatus(o: unknown): string {
  if (!o || typeof o !== 'object' || !('status' in o)) return '—'
  const s = (o as Record<string, unknown>).status
  return typeof s === 'string' ? s : '—'
}

async function loadRefs() {
  try {
    const [g, s, v] = await Promise.all([
      referencesApi.listGroups({ per_page: 1000 }),
      referencesApi.listSubjects({ per_page: 1000 }),
      scheduleApi.getVersions({ per_page: 100 }).then((r) => r?.data ?? []),
    ])
    groups.value = Array.isArray(g) ? g : []
    subjects.value = Array.isArray(s) ? s : []
    versions.value = Array.isArray(v) ? v : []
  } catch (e) {
    console.error('Failed to load refs', e)
  }
}

async function fetchReport(k: 'journal' | 'schedule' | 'gradeSheet' | 'order') {
  loading[k] = true
  result[k] = null
  try {
    if (k === 'journal') {
      result.journal = await reportsApi.journal({
        group_id: filters.journal.group_id,
        subject_id: filters.journal.subject_id || undefined,
        date_from: filters.journal.date_from || undefined,
        date_to: filters.journal.date_to || undefined,
      })
    } else if (k === 'schedule') {
      result.schedule = await reportsApi.schedule({
        version_id: filters.schedule.version_id,
        date_from: filters.schedule.date_from || undefined,
        date_to: filters.schedule.date_to || undefined,
        group_id: filters.schedule.group_id,
      })
    } else if (k === 'gradeSheet') {
      result.gradeSheet = await reportsApi.gradeSheet({
        group_id: filters.gradeSheet.group_id,
        subject_id: filters.gradeSheet.subject_id,
      })
    } else {
      result.order = await reportsApi.order({
        date_from: filters.order.date_from || undefined,
        date_to: filters.order.date_to || undefined,
        status: (filters.order.status as 'draft' | 'on_review' | 'approved' | 'signed' | 'archived') || undefined,
      })
    }
  } catch (e) {
    console.error('Report fetch failed', e)
  } finally {
    loading[k] = false
  }
}

async function printJournal() {
  const gid = filters.journal.group_id
  if (!gid) return
  printing.journal = true
  try {
    const blob = await printApi.journal({ groupId: gid, subjectId: filters.journal.subject_id })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'journal.pdf'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Print journal failed', e)
  } finally {
    printing.journal = false
  }
}

async function printSchedule() {
  const gid = filters.schedule.group_id
  if (!gid) return
  printing.schedule = true
  try {
    const blob = await printApi.schedule({ view: 'group', id: gid })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'schedule.pdf'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Print schedule failed', e)
  } finally {
    printing.schedule = false
  }
}

onMounted(loadRefs)
</script>
