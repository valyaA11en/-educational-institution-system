<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1>История обучения</h1>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>События</v-card-title>
          <v-card-text>
            <v-timeline v-if="timeline.length > 0">
              <v-timeline-item
                v-for="event in timeline"
                :key="event.id"
                :color="getEventColor(event.event_type)"
              >
                <template #opposite>
                  <span>{{ formatDate(event.event_date) }}</span>
                </template>
                <v-card>
                  <v-card-title>{{ event.title }}</v-card-title>
                  <v-card-text>
                    <p v-if="event.description">{{ event.description }}</p>
                    <v-chip v-if="event.payload" size="small" class="mt-2">
                      {{ event.event_type }}
                    </v-chip>
                  </v-card-text>
                </v-card>
              </v-timeline-item>
            </v-timeline>
            <v-alert v-else type="info">Нет событий</v-alert>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { timelineApi, type TimelineEvent } from '@/api/timeline'

const timeline = ref<TimelineEvent[]>([])

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

onMounted(async () => {
  try {
    const studentId = auth.user?.id
    if (!studentId) return
    
    const response = await timelineApi.getTimeline(studentId)
    timeline.value = response.data.data
  } catch (error) {
    console.error('Failed to load timeline:', error)
  }
})

function formatDate(date: string) {
  return new Date(date).toLocaleDateString('ru-RU')
}

function getEventColor(eventType: string): string {
  const colors: Record<string, string> = {
    'grade.created': 'green',
    'assignment.submitted': 'blue',
    'assignment.assigned': 'orange',
    'lesson.attended': 'purple',
  }
  return colors[eventType] || 'grey'
}

</script>

