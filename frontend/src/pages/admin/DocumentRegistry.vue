<template>
  <div>
    <v-card>
      <v-card-title>
        <span class="text-h5">Реестр документов</span>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.type"
              :items="typeOptions"
              label="Тип"
              clearable
              variant="outlined"
              density="compact"
              @update:model-value="loadRegistry"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.year"
              label="Год"
              type="number"
              variant="outlined"
              density="compact"
              @update:model-value="loadRegistry"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="registry"
          :loading="loading"
        >
          <template v-slot:item.last_number="{ item }">
            {{ item.last_number }}
          </template>
          <template v-slot:item.updated_at="{ item }">
            {{ formatDateTime(item.updated_at) }}
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '../../api/client'

const loading = ref(false)
const registry = ref<any[]>([])
const filters = ref({
  type: null as string | null,
  year: new Date().getFullYear(),
})

const typeOptions = [
  { title: 'Приказ', value: 'order' },
  { title: 'Решение', value: 'decision' },
  { title: 'Служебная записка', value: 'memo' },
  { title: 'Протокол', value: 'protocol' },
  { title: 'Заявление', value: 'statement' },
  { title: 'Ведомость', value: 'grade_sheet' },
]

const headers = [
  { title: 'Тип', key: 'type' },
  { title: 'Год', key: 'year' },
  { title: 'Последний номер', key: 'last_number' },
  { title: 'Обновлено', key: 'updated_at' },
]

const loadRegistry = async () => {
  loading.value = true
  try {
    const params: any = {}
    if (filters.value.type) params.type = filters.value.type
    if (filters.value.year) params.year = filters.value.year

    const response = await apiClient.get('/v1/documents/registry', { params })
    registry.value = response.data
  } catch (error) {
    console.error('Failed to load registry:', error)
  } finally {
    loading.value = false
  }
}

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('ru-RU')
}

onMounted(() => {
  loadRegistry()
})
</script>


