<template>
  <v-card>
    <v-card-title class="d-flex align-center">
      <v-icon class="mr-2" color="warning">mdi-alert</v-icon>
      Требует внимания
      <v-spacer></v-spacer>
      <v-chip v-if="count > 0" size="small" color="error">{{ count }}</v-chip>
    </v-card-title>
    <v-card-text>
      <div v-if="loading" class="text-center py-4">
        <v-progress-circular indeterminate></v-progress-circular>
      </div>
      <div v-else-if="items.length === 0" class="text-center py-4 text-medium-emphasis">
        Все в порядке
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
            <v-chip v-if="isOverdue(item)" size="small" color="error">Просрочено</v-chip>
            <v-chip v-else-if="isUrgent(item)" size="small" color="warning">Срочно</v-chip>
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
      ...studentData.overdue_assignments.map((a) => ({ type: 'overdue', ...a })),
      ...studentData.deadlines_3d.map((d) => ({ type: 'deadline_3d', ...d })),
    ]
  } else if (props.role === 'teacher') {
    const teacherData = props.data as TeacherTodayData
    return [
      ...teacherData.unchecked_submissions.map((s) => ({ type: 'unchecked', ...s })),
      ...teacherData.lessons_without_grades.map((l) => ({ type: 'no_grade', ...l })),
    ]
  } else if (props.role === 'curator') {
    const curatorData = props.data as CuratorTodayData
    return [
      ...curatorData.group_overdue_assignments.map((g) => ({ type: 'group_overdue', ...g })),
      ...curatorData.low_average_students.map((s) => ({ type: 'low_avg', ...s })),
    ]
  } else if (props.role === 'admin') {
    const adminData = props.data as AdminTodayData
    return [
      ...adminData.documents_pending_approval.map((d) => ({ type: 'pending_doc', ...d })),
      ...adminData.tickets_sla_overdue.map((t) => ({ type: 'overdue_ticket', ...t })),
    ]
  }
  return []
})

const count = computed(() => items.value.length)

function getItemTitle(item: any): string {
  if (item.type === 'overdue') return item.title
  if (item.type === 'deadline_3d') return item.title
  if (item.type === 'unchecked') return item.title
  if (item.type === 'no_grade') return `${item.subject || 'Урок'} - ${item.topic || ''}`
  if (item.type === 'group_overdue') return `Группа: ${item.count} заданий`
  if (item.type === 'low_avg') return item.fio || `Студент #${item.id}`
  if (item.type === 'pending_doc') return `${item.type} #${item.number}`
  if (item.type === 'overdue_ticket') return item.title
  return ''
}

function getItemSubtitle(item: any): string {
  if (item.type === 'overdue') {
    return `Просрочено на ${item.days_overdue} дн. • ${item.subject || ''}`
  }
  if (item.type === 'deadline_3d') {
    return `Осталось ${item.days_left} дн. • ${item.subject || ''}`
  }
  if (item.type === 'unchecked') {
    return item.subject || ''
  }
  if (item.type === 'no_grade') {
    return `${item.date} • ${item.group || ''}`
  }
  if (item.type === 'group_overdue') {
    return item.assignments.map((a: any) => a.title).join(', ')
  }
  if (item.type === 'low_avg') {
    return `Средний балл: ${item.average_grade}`
  }
  if (item.type === 'pending_doc') {
    return `Создан: ${item.created_by || ''} • ${item.pending_routes} маршрутов`
  }
  if (item.type === 'overdue_ticket') {
    return `Приоритет: ${item.priority} • ${item.created_by || ''}`
  }
  return ''
}

function getItemIcon(item: any): string {
  const icons: Record<string, string> = {
    overdue: 'mdi-alert-circle',
    deadline_3d: 'mdi-clock-outline',
    unchecked: 'mdi-file-check-outline',
    no_grade: 'mdi-school-outline',
    group_overdue: 'mdi-account-group',
    low_avg: 'mdi-chart-line',
    pending_doc: 'mdi-file-document-outline',
    overdue_ticket: 'mdi-ticket-outline',
  }
  return icons[item.type] || 'mdi-alert'
}

function getItemColor(item: any): string {
  if (item.type === 'overdue' || item.type === 'overdue_ticket') return 'error'
  if (item.type === 'deadline_3d' || item.type === 'unchecked') return 'warning'
  return 'info'
}

function isOverdue(item: any): boolean {
  return item.type === 'overdue' || item.type === 'overdue_ticket'
}

function isUrgent(item: any): boolean {
  if (item.type === 'deadline_3d') {
    return (item.days_left ?? 0) <= 1
  }
  return false
}

function getItemUrl(item: any): string {
  if (item.type === 'overdue' && item.id) {
    return `/assignments/${item.id}`
  }
  if (item.type === 'deadline_3d' && item.id) {
    return `/assignments/${item.id}`
  }
  if (item.type === 'unchecked' && item.id) {
    return `/assignments/${item.id}`
  }
  if (item.type === 'no_grade' && item.id) {
    return `/journal?lesson_id=${item.id}`
  }
  if (item.type === 'group_overdue' && item.group_id) {
    return `/groups/${item.group_id}`
  }
  if (item.type === 'low_avg' && item.id) {
    return `/students/${item.id}/timeline`
  }
  if (item.type === 'pending_doc' && item.id) {
    return `/documents/${item.id}`
  }
  if (item.type === 'overdue_ticket' && item.id) {
    return `/tickets/${item.id}`
  }
  return '#'
}
</script>

