<template>
  <v-container v-if="contest">
    <v-card>
      <v-card-title class="d-flex align-center">
        <span class="text-h5">{{ contest.title }}</span>
        <v-spacer />
        <v-chip size="small" class="mr-2">
          {{ formatVisibility(contest.visibility_scope) }}
        </v-chip>
        <v-btn
          v-if="canManage"
          icon="mdi-pencil"
          variant="text"
          @click="editMode = !editMode"
        />
      </v-card-title>

      <v-tabs v-model="activeTab">
        <v-tab value="description">Описание</v-tab>
        <v-tab v-if="canManage" value="targets">Участники</v-tab>
        <v-tab value="submissions">Работы</v-tab>
        <v-tab v-if="canManage" value="jury">Жюри</v-tab>
        <v-tab v-if="canManage" value="rubric">Рубрика</v-tab>
        <v-tab value="results">Результаты</v-tab>
        <v-tab v-if="canManage" value="certificates">Сертификаты</v-tab>
      </v-tabs>

      <v-card-text>
        <v-window v-model="activeTab">
          <!-- Описание -->
          <v-window-item value="description">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="contest.title"
                  label="Название"
                  variant="outlined"
                  :readonly="!editMode || !canManage"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  :value="formatDateTime(contest.start_at)"
                  label="Начало"
                  variant="outlined"
                  readonly
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  :value="formatDateTime(contest.end_at)"
                  label="Окончание"
                  variant="outlined"
                  readonly
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="contest.description"
                  label="Описание"
                  variant="outlined"
                  rows="5"
                  :readonly="!editMode || !canManage"
                />
              </v-col>
            </v-row>
            <div v-if="isParticipant && isActive" class="mt-4">
              <v-btn color="primary" @click="showSubmitDialog = true">
                <v-icon start>mdi-upload</v-icon>
                Отправить работу
              </v-btn>
            </div>
            <v-btn
              v-if="editMode && canManage"
              color="primary"
              class="mt-4"
              :loading="saving"
              @click="saveContest"
            >
              Сохранить
            </v-btn>
          </v-window-item>

          <!-- Участники -->
          <v-window-item v-if="canManage" value="targets">
            <div class="mb-3">
              <v-btn color="primary" @click="showAddTarget = true">
                <v-icon start>mdi-plus</v-icon>
                Добавить участника
              </v-btn>
            </div>
            <v-list>
              <v-list-item
                v-for="target in contest.targets || []"
                :key="target.id"
              >
                <v-list-item-title>
                  {{ target.group?.name || target.user?.fio || '-' }}
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ target.group ? 'Группа' : 'Пользователь' }}
                </v-list-item-subtitle>
                <template #append>
                  <v-btn
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    @click="removeTarget(target.id)"
                  />
                </template>
              </v-list-item>
            </v-list>
          </v-window-item>

          <!-- Работы -->
          <v-window-item value="submissions">
            <v-data-table
              :headers="submissionHeaders"
              :items="submissions"
              :loading="loadingSubmissions"
            >
              <template #item.participant="{ item }">
                {{ item.participant?.fio || '-' }}
              </template>
              <template #item.files="{ item }">
                {{ item.files?.length || 0 }} файл(ов)
              </template>
              <template #item.actions="{ item }">
                <v-btn
                  v-if="isJury"
                  icon="mdi-star"
                  size="small"
                  variant="text"
                  @click="openScoreDialog(item)"
                />
                <v-btn
                  icon="mdi-eye"
                  size="small"
                  variant="text"
                  @click="viewSubmission(item)"
                />
              </template>
            </v-data-table>
          </v-window-item>

          <!-- Жюри -->
          <v-window-item v-if="canManage" value="jury">
            <div class="mb-3">
              <v-btn color="primary" @click="showAddJury = true">
                <v-icon start>mdi-plus</v-icon>
                Добавить жюри
              </v-btn>
            </div>
            <v-list>
              <v-list-item
                v-for="member in contest.jury || []"
                :key="member.id"
              >
                <v-list-item-title>{{ member.user?.fio }}</v-list-item-title>
                <v-list-item-subtitle>
                  {{ member.role === 'chair' ? 'Председатель' : 'Член жюри' }}
                </v-list-item-subtitle>
                <template #append>
                  <v-btn
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    @click="removeJury(member.user_id)"
                  />
                </template>
              </v-list-item>
            </v-list>
          </v-window-item>

          <!-- Рубрика -->
          <v-window-item v-if="canManage" value="rubric">
            <div v-if="contest.rubrics && contest.rubrics.length > 0">
              <v-card v-for="rubric in contest.rubrics" :key="rubric.id" class="mb-4">
                <v-card-title>{{ rubric.title }}</v-card-title>
                <v-card-text>
                  <v-data-table
                    :headers="criteriaHeaders"
                    :items="rubric.criteria_json"
                  >
                    <template #item.weight="{ item }">
                      {{ item.weight || 1 }}
                    </template>
                  </v-data-table>
                </v-card-text>
              </v-card>
            </div>
            <v-btn color="primary" @click="showAddRubric = true">
              <v-icon start>mdi-plus</v-icon>
              Добавить рубрику
            </v-btn>
          </v-window-item>

          <!-- Результаты -->
          <v-window-item value="results">
            <div v-if="canManage" class="mb-3">
              <v-btn
                color="primary"
                :loading="publishing"
                @click="publishResults"
              >
                Опубликовать результаты
              </v-btn>
            </div>
            <v-data-table
              :headers="resultHeaders"
              :items="results"
              :loading="loadingResults"
            >
              <template #item.fio="{ item }">
                {{ item.submission?.participant?.fio || '-' }}
              </template>
              <template #item.place="{ item }">
                {{ item.place || '-' }}
              </template>
              <template #item.final_score="{ item }">
                {{ item.final_score.toFixed(2) }}
              </template>
              <template #item.certificate="{ item }">
                <v-btn
                  v-if="getResultCertificateId(item)"
                  icon="mdi-download"
                  size="small"
                  variant="text"
                  @click="downloadCertificate(getResultCertificateId(item)!)"
                />
                <span v-else>-</span>
              </template>
            </v-data-table>
          </v-window-item>

          <!-- Сертификаты -->
          <v-window-item v-if="canManage" value="certificates">
            <v-btn
              color="primary"
              :loading="generating"
              @click="generateCertificates"
            >
              Сгенерировать сертификаты
            </v-btn>
            <div v-if="certificates.length > 0" class="mt-4">
              <v-list>
                <v-list-item
                  v-for="cert in certificates"
                  :key="cert.userId"
                >
                  <v-list-item-title>
                    Пользователь ID: {{ cert.userId }}
                  </v-list-item-title>
                  <v-list-item-subtitle>
                    Документ ID: {{ cert.documentId }}
                  </v-list-item-subtitle>
                  <template #append>
                    <v-btn
                      icon="mdi-download"
                      size="small"
                      variant="text"
                      @click="downloadCertificate(cert.documentId)"
                    />
                  </template>
                </v-list-item>
              </v-list>
            </div>
          </v-window-item>
        </v-window>
      </v-card-text>
    </v-card>

    <!-- Диалог отправки работы -->
    <v-dialog v-model="showSubmitDialog" max-width="600">
      <v-card>
        <v-card-title>Отправить работу</v-card-title>
        <v-card-text>
          <v-form ref="submitFormRef">
            <v-text-field
              v-model="submission.title"
              label="Название работы"
              variant="outlined"
            />
            <v-textarea
              v-model="submission.description"
              label="Описание"
              variant="outlined"
              rows="3"
            />
            <FileUploader
              v-model="submission.fileIds"
              :max-files="10"
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showSubmitDialog = false">Отмена</v-btn>
          <v-btn
            color="primary"
            :loading="submitting"
            @click="submitWork"
          >
            Отправить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Диалог оценивания -->
    <v-dialog v-model="showScoreDialog" max-width="800">
      <v-card v-if="scoringSubmission && contest.rubrics && contest.rubrics[0]">
        <v-card-title>Оценить работу</v-card-title>
        <v-card-text>
          <v-form ref="scoreFormRef">
            <div
              v-for="criterion in contest.rubrics[0].criteria_json"
              :key="criterion.key"
              class="mb-4"
            >
              <v-text-field
                v-model.number="scoreForm.rubric_json[criterion.key]"
                :label="criterion.title"
                type="number"
                :max="criterion.maxScore"
                min="0"
                variant="outlined"
                :suffix="`/ ${criterion.maxScore}`"
              />
            </div>
            <v-textarea
              v-model="scoreForm.comment"
              label="Комментарий"
              variant="outlined"
              rows="3"
            />
            <div class="text-h6 mt-4">
              Итого: {{ calculatedTotalScore.toFixed(2) }}
            </div>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showScoreDialog = false">Отмена</v-btn>
          <v-btn
            color="primary"
            :loading="scoring"
            @click="saveScore"
          >
            Сохранить оценку
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Диалог добавления участника -->
    <v-dialog v-model="showAddTarget" max-width="400">
      <v-card>
        <v-card-title>Добавить участника</v-card-title>
        <v-card-text>
          <v-select
            v-model="newTarget.type"
            :items="[
              { title: 'Группа', value: 'group' },
              { title: 'Пользователь', value: 'user' },
            ]"
            label="Тип"
            variant="outlined"
          />
          <v-select
            v-if="newTarget.type === 'group'"
            v-model="newTarget.group_id"
            :items="groups"
            item-title="name"
            item-value="id"
            label="Группа"
            variant="outlined"
          />
          <v-select
            v-if="newTarget.type === 'user'"
            v-model="newTarget.user_id"
            :items="users"
            item-title="fio"
            item-value="id"
            label="Пользователь"
            variant="outlined"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showAddTarget = false">Отмена</v-btn>
          <v-btn color="primary" @click="addTarget">Добавить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Диалог добавления жюри -->
    <v-dialog v-model="showAddJury" max-width="400">
      <v-card>
        <v-card-title>Добавить жюри</v-card-title>
        <v-card-text>
          <v-select
            v-model="newJury.user_id"
            :items="users"
            item-title="fio"
            item-value="id"
            label="Пользователь"
            variant="outlined"
          />
          <v-select
            v-model="newJury.role"
            :items="[
              { title: 'Председатель', value: 'chair' },
              { title: 'Член жюри', value: 'member' },
            ]"
            label="Роль"
            variant="outlined"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showAddJury = false">Отмена</v-btn>
          <v-btn color="primary" @click="addJury">Добавить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Диалог добавления рубрики -->
    <v-dialog v-model="showAddRubric" max-width="800">
      <v-card>
        <v-card-title>Добавить рубрику</v-card-title>
        <v-card-text>
          <v-form ref="rubricFormRef">
            <v-text-field
              v-model="newRubric.title"
              label="Название рубрики"
              variant="outlined"
              required
            />
            <div class="mt-4">
              <div class="d-flex justify-space-between align-center mb-2">
                <h3>Критерии</h3>
                <v-btn icon="mdi-plus" size="small" @click="addCriterion" />
              </div>
              <v-data-table
                :headers="criteriaEditHeaders"
                :items="newRubric.criteria_json"
              >
                <template #item.key="{ item }">
                  <v-text-field
                    v-model="item.key"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                </template>
                <template #item.title="{ item }">
                  <v-text-field
                    v-model="item.title"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                </template>
                <template #item.maxScore="{ item }">
                  <v-text-field
                    v-model.number="item.maxScore"
                    type="number"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                </template>
                <template #item.weight="{ item }">
                  <v-text-field
                    v-model.number="item.weight"
                    type="number"
                    step="0.1"
                    min="0"
                    max="1"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                </template>
                <template #item.actions="{ index }">
                  <v-btn
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    @click="removeCriterion(index)"
                  />
                </template>
              </v-data-table>
            </div>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showAddRubric = false">Отмена</v-btn>
          <v-btn color="primary" @click="saveRubric">Сохранить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { contestsApi, type ContestDTO, type ContestSubmissionDTO, type ContestResultDTO } from '../api/contests'
