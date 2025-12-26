<template>
  <div>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span>История изменений</span>
        <div class="d-flex align-center gap-2">
          <v-text-field
            v-model="filters.dateFrom"
            type="date"
            label="С"
            variant="outlined"
            density="compact"
            style="max-width: 150px"
            @update:model-value="loadChangelog"
          />
          <v-text-field
            v-model="filters.dateTo"
            type="date"
            label="По"
            variant="outlined"
            density="compact"
            style="max-width: 150px"
            @update:model-value="loadChangelog"
          />
          <v-btn
            icon="mdi-refresh"
            variant="text"
            @click="loadChangelog"
            :loading="loading"
          />
        </div>
      </v-card-title>
      <v-card-text>
        <v-data-table
          :headers="headers"
          :items="changelog"
          :loading="loading"
          :items-per-page="20"
        >
          <template v-slot:item.created_at="{ item }">
            {{ formatDateTime(item.created_at) }}
          </template>
          <template v-slot:item.action="{ item }">
            <v-chip :color="getActionColor(item.action)" size="small">
              {{ getActionText(item.action) }}
            </v-chip>
          </template>
          <template v-slot:item.actor="{ item }">
            {{ item.actor?.fio || `ID: ${item.actor_user_id}` }}
          </template>
          <template v-slot:item.item_summary="{ item }">
            <div v-if="item.scheduleItem">
              {{ formatItemSummary(item.scheduleItem) }}
            </div>
            <span v-else class="text-grey">-</span>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { scheduleApi, type ScheduleItemDTO } from '../api/schedule'

interface Props {
  versionId: number | null
}

const props = defineProps<Props>()

const loading = ref(false)
const changelog = ref<any[]>([])
const filters = ref({
  dateFrom: null as string | null,
  dateTo: null as string | null,
})

const headers = [
  { title: 'Дата/Время', key: 'created_at' },
  { title: 'Действие', key: 'action' },
  { title: 'Автор', key: 'actor' },
  { title: 'Элемент расписания', key: 'item_summary' },
]

const loadChangelog = async () => {
  if (!props.versionId) {
    changelog.value = []
    return
  }

  loading.value = true
  try {
    const params: any = {
      versionId: props.versionId,
    }
    if (filters.value.dateFrom) {
      params.dateFrom = filters.value.dateFrom
    }
    if (filters.value.dateTo) {
      params.dateTo = filters.value.dateTo
    }

    const response = await scheduleApi.getChanges(params)
    changelog.value = response.data
  } catch (error) {
    console.error('Failed to load changelog:', error)
  } finally {
    loading.value = false
  }
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('ru-RU')
}

const getActionColor = (action: string) => {
  const colors: Record<string, string> = {
    create: 'success',
    update: 'warning',
    delete: 'error',
    publish: 'info',
    archive: 'default',
  }
  return colors[action] || 'default'
}

const getActionText = (action: string) => {
  const texts: Record<string, string> = {
    create: 'Создано',
    update: 'Изменено',
    delete: 'Удалено',
    publish: 'Опубликовано',
    archive: 'Архивировано',
  }
  return texts[action] || action
}

const formatItemSummary = (item: ScheduleItemDTO) => {
  const parts = []
  if (item.date) parts.push(item.date)
  if (item.group?.name) parts.push(item.group.name)
  if (item.subject?.name) parts.push(item.subject.name)
  if (item.teacher?.fio) parts.push(item.teacher.fio)
  return parts.join(' - ')
}

watch(() => props.versionId, () => {
  loadChangelog()
}, { immediate: true })

onMounted(() => {
  loadChangelog()
})
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>


