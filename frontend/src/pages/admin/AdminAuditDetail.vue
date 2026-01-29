<template>
  <div>
    <v-btn
      icon="mdi-arrow-left"
      variant="text"
      @click="$router.push({ name: 'admin-audit' })"
      class="mb-4"
    >
    </v-btn>

    <v-card v-if="loading">
      <v-card-text>
        <v-skeleton-loader type="article, article"></v-skeleton-loader>
      </v-card-text>
    </v-card>

    <v-card v-else-if="log">
      <v-card-title>Запись аудита #{{ log.id }}</v-card-title>

      <v-card-text>
        <!-- Basic Info -->
        <v-row>
          <v-col cols="12" md="6">
            <v-list-item>
              <v-list-item-title>Дата</v-list-item-title>
              <v-list-item-subtitle>{{ formatDate(log.created_at) }}</v-list-item-subtitle>
            </v-list-item>
          </v-col>
          <v-col cols="12" md="6">
            <v-list-item>
              <v-list-item-title>Пользователь</v-list-item-title>
              <v-list-item-subtitle>
                {{ log.user_fio || '-' }}
                <span v-if="log.user_email"> ({{ log.user_email }})</span>
              </v-list-item-subtitle>
            </v-list-item>
          </v-col>
          <v-col cols="12" md="6">
            <v-list-item>
              <v-list-item-title>Действие</v-list-item-title>
              <v-list-item-subtitle>{{ log.action }}</v-list-item-subtitle>
            </v-list-item>
          </v-col>
          <v-col cols="12" md="6">
            <v-list-item>
              <v-list-item-title>Сущность</v-list-item-title>
              <v-list-item-subtitle>{{ log.entity }}</v-list-item-subtitle>
            </v-list-item>
          </v-col>
          <v-col cols="12" md="6" v-if="log.entity_id">
            <v-list-item>
              <v-list-item-title>ID сущности</v-list-item-title>
              <v-list-item-subtitle>
                <v-btn
                  variant="text"
                  size="small"
                  color="primary"
                  @click="goToEntity(log.entity, log.entity_id!)"
                >
                  {{ log.entity_id }}
                </v-btn>
              </v-list-item-subtitle>
            </v-list-item>
          </v-col>
          <v-col cols="12" md="6" v-if="log.ip">
            <v-list-item>
              <v-list-item-title>IP адрес</v-list-item-title>
              <v-list-item-subtitle>{{ log.ip }}</v-list-item-subtitle>
            </v-list-item>
          </v-col>
        </v-row>

        <v-divider class="my-4" />

        <!-- JSON Diff -->
        <v-row>
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="bg-red-lighten-5">До (Before)</v-card-title>
              <v-card-text>
                <pre v-if="log.before_json" class="json-viewer">{{ formatJson(log.before_json) }}</pre>
                <span v-else class="text-grey">Нет данных</span>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="6">
            <v-card variant="outlined">
              <v-card-title class="bg-green-lighten-5">После (After)</v-card-title>
              <v-card-text>
                <pre v-if="log.after_json" class="json-viewer">{{ formatJson(log.after_json) }}</pre>
                <span v-else class="text-grey">Нет данных</span>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Diff Highlight (simple version - showing changed fields) -->
        <v-card v-if="log.before_json && log.after_json" variant="outlined" class="mt-4">
          <v-card-title>Изменения</v-card-title>
          <v-card-text>
            <v-list>
              <v-list-item
                v-for="(value, key) in getChangedFields(log.before_json, log.after_json)"
                :key="key"
              >
                <v-list-item-title>{{ key }}</v-list-item-title>
                <v-list-item-subtitle>
                  <span class="text-red">Было: {{ formatValue(value.old) }}</span>
                  <br />
                  <span class="text-green">Стало: {{ formatValue(value.new) }}</span>
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auditApi, type AuditLogDTO } from '@/api/audit'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const log = ref<AuditLogDTO | null>(null)

const loadData = async () => {
  loading.value = true
  try {
    const id = Number(route.params.id)
    log.value = await auditApi.get(id)
  } catch (error) {
    console.error('Failed to load audit log:', error)
  } finally {
    loading.value = false
  }
}

const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

const formatJson = (obj: Record<string, any>): string => {
  return JSON.stringify(obj, null, 2)
}

const formatValue = (value: any): string => {
  if (value === null || value === undefined) {
    return 'null'
  }
  if (typeof value === 'object') {
    return JSON.stringify(value)
  }
  return String(value)
}

const getChangedFields = (
  before: Record<string, any>,
  after: Record<string, any>
): Record<string, { old: any; new: any }> => {
  const changes: Record<string, { old: any; new: any }> = {}
  const allKeys = new Set([...Object.keys(before), ...Object.keys(after)])

  for (const key of allKeys) {
    const beforeValue = before[key]
    const afterValue = after[key]

    if (JSON.stringify(beforeValue) !== JSON.stringify(afterValue)) {
      changes[key] = { old: beforeValue, new: afterValue }
    }
  }

  return changes
}

const goToEntity = (entity: string, entityId: number) => {
  // Map entity types to routes
  const entityRoutes: Record<string, string> = {
    'App\\Models\\User': 'admin-users',
    'App\\Models\\Group': 'admin-groups',
    'App\\Models\\Subject': 'admin-subjects',
    'App\\Models\\Document': 'document-view',
    'App\\Models\\Assignment': 'tasks', // TODO: add assignment detail route
    'App\\Models\\Lesson': 'lesson-journal',
  }

  const routeName = entityRoutes[entity]
  if (routeName) {
    router.push({ name: routeName, params: { id: entityId } })
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.json-viewer {
  background-color: #f5f5f5;
  padding: 16px;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 12px;
  overflow-x: auto;
  max-height: 600px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>