import { directoryApi, type GroupDTO } from '../api/directory'
import { usersApi, type UserDTO } from '../api/users'
import { useAuthStore } from '../stores/auth'
import { useToast } from '../composables/useToast'
import FileUploader from '../components/FileUploader.vue'
import { documentsApi } from '../api/documents'

const route = useRoute()
const auth = useAuthStore()
const { showToast } = useToast()

const contest = ref<ContestDTO | null>(null)
const loading = ref(false)
const saving = ref(false)
const editMode = ref(false)
const activeTab = ref('description')
const submissions = ref<ContestSubmissionDTO[]>([])
const results = ref<ContestResultDTO[]>([])
const loadingSubmissions = ref(false)
const loadingResults = ref(false)
const submitting = ref(false)
const scoring = ref(false)
const publishing = ref(false)
const generating = ref(false)
const certificates = ref<Array<{ userId: number; documentId: number }>>([])

const showSubmitDialog = ref(false)
const showScoreDialog = ref(false)
const showAddTarget = ref(false)
const showAddJury = ref(false)
const showAddRubric = ref(false)

const groups = ref<GroupDTO[]>([])
const users = ref<UserDTO[]>([])

const submission = ref({
  title: '',
  description: '',
  fileIds: [] as number[],
})

const scoringSubmission = ref<ContestSubmissionDTO | null>(null)
const scoreForm = ref({
  rubric_json: {} as Record<string, number>,
  comment: '',
})

