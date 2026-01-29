<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 mb-4">Панель управления</h1>
        <p class="text-subtitle-1 mb-6">
          Добро пожаловать, {{ auth.user?.fio || 'Пользователь' }}!
        </p>
      </v-col>
    </v-row>

    <v-row v-if="loading">
      <v-col cols="12" class="text-center">
        <v-progress-circular indeterminate size="64"></v-progress-circular>
      </v-col>
    </v-row>

    <v-row v-else>
      <!-- Виджет "Сегодня" -->
      <v-col v-if="todayData" cols="12" md="4">
        <TodayWidget :data="todayData!" :role="userRole" :loading="loading" />
      </v-col>

      <!-- Виджет "Требует внимания" -->
      <v-col v-if="todayData" cols="12" md="4">
        <AttentionWidget :data="todayData!" :role="userRole" :loading="loading" />
      </v-col>

      <!-- Виджет "Риски" (только для student, teacher, curator) -->
      <v-col v-if="risksData" cols="12" md="4">
        <RisksWidget :data="risksData!" :role="userRole" :loading="loading" />
      </v-col>

      <!-- Дополнительные виджеты для админа -->
      <template v-if="userRole === 'admin'">
        <v-col cols="12" md="4">
          <v-card>
            <v-card-title class="d-flex align-center">
              <v-icon class="mr-2" color="error">mdi-alert-circle</v-icon>
              Конфликты расписания
              <v-spacer></v-spacer>
              <v-chip
                v-if="(adminData?.schedule_conflicts?.length ?? 0) > 0"
                size="small"
                color="error"
              >
                {{ adminData?.schedule_conflicts?.length ?? 0 }}
              </v-chip>
            </v-card-title>
            <v-card-text>
              <div
                v-if="(adminData?.schedule_conflicts?.length ?? 0) === 0"
                class="text-center py-4 text-medium-emphasis"
              >
                Конфликтов не обнаружено
              </div>
              <v-list v-else density="compact">
                <v-list-item
                  v-for="(conflict, index) in adminData?.schedule_conflicts || []"
                  :key="index"
                  :to="`/schedule?date=${conflict.date}`"
                  style="cursor: pointer"
                >
                  <v-list-item-title>
                    Конфликт {{ conflict.type === 'teacher' ? 'преподавателя' : 'аудитории' }}
                  </v-list-item-title>
                  <v-list-item-subtitle>{{ conflict.date }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="4">
          <v-card>
            <v-card-title class="d-flex align-center">
              <v-icon class="mr-2" color="info">mdi-bell</v-icon>
              Системные уведомления
            </v-card-title>
            <v-card-text>
              <div
                v-if="(adminData?.system_alerts?.length ?? 0) === 0"
                class="text-center py-4 text-medium-emphasis"
              >
                Все в порядке
              </div>
              <v-list v-else density="compact">
                <v-list-item
                  v-for="(alert, index) in adminData?.system_alerts || []"
                  :key="index"
                >
                  <template #prepend>
                    <v-icon :color="getAlertColor(alert.severity)">
                      {{ getAlertIcon(alert.severity) }}
                    </v-icon>
                  </template>
                  <v-list-item-title>{{ alert.message }}</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>
      </template>
    </v-row>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import {
  assistantApi,
  type TodayData,
  type AdminTodayData,
  type StudentTodayData,
  type TeacherTodayData,
  type CuratorTodayData,
} from '../api/assistant'
import TodayWidget from '../components/DashboardWidgets/TodayWidget.vue'
import AttentionWidget from '../components/DashboardWidgets/AttentionWidget.vue'
import RisksWidget from '../components/DashboardWidgets/RisksWidget.vue'

const auth = useAuthStore()
const loading = ref(false)
const todayData = ref<TodayData | null>(null)
const userRole = ref<string>('unknown')

const adminData = computed<AdminTodayData | null>(() => {
  if (userRole.value === 'admin' && todayData.value) {
    return todayData.value as AdminTodayData
  }
  return null
})

const risksData = computed<
  StudentTodayData | TeacherTodayData | CuratorTodayData | null
>(() => {
  if (!todayData.value) return null
  if (['student', 'teacher', 'curator'].includes(userRole.value)) {
    return todayData.value as
      | StudentTodayData
      | TeacherTodayData
      | CuratorTodayData
  }
  return null
})

onMounted(async () => {
  await loadTodayData()
})

async function loadTodayData() {
  loading.value = true
  try {
    const response = await assistantApi.getToday()
    todayData.value = response.data.data
    userRole.value = response.data.role
  } catch (error) {
    console.error('Failed to load today data:', error)
  } finally {
    loading.value = false
  }
}

function getAlertColor(severity: string): string {
  if (severity === 'high') return 'error'
  if (severity === 'medium') return 'warning'
  return 'info'
}

function getAlertIcon(severity: string): string {
  if (severity === 'high') return 'mdi-alert-octagon'
  if (severity === 'medium') return 'mdi-alert'
  return 'mdi-information'
}
</script>









