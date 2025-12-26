<template>
  <div>
    <v-card>
      <v-card-title>Расписание</v-card-title>
      <v-card-text>
        <div v-if="loading" class="text-center py-8">
          <v-progress-circular indeterminate color="primary" />
        </div>
        <div v-else-if="items.length === 0" class="text-center py-8 text-grey">
          <p>Расписание пусто</p>
          <p v-if="versionId" class="text-caption">Выберите версию или создайте новую</p>
        </div>
        <div v-else>
          <!-- TODO: Implement schedule grid visualization -->
          <v-data-table
            :headers="headers"
            :items="items"
            :loading="loading"
            :items-per-page="50"
          >
            <template v-slot:item.date="{ item }">
              {{ formatDate(item.date) }}
            </template>
            <template v-slot:item.group="{ item }">
              {{ item.group?.name || '-' }}
            </template>
            <template v-slot:item.subject="{ item }">
              {{ item.subject?.name || '-' }}
            </template>
            <template v-slot:item.teacher="{ item }">
              {{ item.teacher?.fio || '-' }}
            </template>
            <template v-slot:item.room="{ item }">
              {{ item.room?.name || '-' }}
            </template>
          </v-data-table>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { scheduleApi, type ScheduleItemDTO } from '../api/schedule'

interface Props {
  versionId?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  versionId: null,
})

const loading = ref(false)
const items = ref<ScheduleItemDTO[]>([])

const headers = [
  { title: 'Дата', key: 'date' },
  { title: 'Группа', key: 'group' },
  { title: 'Предмет', key: 'subject' },
  { title: 'Преподаватель', key: 'teacher' },
  { title: 'Кабинет', key: 'room' },
]

const loadItems = async () => {
  if (!props.versionId) {
    items.value = []
    return
  }

  loading.value = true
  try {
    const response = await scheduleApi.getItems({
      version_id: props.versionId,
      per_page: 1000,
    })
    items.value = response.data
  } catch (error) {
    console.error('Failed to load schedule items:', error)
  } finally {
    loading.value = false
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('ru-RU')
}

watch(() => props.versionId, () => {
  loadItems()
}, { immediate: true })

onMounted(() => {
  loadItems()
})
</script>