const newTarget = ref({
  type: 'group' as 'group' | 'user',
  group_id: null as number | null,
  user_id: null as number | null,
})

const newJury = ref({
  user_id: null as number | null,
  role: 'member' as 'chair' | 'member',
})

const newRubric = ref({
  title: '',
  criteria_json: [] as Array<{ key: string; title: string; maxScore: number; weight?: number }>,
})

const canManage = computed(() => {
  return auth.hasPermission('contests.manage') || auth.hasRole('admin')
})

const isJury = computed(() => {
  if (!contest.value || !auth.user) return false
  return contest.value.jury?.some(j => j.user_id === auth.user?.id) || false
})

const isParticipant = computed(() => {
  if (!contest.value || !auth.user) return false
  if (contest.value.visibility_scope === 'all') return true
  if (contest.value.visibility_scope === 'group') {
    return contest.value.targets?.some(t => t.group_id === auth.user?.group_id) || false
  }
  if (contest.value.visibility_scope === 'invite') {
    return contest.value.targets?.some(t => t.user_id === auth.user?.id) || false
  }
  return false
})

const isActive = computed(() => {
  if (!contest.value) return false
  const now = new Date()
  const start = new Date(contest.value.start_at)
  const end = new Date(contest.value.end_at)
  return now >= start && now <= end
})

