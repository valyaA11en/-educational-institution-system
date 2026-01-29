<template>
  <div v-if="exam">
    <v-card>
      <v-card-title class="d-flex align-center">
        <span class="text-h5">{{ exam.title }}</span>
        <v-spacer />
        <v-chip size="small" class="mr-2">{{ getTypeLabel(exam.type) }}</v-chip>
        <v-btn icon="mdi-pencil" variant="text" @click="editMode = true" />
      </v-card-title>

      <v-tabs v-model="activeTab">
        <v-tab value="details">Детали</v-tab>
        <v-tab value="commission">Комиссия</v-tab>
        <v-tab value="admissions">Допуски</v-tab>
        <v-tab value="results">Результаты</v-tab>
        <v-tab value="sheet">Ведомость</v-tab>
      </v-tabs>

      <v-card-text>
        <v-window v-model="activeTab">
          <!-- Детали -->
          <v-window-item value="details">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="exam.title"
                  label="Название"
                  variant="outlined"
                  :readonly="!editMode"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  :value="formatDateTime(exam.date_at)"
                  label="Дата и время"
                  variant="outlined"
                  readonly
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  :value="exam.subject?.name || '-'"
                  label="Предмет"
                  variant="outlined"
                  readonly
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  :value="exam.group?.name || '-'"
                  label="Группа"
                  variant="outlined"
                  readonly
                />
              </v-col>
            </v-row>
            <v-btn v-if="editMode" color="primary" @click="saveExam" :loading="saving">Сохранить</v-btn>
          </v-window-item>

          <!-- Комиссия -->
          <v-window-item value="commission">
            <div class="mb-3">
              <v-btn color="primary" @click="showAddCommission = true">
                <v-icon start>mdi-plus</v-icon>
                Добавить участника
              </v-btn>
            </div>
            <v-list>
              <v-list-item
                v-for="member in commissionMembers"
                :key="member.id"
              >
                <v-list-item-title>{{ member.user?.fio }}</v-list-item-title>
                <v-list-item-subtitle>{{ member.role === 'chair' ? 'Председатель' : 'Член комиссии' }}</v-list-item-subtitle>
                <template #append>
                  <v-btn icon="mdi-delete" size="small" variant="text" @click="removeCommissionMember(member.user_id)" />
                </template>
              </v-list-item>
            </v-list>

            <v-dialog v-model="showAddCommission" max-width="400">
              <v-card>
                <v-card-title>Добавить участника</v-card-title>
                <v-card-text>
                  <v-select
                    v-model="newMember.user_id"
                    :items="users"
                    item-title="fio"
                    item-value="id"
                    label="Пользователь"
                    variant="outlined"
                  />
                  <v-select
                    v-model="newMember.role"
                    :items="roles"
                    label="Роль"
                    variant="outlined"
                  />
                </v-card-text>
                <v-card-actions>
                  <v-spacer />
                  <v-btn variant="text" @click="showAddCommission = false">Отмена</v-btn>
                  <v-btn color="primary" @click="addCommissionMember">Добавить</v-btn>
                </v-card-actions>
              </v-card>
            </v-dialog>
          </v-window-item>

          <!-- Допуски -->
          <v-window-item value="admissions">
            <div class="mb-3">
              <v-btn color="primary" @click="seedRegistrations" :loading="seeding">
                Создать регистрации для группы
              </v-btn>
              <v-btn color="secondary" class="ml-2" @click="recalculateAdmissions" :loading="recalculating">
                Пересчитать допуски
              </v-btn>
            </div>
            <v-data-table
              :headers="admissionHeaders"
              :items="registrations"
              :loading="loadingRegistrations"
            >
              <template v-slot:item.status="{ item }">
                <v-chip :color="getStatusColor(item.status)" size="small">
                  {{ getStatusLabel(item.status) }}
                </v-chip>
              </template>
            </v-data-table>
          </v-window-item>

          <!-- Результаты -->
          <v-window-item value="results">
            <v-data-table
              :headers="resultHeaders"
              :items="resultsWithStudents"
              :loading="loadingResults"
            >
              <template v-slot:item.student="{ item }">
                {{ item.student?.fio || '-' }}
              </template>
              <template v-slot:item.score="{ item }">
                <v-text-field
                  v-model.number="item.score"
                  type="number"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @blur="saveResult(item)"
                />
              </template>
              <template v-slot:item.grade_value="{ item }">
                <v-select
                  v-model.number="item.grade_value"
                  :items="[2, 3, 4, 5]"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @update:model-value="saveResult(item)"
                />
              </template>
              <template v-slot:item.comment="{ item }">
                <v-text-field
                  v-model="item.comment"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @blur="saveResult(item)"
                />
              </template>
            </v-data-table>
          </v-window-item>

          <!-- Ведомость -->
          <v-window-item value="sheet">
            <div v-if="!sheetDocumentId">
              <v-btn color="primary" @click="generateSheet" :loading="generating">
                Сформировать ведомость
              </v-btn>
            </div>
            <div v-else>
              <v-alert type="success" class="mb-3">
                Ведомость создана (ID: {{ sheetDocumentId }})
              </v-alert>
              <v-btn color="primary" :to="{ name: 'document-view', params: { id: sheetDocumentId } }">
                Открыть документ
              </v-btn>
              <v-btn color="secondary" class="ml-2" @click="exportDocument('docx')">
                Экспорт DOCX
              </v-btn>
              <v-btn color="secondary" class="ml-2" @click="exportDocument('pdf')">
                Экспорт PDF
              </v-btn>
            </div>
          </v-window-item>
        </v-window>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { examsApi, type ExamDTO, type ExamCommissionDTO, type ExamRegistrationDTO, type ExamResultDTO } from '../api/exams'
