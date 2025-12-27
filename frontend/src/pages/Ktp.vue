<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span class="text-h5">Календарно-тематическое планирование</span>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="showCreateDialog = true"
        >
          Создать план
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.termId"
              :items="termOptions"
              item-title="name"
              item-value="id"
              label="Семестр"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadPlans"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.groupId"
              :items="groupOptions"
              item-title="name"
              item-value="id"
              label="Группа"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadPlans"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.subjectId"
              :items="subjectOptions"
              item-title="name"
              item-value="id"
              label="Предмет"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadPlans"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Статус"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadPlans"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="plans"
          :loading="loading"
          :items-per-page="pagination.per_page"
          :page="pagination.current_page"
          :server-items-length="pagination.total"
          @update:page="onPageChange"
        >
          <template v-slot:item.subject="{ item }">
            {{ item.subject?.name || '-' }}
          </template>
          <template v-slot:item.group="{ item }">
            {{ item.group?.name || '-' }}
          </template>
          <template v-slot:item.term="{ item }">
            {{ item.term?.name || '-' }}
          </template>
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ getStatusText(item.status) }}
            </v-chip>
          </template>
          <template v-slot:item.creator="{ item }">
            {{ item.creator?.fio || `ID: ${item.created_by}` }}
          </template>
          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              @click="$router.push({ name: 'ktp-view', params: { id: item.id } })"
            />
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Create Plan Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="600">
      <v-card>
        <v-card-title>Создать план КТП</v-card-title>
        <v-card-text>
          <v-form ref="createFormRef">
            <v-select
              v-model="newPlan.subject_id"
              :items="subjectOptions"
              item-title="name"
              item-value="id"
              label="Предмет *"
              variant="outlined"
              :rules="[v => !!v || 'Обязательное поле']"
            />
            <v-select
              v-model="newPlan.group_id"
              :items="groupOptions"
              item-title="name"
              item-value="id"
              label="Группа *"
              variant="outlined"
              :rules="[v => !!v || 'Обязательное поле']"
            />
            <v-select
              v-model="newPlan.term_id"
              :items="termOptions"
              item-title="name"
              item-value="id"
              label="Семестр *"
              variant="outlined"
              :rules="[v => !!v || 'Обязательное поле']"
            />
            <v-text-field
              v-model="newPlan.name"
              label="Название"
              variant="outlined"
            />
            <v-textarea
              v-model="newPlan.description"
              label="Описание"
              variant="outlined"
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showCreateDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="createPlan">Создать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ktpApi, { type KtpPlanDTO, type KtpListResponse } from '../api/ktp'
import { directoryApi, type TermDTO, type GroupDTO, type SubjectDTO } from '../api/directory'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { showToast } = useToast()

const loading = ref(false)
const plans = ref<KtpPlanDTO[]>([])
const pagination = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1,
})

const filters = ref({
  termId: null as number | null,
  groupId: null as number | null,
  subjectId: null as number | null,
  status: null as string | null,
})

const termOptions = ref<TermDTO[]>([])
const groupOptions = ref<GroupDTO[]>([])
const subjectOptions = ref<SubjectDTO[]>([])

const statusOptions = [
  { title: 'Черновик', value: 'draft' },
  { title: 'Активный', value: 'active' },
  { title: 'Архив', value: 'archived' },
]

const headers = [
  { title: 'Предмет', key: 'subject', sortable: false },
  { title: 'Группа', key: 'group', sortable: false },
  { title: 'Семестр', key: 'term', sortable: false },
  { title: 'Статус', key: 'status', sortable: true },
  { title: 'Создатель', key: 'creator', sortable: false },
  { title: 'Действия', key: 'actions', sortable: false, width: '100px' },
]

const showCreateDialog = ref(false)
const createFormRef = ref()
const newPlan = ref({
  subject_id: null as number | null,
  group_id: null as number | null,
  term_id: null as number | null,
  name: '',
  description: '',
})

const loadPlans = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
    }
    if (filters.value.termId) params.termId = filters.value.termId
    if (filters.value.groupId) params.groupId = filters.value.groupId
    if (filters.value.subjectId) params.subjectId = filters.value.subjectId
    if (filters.value.status) params.status = filters.value.status

    const response: KtpListResponse = await ktpApi.getPlans(params)
    plans.value = response.data
    pagination.value = {
      current_page: response.current_page,
      per_page: response.per_page,
      total: response.total,
      last_page: response.last_page,
    }
  } catch (error) {
    console.error('Failed to load plans:', error)
    showToast('Ошибка загрузки планов', 'error')
  } finally {
    loading.value = false
  }
}

const loadDirectory = async () => {
  try {
    const [terms, groups, subjects] = await Promise.all([
      directoryApi.getTerms(),
      directoryApi.getGroups(),
      directoryApi.getSubjects(),
    ])
    termOptions.value = terms.data || terms
    groupOptions.value = groups.data || groups
    subjectOptions.value = subjects.data || subjects
  } catch (error) {
    console.error('Failed to load directory:', error)
  }
}

const onPageChange = (page: number) => {
  pagination.value.current_page = page
  loadPlans()
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'grey',
    active: 'green',
    archived: 'default',
  }
  return colors[status] || 'default'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Черновик',
    active: 'Активный',
    archived: 'Архив',
  }
  return texts[status] || status
}

const createPlan = async () => {
  const { valid } = await createFormRef.value?.validate()
  if (!valid) return

  try {
    const plan = await ktpApi.createPlan({
      subject_id: newPlan.value.subject_id!,
      group_id: newPlan.value.group_id!,
      term_id: newPlan.value.term_id!,
      name: newPlan.value.name || undefined,
      description: newPlan.value.description || undefined,
    })
    showToast('План создан успешно', 'success')
    showCreateDialog.value = false
    newPlan.value = {
      subject_id: null,
      group_id: null,
      term_id: null,
      name: '',
      description: '',
    }
    router.push({ name: 'ktp-view', params: { id: plan.id } })
  } catch (error: any) {
    console.error('Failed to create plan:', error)
    showToast(error.response?.data?.message || 'Ошибка создания плана', 'error')
  }
}

onMounted(() => {
  loadDirectory()
  loadPlans()
})
</script>