const calculatedTotalScore = computed(() => {
  if (!contest.value?.rubrics?.[0]) return 0
  let total = 0
  for (const criterion of contest.value.rubrics[0].criteria_json) {
    const score = scoreForm.value.rubric_json[criterion.key] || 0
    const weight = criterion.weight || 1
    total += (score / criterion.maxScore) * 100 * weight
  }
  return total
})

const submissionHeaders = [
  { title: 'Участник', key: 'participant' },
  { title: 'Название', key: 'title' },
  { title: 'Файлы', key: 'files' },
  { title: 'Действия', key: 'actions', sortable: false },
]

const resultHeaders = [
  { title: 'Место', key: 'place' },
  { title: 'ФИО', key: 'fio' },
  { title: 'Балл', key: 'final_score' },
  { title: 'Сертификат', key: 'certificate', sortable: false },
]

const getResultCertificateId = (result: ContestResultDTO): number | null => {
  const participantId = result.submission?.participant?.id
  if (!participantId) return null
  const cert = certificates.value.find((c) => c.userId === participantId)
  return cert?.documentId ?? null
}

const criteriaHeaders = [
  { title: 'Ключ', key: 'key' },
  { title: 'Название', key: 'title' },
  { title: 'Макс. балл', key: 'maxScore' },
  { title: 'Вес', key: 'weight' },
]

