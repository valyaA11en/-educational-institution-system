<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1>Что делать сегодня</h1>
        <v-alert v-if="urgentCount > 0" type="error" class="mb-4">
          Срочных задач: {{ urgentCount }}
        </v-alert>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12" md="6" v-for="task in tasks" :key="task.type + task.entity_id">
        <v-card :color="getPriorityColor(task.priority)">
          <v-card-title>{{ task.title }}</v-card-title>
          <v-card-text>
            <p>{{ task.description }}</p>
            <p v-if="task.due_at" class="text-caption">
              До: {{ formatDateTime(task.due_at) }}
            </p>
          </v-card-text>
          <v-card-actions>
            <v-btn :to="task.action_url" color="primary">Перейти</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
    <v-row v-if="tasks.length === 0">
      <v-col cols="12">
        <v-alert type="success">Нет задач на сегодня!</v-alert>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { assistantApi, type Task } from '@/api/assistant'

const tasks = ref<Task[]>([])
const urgentCount = ref(0)

onMounted(async () => {
  try {
    const response = await assistantApi.getTodayTasks()
    tasks.value = response.data.data
    urgentCount.value = response.data.urgent_count
  } catch (error) {
    console.error('Failed to load tasks:', error)
  }
})

function formatDateTime(dateTime: string) {
  return new Date(dateTime).toLocaleString('ru-RU')
}

function getPriorityColor(priority: string): string {
  const colors: Record<string, string> = {
    urgent: 'error',
    high: 'warning',
    medium: 'info',
    low: 'success',
  }
  return colors[priority] || 'grey'
}
</script>