import { useToast } from '../composables/useToast'
import { useEcho } from '../composables/useEcho'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const { showToast } = useToast()
const echo = useEcho()
const auth = useAuthStore()

const exam = ref<ExamDTO | null>(null)
const loading = ref(false)
const saving = ref(false)
const editMode = ref(false)
const activeTab = ref('details')

const commissionMembers = ref<ExamCommissionDTO[]>([])
const showAddCommission = ref(false)
const newMember = ref({ user_id: null as number | null, role: 'member' as string })

const registrations = ref<ExamRegistrationDTO[]>([])
const loadingRegistrations = ref(false)
const seeding = ref(false)
const recalculating = ref(false)

const results = ref<ExamResultDTO[]>([])
const loadingResults = ref(false)

const resultsWithStudents = computed(() => {
  // Merge results with registrations to show all students
  const studentMap = new Map<number, ExamResultDTO>()
  
  results.value.forEach(r => {
    studentMap.set(r.student_user_id, r)
  })

  // Add students from registrations who don't have results yet
  registrations.value.forEach(reg => {
    if (!studentMap.has(reg.student_user_id)) {
      studentMap.set(reg.student_user_id, {
        id: 0,
        exam_id: Number(route.params.id),
        student_user_id: reg.student_user_id,
        score: null,
        grade_value: null,
        comment: null,
        student: reg.student,
      } as ExamResultDTO)
    }
  })

  return Array.from(studentMap.values())
})

const sheetDocumentId = ref<number | null>(null)
const generating = ref(false)

const users = ref<any[]>([])
const roles = [
  { title: 'Председатель', value: 'chair' },
  { title: 'Член комиссии', value: 'member' },
]

const admissionHeaders = [
  { title: 'Студент', key: 'student.fio' },
  { title: 'Статус', key: 'status' },
  { title: 'Причина', key: 'reason' },
]

const resultHeaders = [
  { title: 'Студент', key: 'student' },
  { title: 'Балл', key: 'score' },
  { title: 'Оценка', key: 'grade_value' },
  { title: 'Комментарий', key: 'comment' },
]

const loadExam = async () => {
  loading.value = true
  try {
    exam.value = await examsApi.get(Number(route.params.id))
    await loadCommission()
    await loadRegistrations()
    await loadResults()
  } catch (error) {
    showToast('Ошибка загрузки экзамена', 'error')
    router.push({ name: 'exams' })
  } finally {
    loading.value = false
  }
}

const loadCommission = async () => {
  try {
    const response = await examsApi.getCommission(Number(route.params.id))
    commissionMembers.value = response.members
  } catch (error) {
    console.error('Failed to load commission:', error)
  }
}

const loadRegistrations = async () => {
  loadingRegistrations.value = true
  try {
    const response = await examsApi.getAdmissionReport(Number(route.params.id))
    registrations.value = response.registrations
  } catch (error) {
    console.error('Failed to load registrations:', error)
  } finally {
    loadingRegistrations.value = false
  }
}

