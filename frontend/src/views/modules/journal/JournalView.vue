<template>
  <v-card>
    <v-card-title class="d-flex flex-wrap align-center gap-2">
      <span>Журнал</span>
      <v-spacer />
      <v-btn
        color="success"
        prepend-icon="mdi-file-download"
        :loading="exporting"
        :disabled="readOnly.isEnabled"
        @click="exportGradeChanges"
      >
        Экспорт изменений оценок
      </v-btn>
      <v-btn
        color="primary"
        prepend-icon="mdi-printer"
        :loading="printing"
        @click="printJournal"
      >
        Печать журнала
      </v-btn>
    </v-card-title>

    <v-card-text>
      <v-form class="mb-4">
        <v-row dense>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.group_id"
              :items="groupOptions"
              item-title="name"
              item-value="id"
              label="Группа"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              @update:model-value="onGroupChange"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.subject_id"
              :items="subjectOptions"
              item-title="name"
              item-value="id"
              label="Предмет"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.subgroup_id"
              :items="subgroupOptions"
              item-title="name"
              item-value="id"
              label="Подгруппа"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field
              v-model="filters.date_from"
              label="Дата с"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field
              v-model="filters.date_to"
              label="Дата по"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2" class="d-flex align-center">
            <v-btn
              color="primary"
              prepend-icon="mdi-refresh"
              :loading="loading"
              :disabled="!canLoad"
              @click="loadGrid"
            >
              Загрузить
            </v-btn>
          </v-col>
        </v-row>
      </v-form>

      <div v-if="loading" class="text-center py-8">
        <v-progress-circular indeterminate color="primary" />
      </div>
      <div v-else-if="!grid" class="text-center py-8 text-medium-emphasis">
        <p>Выберите группу, предмет и период и нажмите «Загрузить».</p>
      </div>
      <div v-else-if="grid.students.length === 0" class="text-center py-8 text-medium-emphasis">
        <p>Нет данных за выбранный период.</p>
      </div>
      <div v-else class="journal-grid-wrapper">
        <v-table density="compact" class="journal-table">
          <thead>
            <tr>
              <th class="text-left journal-student-col">Студент</th>
              <th
                v-for="les in grid.lessons"
                :key="les.id"
                class="text-center journal-lesson-col"
              >
                <div class="text-caption font-weight-bold">{{ formatDate(les.date) }}</div>
                <div class="text-caption text-medium-emphasis text-truncate" :title="les.topic || ''">
                  {{ les.topic || '—' }}
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="st in grid.students" :key="st.id">
              <td class="journal-student-cell font-weight-medium">{{ st.fio }}</td>
              <td
                v-for="cell in st.lessons"
                :key="`${st.id}-${cell.lesson_id}`"
                class="journal-cell text-center"
              >
                <div v-if="cell.grades?.length" class="d-flex flex-wrap justify-center gap-1">
                  <v-chip
                    v-for="g in cell.grades"
                    :key="g.id"
                    size="x-small"
                    variant="flat"
                    color="primary"
                  >
                    {{ g.value }}
                  </v-chip>
                </div>
                <div v-else class="text-caption text-medium-emphasis">—</div>
                <div
                  v-if="cell.attendance"
                  class="text-caption mt-1"
                  :class="attendanceClass(cell.attendance.status)"
                >
                  {{ attendanceLabel(cell.attendance.status) }}
                </div>
              </td>
            </tr>
          </tbody>
        </v-table>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { journalApi } from '../../../api/journal'
import { printApi } from '../../../api/print'
import { directoryApi, type GroupDTO, type SubjectDTO, type SubgroupDTO } from '../../../api/directory'
import { useReadOnlyStore } from '../../../stores/readOnly'
import { useToast } from '../../../composables/useToast'
import type { JournalGridResponse, JournalGridStudent, JournalGridLesson } from '../../../api/journal'

const { showToast } = useToast()
const readOnly = useReadOnlyStore()

const loading = ref(false)
const exporting = ref(false)
const printing = ref(false)
const grid = ref<{ students: JournalGridStudent[]; lessons: JournalGridLesson[] } | null>(null)

const groupOptions = ref<GroupDTO[]>([])
const subjectOptions = ref<SubjectDTO[]>([])
const subgroupOptions = ref<SubgroupDTO[]>([])

