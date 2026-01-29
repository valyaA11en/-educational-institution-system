<template>
  <v-card>
    <v-card-title class="d-flex align-center">
      <v-icon class="mr-2">mdi-calendar-today</v-icon>
      Сегодня
    </v-card-title>
    <v-card-text>
      <div v-if="loading" class="text-center py-4">
        <v-progress-circular indeterminate></v-progress-circular>
      </div>
      <div v-else-if="items.length === 0" class="text-center py-4 text-medium-emphasis">
        Нет событий на сегодня
      </div>
      <v-list v-else density="compact">
        <v-list-item
          v-for="(item, index) in items"
          :key="index"
          :to="getItemUrl(item)"
          class="mb-1"
          style="cursor: pointer"
        >
          <template #prepend>
            <v-icon :color="getItemColor(item)">{{ getItemIcon(item) }}</v-icon>
          </template>
          <v-list-item-title>{{ getItemTitle(item) }}</v-list-item-title>
          <v-list-item-subtitle>{{ getItemSubtitle(item) }}</v-list-item-subtitle>
          <template #append>
            <v-chip v-if="isUrgent(item)" size="small" color="error">Срочно</v-chip>
            <v-chip v-else-if="isToday(item)" size="small" color="warning">Сегодня</v-chip>
          </template>
        </v-list-item>
      </v-list>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type {
  StudentTodayData,
  TeacherTodayData,
  CuratorTodayData,
  AdminTodayData,
} from '@/api/assistant'

interface Props {
  data: StudentTodayData | TeacherTodayData | CuratorTodayData | AdminTodayData
  role: string
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const items = computed(() => {
  if (props.role === 'student') {
    const studentData = props.data as StudentTodayData
    return [
      ...studentData.lessons_today.map((l) => ({ type: 'lesson', ...l })),
      ...studentData.deadlines_24h.map((d) => ({ type: 'deadline_24h', ...d })),
    ]
  } else if (props.role === 'teacher') {
    const teacherData = props.data as TeacherTodayData
    return teacherData.lessons_today.map((l) => ({ type: 'lesson', ...l }))
  } else if (props.role === 'curator') {
    // Для куратора показываем пустой список в "Сегодня"
    return []
  } else if (props.role === 'admin') {
    // Для админа показываем пустой список в "Сегодня"
    return []
  }
  return []
})

function getItemTitle(item: any): string {
  if (item.type === 'lesson') {
    return `${item.subject || 'Урок'} ${item.time ? `(${item.time})` : ''}`
  } else if (item.type === 'deadline_24h') {
    return item.title
  }
  return ''
}

function getItemSubtitle(item: any): string {
  if (item.type === 'lesson') {
    return `${item.room || ''} ${item.teacher ? `• ${item.teacher}` : ''}`.trim()
  } else if (item.type === 'deadline_24h') {
    const hours = item.hours_left
    return hours !== undefined ? `Осталось ${hours} ч.` : ''
  }
  return ''
}

function getItemIcon(item: any): string {
  if (item.type === 'lesson') return 'mdi-school'
  if (item.type === 'deadline_24h') return 'mdi-clock-alert'
  return 'mdi-circle'
}

function getItemColor(item: any): string {
  if (item.type === 'lesson') return 'primary'
  if (item.type === 'deadline_24h') return 'warning'
  return 'grey'
}

function isUrgent(item: any): boolean {
  if (item.type === 'deadline_24h') {
    return (item.hours_left ?? 0) < 6
  }
  return false
}

function isToday(item: any): boolean {
  return item.type === 'lesson' || item.type === 'deadline_24h'
}

function getItemUrl(item: any): string {
  if (item.type === 'lesson') {
    return `/schedule?date=${new Date().toISOString().split('T')[0]}`
  } else if (item.type === 'deadline_24h' && item.id) {
    return `/assignments/${item.id}`
  }
  return '#'
}
</script>