const criteriaEditHeaders = [
  { title: 'Ключ', key: 'key' },
  { title: 'Название', key: 'title' },
  { title: 'Макс. балл', key: 'maxScore' },
  { title: 'Вес', key: 'weight' },
  { title: 'Действия', key: 'actions', sortable: false },
]

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('ru-RU')
}

const formatVisibility = (scope: string) => {
  return scope === 'all' ? 'Все' : scope === 'group' ? 'Группа' : 'По приглашению'
}

const loadContest = async () => {
  loading.value = true
  try {
    const res = await contestsApi.get(Number(route.params.id))
    contest.value = res.data
  } catch (error) {
    console.error('Failed to load contest:', error)
    showToast('Ошибка загрузки конкурса', 'error')
  } finally {
    loading.value = false
  }
}

const saveContest = async () => {
  if (!contest.value) return
  saving.value = true
  try {
    await contestsApi.update(contest.value.id, {
      title: contest.value.title || undefined,
      description: contest.value.description || undefined,
    })
    editMode.value = false
    showToast('Конкурс обновлен', 'success')
  } catch (error) {
    console.error('Failed to save contest:', error)
    showToast('Ошибка сохранения', 'error')
  } finally {
    saving.value = false
  }
}

const loadSubmissions = async () => {
  loadingSubmissions.value = true
  try {
    const res = await contestsApi.getSubmissions(Number(route.params.id))
    submissions.value = res.data
  } catch (error) {
    console.error('Failed to load submissions:', error)
  } finally {
    loadingSubmissions.value = false
  }
}

const loadResults = async () => {
  loadingResults.value = true
  try {
    const res = await contestsApi.getResults(Number(route.params.id))
    results.value = res.data
  } catch (error) {
    console.error('Failed to load results:', error)
  } finally {
    loadingResults.value = false
  }
}

const submitWork = async () => {
  if (!contest.value) return
  submitting.value = true
  try {
    await contestsApi.submit(contest.value.id, {
      title: submission.value.title || undefined,
      description: submission.value.description || undefined,
      fileIds: submission.value.fileIds,
    })
    showSubmitDialog.value = false
    submission.value = { title: '', description: '', fileIds: [] }
    showToast('Работа отправлена', 'success')
    await loadSubmissions()
  } catch (error) {
    console.error('Failed to submit work:', error)
    showToast('Ошибка отправки работы', 'error')
  } finally {
    submitting.value = false
  }
}

const openScoreDialog = (submission: ContestSubmissionDTO) => {
  scoringSubmission.value = submission
  scoreForm.value = {
    rubric_json: {},
    comment: '',
  }
  if (contest.value?.rubrics?.[0]) {
    for (const criterion of contest.value.rubrics[0].criteria_json) {
      scoreForm.value.rubric_json[criterion.key] = 0
    }
  }
  showScoreDialog.value = true
}

const saveScore = async () => {
  if (!contest.value || !scoringSubmission.value) return
  scoring.value = true
  try {
    await contestsApi.score(contest.value.id, scoringSubmission.value.id, {
      rubric_json: scoreForm.value.rubric_json,
      comment: scoreForm.value.comment || undefined,
    })
    showScoreDialog.value = false
    showToast('Оценка сохранена', 'success')
    await loadSubmissions()
  } catch (error) {
    console.error('Failed to save score:', error)
    showToast('Ошибка сохранения оценки', 'error')
  } finally {
    scoring.value = false
  }
}

const addTarget = async () => {
  if (!contest.value) return
  try {
    const targets = contest.value.targets || []
    const newTargets = [...targets]
    if (newTarget.value.type === 'group' && newTarget.value.group_id) {
      newTargets.push({ group_id: newTarget.value.group_id, user_id: null } as any)
    } else if (newTarget.value.type === 'user' && newTarget.value.user_id) {
      newTargets.push({ group_id: null, user_id: newTarget.value.user_id } as any)
    }
    await contestsApi.setTargets(contest.value.id, newTargets.map(t => ({
      group_id: t.group_id || undefined,
      user_id: t.user_id || undefined,
    })))
    showAddTarget.value = false
    newTarget.value = { type: 'group', group_id: null, user_id: null }
    await loadContest()
    showToast('Участник добавлен', 'success')
  } catch (error) {
    console.error('Failed to add target:', error)
    showToast('Ошибка добавления участника', 'error')
  }
}