const filters = ref({
  group_id: null as number | null,
  subject_id: null as number | null,
  subgroup_id: null as number | null,
  date_from: '',
  date_to: '',
})

const canLoad = computed(() =>
  Boolean(
    filters.value.group_id &&
    filters.value.subject_id &&
    filters.value.date_from &&
    filters.value.date_to
  )
)

function formatDate (d: string): string {
  if (!d) return '—'
  const dt = new Date(d + 'T12:00:00')
  return dt.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' })
}

function attendanceLabel (s: string): string {
  const map: Record<string, string> = {
    present: '✓',
    absent: 'Н',
    late: 'О',
  }
  return map[s] ?? s
}

function attendanceClass (s: string): string {
  if (s === 'present') return 'text-success'
  if (s === 'absent') return 'text-error'
  if (s === 'late') return 'text-warning'
  return ''
}

async function loadDirectory () {
  try {
    const [groups, subjRes] = await Promise.all([
      directoryApi.getGroups(),
      directoryApi.listSubjects({ per_page: 1000 }),
    ])
    groupOptions.value = Array.isArray(groups) ? groups : (groups as { data?: GroupDTO[] }).data ?? []
    subjectOptions.value = subjRes?.data ?? []
  } catch (e) {
    console.error('Failed to load directory:', e)
    showToast('Ошибка загрузки справочников', 'error')
  }
}

async function onGroupChange () {
  filters.value.subgroup_id = null
  subgroupOptions.value = []
  if (!filters.value.group_id) return
  try {
    const res = await directoryApi.listSubgroups({ group_id: filters.value.group_id, per_page: 100 })
    subgroupOptions.value = res?.data ?? []
  } catch (e) {
    console.error('Failed to load subgroups:', e)
  }
}

async function loadGrid () {
  if (!canLoad.value) return
  loading.value = true
  grid.value = null
  try {
    const res: JournalGridResponse = await journalApi.getGrid({
      group_id: filters.value.group_id!,
      subject_id: filters.value.subject_id!,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      subgroup_id: filters.value.subgroup_id ?? undefined,
    })
    if (res?.success && res?.data) {
      grid.value = { students: res.data.students, lessons: res.data.lessons }
    } else {
      showToast('Не удалось загрузить журнал', 'error')
    }
  } catch (e: unknown) {
    console.error('Failed to load journal grid:', e)
    showToast((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Ошибка загрузки журнала', 'error')
  } finally {
    loading.value = false
  }
}

async function exportGradeChanges () {
  exporting.value = true
  try {
    await journalApi.exportGradeChanges({
      subject_id: filters.value.subject_id ?? undefined,
      date_from: filters.value.date_from || undefined,
      date_to: filters.value.date_to || undefined,
    })
    showToast('Экспорт завершён', 'success')
  } catch (e) {
    console.error('Export failed:', e)
    showToast('Ошибка экспорта', 'error')
  } finally {
    exporting.value = false
  }
}

async function printJournal () {
  if (!filters.value.group_id) {
    showToast('Выберите группу', 'warning')
    return
  }
  printing.value = true
  try {
    const blob = await printApi.journal({
      groupId: filters.value.group_id,
      subjectId: filters.value.subject_id ?? undefined,
      format: 'pdf',
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `journal_group_${filters.value.group_id}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
    showToast('PDF сгенерирован', 'success')
  } catch (e: unknown) {
    console.error('Print failed:', e)
    showToast((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Ошибка при генерации PDF', 'error')
  } finally {
    printing.value = false
  }
}

onMounted(() => {
  loadDirectory()
  const today = new Date()
  const weekAgo = new Date(today)
  weekAgo.setDate(weekAgo.getDate() - 7)
  if (!filters.value.date_from) {
    filters.value.date_from = weekAgo.toISOString().slice(0, 10)
  }
  if (!filters.value.date_to) {
    filters.value.date_to = today.toISOString().slice(0, 10)
  }
})
</script>

<style scoped>
.gap-2 { gap: 8px; }
.journal-grid-wrapper { overflow-x: auto; }
.journal-table { min-width: 600px; }
.journal-student-col { min-width: 180px; }
.journal-lesson-col { min-width: 100px; }
.journal-student-cell { vertical-align: middle; }
.journal-cell { vertical-align: middle; }
</style>
