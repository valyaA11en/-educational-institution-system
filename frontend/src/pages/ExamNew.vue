<template>
  <div>
    <v-card>
      <v-card-title>Создать экзамен</v-card-title>
      <v-card-text>
        <v-form ref="formRef">
          <v-select
            v-model="exam.term_id"
            :items="terms"
            item-title="name"
            item-value="id"
            label="Семестр"
            variant="outlined"
            required
            :rules="[v => !!v || 'Выберите семестр']"
          />
          <v-select
            v-model="exam.type"
            :items="examTypes"
            label="Тип"
            variant="outlined"
            required
            :rules="[v => !!v || 'Выберите тип']"
          />
          <v-text-field
            v-model="exam.title"
            label="Название"
            variant="outlined"
            required
            :rules="[v => !!v || 'Введите название']"
          />
          <v-select
            v-model="exam.subject_id"
            :items="subjects"
            item-title="name"
            item-value="id"
            label="Предмет"
            variant="outlined"
            clearable
          />
          <v-select
            v-model="exam.group_id"
            :items="groups"
            item-title="name"
            item-value="id"
            label="Группа"
            variant="outlined"
            clearable
          />
          <v-text-field
            v-model="exam.date_at"
            type="datetime-local"
            label="Дата и время"
            variant="outlined"
            required
            :rules="[v => !!v || 'Выберите дату и время']"
          />
          <v-select
            v-model="exam.room_id"
            :items="rooms"
            item-title="name"
            item-value="id"
            label="Кабинет"
            variant="outlined"
            clearable
          />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :to="{ name: 'exams' }">Отмена</v-btn>
        <v-btn color="primary" @click="createExam" :loading="creating">Создать</v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { examsApi } from '../api/exams'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { showToast } = useToast()

const creating = ref(false)
const formRef = ref()

const exam = ref({
  term_id: null as number | null,
  type: 'exam' as string,
  title: '',
  subject_id: null as number | null,
  group_id: null as number | null,
  date_at: '',
  room_id: null as number | null,
})

const examTypes = [
  { title: 'Экзамен', value: 'exam' },
  { title: 'Зачет', value: 'test' },
  { title: 'Аттестация', value: 'attestation' },
]

const terms = ref<any[]>([])
const subjects = ref<any[]>([])
const groups = ref<any[]>([])
const rooms = ref<any[]>([])

const createExam = async () => {
  if (!formRef.value?.validate()) return

  creating.value = true
  try {
    const created = await examsApi.create(exam.value as any)
    showToast('Экзамен создан', 'success')
    router.push({ name: 'exam-detail', params: { id: created.id } })
  } catch (error: any) {
    showToast(error.response?.data?.message || 'Ошибка создания экзамена', 'error')
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  // TODO: Load terms, subjects, groups, rooms
})
</script>

