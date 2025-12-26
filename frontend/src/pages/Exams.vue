<template>
  <div>
    <v-card>
      <v-card-title class="d-flex align-center">
        <span class="text-h5">Экзамены и аттестации</span>
        <v-spacer />
        <v-btn color="primary" :to="{ name: 'exam-new' }">
          <v-icon start>mdi-plus</v-icon>
          Создать экзамен
        </v-btn>
      </v-card-title>

      <v-card-text>
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.termId"
              :items="terms"
              item-title="name"
              item-value="id"
              label="Семестр"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadExams"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.groupId"
              :items="groups"
              item-title="name"
              item-value="id"
              label="Группа"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadExams"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field
              v-model="filters.from"
              type="date"
              label="С"
              variant="outlined"
              density="compact"
              @update:model-value="loadExams"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field
              v-model="filters.to"
              type="date"
              label="По"
              variant="outlined"
              density="compact"
              @update:model-value="loadExams"
            />
          </v-col>
        </v-row>

        <v-list>
          <template v-for="exam in examsByDate" :key="exam.date">
            <v-list-subheader>{{ formatDate(exam.date) }}</v-list-subheader>
            <v-list-item
              v-for="item in exam.items"
              :key="item.id"
              :to="{ name: 'exam-detail', params: { id: item.id } }"
            >
              <v-list-item-title>{{ item.title }}</v-list-item-title>
              <v-list-item-subtitle>
                {{ item.type }} | {{ item.group?.name || '-' }} | {{ item.subject?.name || '-' }}
              </v-list-item-subtitle>
              <template #append>
                <v-chip size="small">{{ getTypeLabel(item.type) }}</v-chip>
              </template>
            </v-list-item>
          </template>
        </v-list>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { examsApi, type ExamDTO } from '../api/exams'
import { useToast } from '../composables/useToast'

const { showToast } = useToast()

const loading = ref(false)
const exams = ref<ExamDTO[]>([])

const filters = ref({
  termId: null as number | null,
  groupId: null as number | null,
  from: null as string | null,
  to: null as string | null,
})

const examsByDate = computed(() => {
  const grouped: Record<string, ExamDTO[]> = {}
  
  exams.value.forEach((exam) => {
    const date = exam.date_at.split('T')[0]
    if (!grouped[date]) {
      grouped[date] = []
    }
    grouped[date].push(exam)
  })

  return Object.keys(grouped)
    .sort()
    .map((date) => ({
      date,
      items: grouped[date].sort((a, b) => a.date_at.localeCompare(b.date_at)),
    }))
})

const terms = ref<any[]>([])
const groups = ref<any[]>([])

const loadExams = async () => {
  loading.value = true
  try {
    const response = await examsApi.list(filters.value)
    exams.value = response.data
  } catch (error) {
    showToast('Ошибка загрузки экзаменов', 'error')
  } finally {
    loading.value = false
  }
}

const getTypeLabel = (type: string) => {
  const labels: Record<string, string> = {
    exam: 'Экзамен',
    test: 'Зачет',
    attestation: 'Аттестация',
  }
  return labels[type] || type
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('ru-RU', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

onMounted(() => {
  loadExams()
  // TODO: Load terms, groups
})
</script>