const removeTarget = async (targetId: number) => {
  if (!contest.value) return
  try {
    const targets = (contest.value.targets || []).filter(t => t.id !== targetId)
    await contestsApi.setTargets(contest.value.id, targets.map(t => ({
      group_id: t.group_id || undefined,
      user_id: t.user_id || undefined,
    })))
    await loadContest()
    showToast('Участник удален', 'success')
  } catch (error) {
    console.error('Failed to remove target:', error)
    showToast('Ошибка удаления участника', 'error')
  }
}

const addJury = async () => {
  if (!contest.value || !newJury.value.user_id) return
  try {
    await contestsApi.addJury(contest.value.id, {
      user_id: newJury.value.user_id,
      role: newJury.value.role,
    })
    showAddJury.value = false
    newJury.value = { user_id: null, role: 'member' }
    await loadContest()
    showToast('Жюри добавлен', 'success')
  } catch (error) {
    console.error('Failed to add jury:', error)
    showToast('Ошибка добавления жюри', 'error')
  }
}

const removeJury = async (userId: number) => {
  // TODO: Implement remove jury endpoint
  showToast(`Удаление жюри (ID ${userId}) не реализовано`, 'warning')
}

const addCriterion = () => {
  newRubric.value.criteria_json.push({
    key: '',
    title: '',
    maxScore: 10,
    weight: 1,
  })
}

const removeCriterion = (index: number) => {
  newRubric.value.criteria_json.splice(index, 1)
}

const saveRubric = async () => {
  if (!contest.value) return
  try {
    await contestsApi.addRubric(contest.value.id, {
      title: newRubric.value.title,
      criteria_json: newRubric.value.criteria_json,
    })
    showAddRubric.value = false
    newRubric.value = { title: '', criteria_json: [] }
    await loadContest()
    showToast('Рубрика добавлена', 'success')
  } catch (error) {
    console.error('Failed to save rubric:', error)
    showToast('Ошибка сохранения рубрики', 'error')
  }
}

const publishResults = async () => {
  if (!contest.value) return
  publishing.value = true
  try {
    await contestsApi.publishResults(contest.value.id)
    showToast('Результаты опубликованы', 'success')
    await loadResults()
  } catch (error) {
    console.error('Failed to publish results:', error)
    showToast('Ошибка публикации результатов', 'error')
  } finally {
    publishing.value = false
  }
}

const generateCertificates = async () => {
  if (!contest.value) return
  generating.value = true
  try {
    const res = await contestsApi.generateCertificates(contest.value.id)
    certificates.value = res.data
    showToast('Сертификаты сгенерированы', 'success')
  } catch (error) {
    console.error('Failed to generate certificates:', error)
    showToast('Ошибка генерации сертификатов', 'error')
  } finally {
    generating.value = false
  }
}

const downloadCertificate = async (documentId: number) => {
  try {
    const blob = await documentsApi.export(documentId, 'docx')
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `certificate-${documentId}.docx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    showToast('Сертификат скачан', 'success')
  } catch (error) {
    console.error('Failed to download certificate:', error)
    showToast('Ошибка скачивания сертификата', 'error')
  }
}

const viewSubmission = (submission: ContestSubmissionDTO) => {
  // TODO: Open submission detail dialog
  showToast(`Просмотр работы: ${submission.title || 'без названия'}`, 'info')
}

const loadGroups = async () => {
  try {
    const res = await directoryApi.getGroups()
    groups.value = res
  } catch (error) {
    console.error('Failed to load groups:', error)
  }
}

const loadUsers = async () => {
  try {
    const res = await usersApi.list({ per_page: 1000 })
    users.value = res.data || []
  } catch (error) {
    console.error('Failed to load users:', error)
  }
}

onMounted(async () => {
  await loadContest()
  await loadSubmissions()
  await loadResults()
  if (canManage.value) {
    await loadGroups()
    await loadUsers()
  }
})
</script>

