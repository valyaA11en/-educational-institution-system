<template>
  <v-card>
    <v-card-title class="d-flex align-center">
      <v-icon class="mr-2" :color="highestRiskColor">mdi-alert-octagon</v-icon>
      Риски
      <v-spacer></v-spacer>
      <v-chip v-if="risks.length > 0" size="small" :color="highestRiskColor">
        {{ risks.length }}
      </v-chip>
    </v-card-title>
    <v-card-text>
      <div v-if="loading" class="text-center py-4">
        <v-progress-circular indeterminate></v-progress-circular>
      </div>
      <div v-else-if="risks.length === 0" class="text-center py-4 text-medium-emphasis">
        Рисков не обнаружено
      </div>
      <v-list v-else density="compact">
        <v-list-item
          v-for="(risk, index) in risks"
          :key="index"
          :to="getRiskUrl(risk)"
          class="mb-1"
          style="cursor: pointer"
        >
          <template #prepend>
            <v-icon :color="getRiskColor(risk.level)">{{ getRiskIcon(risk.level) }}</v-icon>
          </template>
          <v-list-item-title>{{ getRiskTitle(risk) }}</v-list-item-title>
          <v-list-item-subtitle>{{ getRiskSubtitle(risk) }}</v-list-item-subtitle>
          <template #append>
            <v-chip size="small" :color="getRiskColor(risk.level)">
              {{ risk.level.toUpperCase() }}
            </v-chip>
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
} from '@/api/assistant'

interface Props {
  data: StudentTodayData | TeacherTodayData | CuratorTodayData
  role: string
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
})

const risks = computed(() => {
  if (props.role === 'student') {
    const studentData = props.data as StudentTodayData
    return studentData.risks.map((r) => ({ ...r, type: 'risk' }))
  } else if (props.role === 'teacher') {
    const teacherData = props.data as TeacherTodayData
    return teacherData.students_with_red_risk.map((s) => ({
      ...s,
      level: 'red',
      type: 'student_risk',
    }))
  } else if (props.role === 'curator') {
    const curatorData = props.data as CuratorTodayData
    return curatorData.new_risks.map((r) => ({ ...r, type: 'new_risk' }))
  }
  return []
})

const highestRiskColor = computed(() => {
  if (risks.value.length === 0) return 'success'
  const hasRed = risks.value.some((r) => r.level === 'red')
  if (hasRed) return 'error'
  const hasYellow = risks.value.some((r) => r.level === 'yellow')
  if (hasYellow) return 'warning'
  return 'info'
})

function getRiskTitle(risk: any): string {
  if (risk.type === 'risk' || risk.type === 'new_risk') {
    return risk.student_fio || `Студент #${risk.student_id || risk.id}`
  } else if (risk.type === 'student_risk') {
    return risk.fio || `Студент #${risk.id}`
  }
  return ''
}

function getRiskSubtitle(risk: any): string {
  if (risk.type === 'risk' || risk.type === 'new_risk') {
    return `${risk.risk_type} • Балл: ${risk.score}`
  } else if (risk.type === 'student_risk') {
    return `${risk.risk_type} • Балл: ${risk.score}`
  }
  return ''
}

function getRiskIcon(level: string): string {
  if (level === 'red') return 'mdi-alert-octagon'
  if (level === 'yellow') return 'mdi-alert'
  return 'mdi-information'
}

function getRiskColor(level: string): string {
  if (level === 'red') return 'error'
  if (level === 'yellow') return 'warning'
  return 'info'
}

function getRiskUrl(risk: any): string {
  const studentId = risk.student_id || risk.id
  if (studentId) {
    return `/students/${studentId}/timeline`
  }
  return '/risks'
}
</script>

