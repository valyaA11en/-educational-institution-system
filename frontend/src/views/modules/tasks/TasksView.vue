<template>
  <v-card>
    <v-card-title class="d-flex align-center gap-2">
      <span>Задания</span>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="load">
        Обновить
      </v-btn>
    </v-card-title>
    <v-card-text>
      <div v-if="loading && items.length === 0" class="text-center py-8">
        <v-progress-circular indeterminate color="primary" />
      </div>
      <div v-else-if="items.length === 0" class="text-center py-8 text-medium-emphasis">
        <p>Нет заданий.</p>
      </div>
      <v-table v-else density="compact">
        <thead>
          <tr>
            <th class="text-left">Название</th>
            <th class="text-left">Срок</th>
            <th class="text-left">Доступ</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in items" :key="a.id">
            <td>{{ a.title }}</td>
            <td>{{ a.due_at ? formatDate(a.due_at) : '—' }}</td>
            <td>
              <v-chip size="small" variant="tonal">{{ a.visibility_scope }}</v-chip>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { assignmentsApi, type AssignmentDTO } from '../../../api/assignments'

const loading = ref(false)
const items = ref<AssignmentDTO[]>([])

function formatDate(s: string) {
  if (!s) return '—'
  return new Date(s).toLocaleDateString('ru-RU', { dateStyle: 'short' })
}

async function load() {
  loading.value = true
  try {
    items.value = await assignmentsApi.list()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>
