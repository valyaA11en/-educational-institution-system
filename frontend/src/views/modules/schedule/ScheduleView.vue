<template>
  <v-card>
    <v-card-title class="d-flex align-center gap-2">
      <span>Расписание</span>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="load">
        Обновить
      </v-btn>
    </v-card-title>
    <v-card-text>
      <div v-if="loading && versions.length === 0" class="text-center py-8">
        <v-progress-circular indeterminate color="primary" />
      </div>
      <div v-else-if="versions.length === 0" class="text-center py-8 text-medium-emphasis">
        <p>Нет версий расписания.</p>
      </div>
      <v-table v-else density="compact">
        <thead>
          <tr>
            <th class="text-left">ID</th>
            <th class="text-left">Статус</th>
            <th class="text-left">Создано</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in versions" :key="v.id">
            <td>{{ v.id }}</td>
            <td>
              <v-chip size="small" :color="v.status === 'published' ? 'success' : 'default'">
                {{ v.status }}
              </v-chip>
            </td>
            <td>{{ formatDate(v.created_at) }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { scheduleApi, type ScheduleVersionDTO } from '../../../api/schedule'

const loading = ref(false)
const versions = ref<ScheduleVersionDTO[]>([])

function formatDate(s: string) {
  if (!s) return '—'
  return new Date(s).toLocaleString('ru-RU', { dateStyle: 'short' })
}

async function load() {
  loading.value = true
  try {
    const res = await scheduleApi.getVersions({ per_page: 50 })
    versions.value = res?.data ?? []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>