const loadResults = async () => {
  loadingResults.value = true
  try {
    const response = await examsApi.getResults(Number(route.params.id))
    results.value = response.results
  } catch (error) {
    console.error('Failed to load results:', error)
  } finally {
    loadingResults.value = false
  }
}

const saveExam = async () => {
  if (!exam.value) return

  saving.value = true
  try {
    exam.value = await examsApi.update(exam.value.id, exam.value)
    showToast('Экзамен обновлен', 'success')
    editMode.value = false
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка обновления', 'error')
  } finally {
    saving.value = false
  }
}

const addCommissionMember = async () => {
  if (!newMember.value.user_id) return

  const members = [...commissionMembers.value, newMember.value as any]
  try {
    const response = await examsApi.setCommission(Number(route.params.id), members)
    commissionMembers.value = response.members
    showAddCommission.value = false
    newMember.value = { user_id: null, role: 'member' }
    showToast('Участник добавлен', 'success')
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка добавления', 'error')
  }
}

const removeCommissionMember = async (userId: number) => {
  const members = commissionMembers.value.filter(m => m.user_id !== userId)
  try {
    const response = await examsApi.setCommission(Number(route.params.id), members.map(m => ({ user_id: m.user_id, role: m.role })))
    commissionMembers.value = response.members
    showToast('Участник удален', 'success')
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка удаления', 'error')
  }
}

const seedRegistrations = async () => {
  seeding.value = true
  try {
    await examsApi.seedRegistrations(Number(route.params.id))
    showToast('Регистрации созданы', 'success')
    await loadRegistrations()
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка создания регистраций', 'error')
  } finally {
    seeding.value = false
  }
}

const recalculateAdmissions = async () => {
  recalculating.value = true
  try {
    for (const reg of registrations.value) {
      await examsApi.evaluateAdmission(Number(route.params.id), reg.student_user_id)
    }
    showToast('Допуски пересчитаны', 'success')
    await loadRegistrations()
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка пересчета', 'error')
  } finally {
    recalculating.value = false
  }
}

const saveResult = async (result: ExamResultDTO) => {
  try {
    await examsApi.setResult(Number(route.params.id), {
      student_user_id: result.student_user_id,
      score: result.score ?? undefined,
      grade_value: result.grade_value ?? undefined,
      comment: result.comment ?? undefined,
    })
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка сохранения результата', 'error')
  }
}

const generateSheet = async () => {
  generating.value = true
  try {
    const response = await examsApi.generateSheet(Number(route.params.id))
    sheetDocumentId.value = response.document_id
    showToast('Ведомость создана', 'success')
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка создания ведомости', 'error')
  } finally {
    generating.value = false
  }
}

const exportDocument = (format: string) => {
  if (!sheetDocumentId.value) return
  // TODO: Navigate to export endpoint
  window.open(`/api/v1/documents/${sheetDocumentId.value}/export?format=${format}`, '_blank')
}

const getTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    exam: 'Экзамен',
    test: 'Зачет',
    attestation: 'Аттестация',
  }
  return labels[type] || type
}

const getStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
    registered: 'Зарегистрирован',
    admitted: 'Допущен',
    not_admitted: 'Не допущен',
    passed: 'Сдал',
    failed: 'Не сдал',
  }
  return labels[status] || status
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    registered: 'default',
    admitted: 'success',
    not_admitted: 'error',
    passed: 'success',
    failed: 'error',
  }
  return colors[status] || 'default'
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('ru-RU')
}

// Realtime subscriptions
watch(() => route.params.id, () => {
  if (route.params.id) {
    loadExam()
  }
})

onMounted(() => {
  loadExam()

  // Subscribe to exam events
  if (echo) {
    echo.private(`user.${auth.user?.id}`)
      .listen('.exam.updated', (event: any) => {
        const payload = event.payload || {}
        if (payload.exam_id === Number(route.params.id)) {
          showToast('Экзамен обновлен', 'info')
          loadExam()
        }
      })
      .listen('.exam.reminder', (event: any) => {
        const payload = event.payload || {}
        if (payload.exam_id === Number(route.params.id)) {
          showToast(`Напоминание: ${payload.title || 'Экзамен скоро'}`, 'info')
        } else {
          showToast(`Напоминание об экзамене: ${payload.title || ''}`, 'info')
        }
      })
  }
})

onUnmounted(() => {
  // Cleanup is handled automatically by Echo
})
</script>

