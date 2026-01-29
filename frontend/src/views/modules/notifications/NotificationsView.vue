<template>
  <v-card>
    <v-card-title class="d-flex flex-wrap align-center gap-2">
      <span>Уведомления</span>
      <v-spacer />
      <v-select
        v-model="filterType"
        :items="typeOptions"
        item-title="label"
        item-value="value"
        label="Тип"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="shrink"
        style="max-width: 200px"
      />
      <v-btn
        color="primary"
        prepend-icon="mdi-check-all"
        :loading="loading"
        :disabled="unreadCount === 0"
        @click="markAllRead"
      >
        Прочитать все
      </v-btn>
      <v-btn
        color="primary"
        variant="tonal"
        prepend-icon="mdi-refresh"
        :loading="loading"
        @click="load"
      >
        Обновить
      </v-btn>
    </v-card-title>

    <v-card-text>
      <div v-if="loading && items.length === 0" class="text-center py-8">
        <v-progress-circular indeterminate color="primary" />
      </div>
      <div v-else-if="filtered.length === 0" class="text-center py-8 text-medium-emphasis">
        <p>{{ filterType ? 'Нет уведомлений выбранного типа.' : 'Нет уведомлений.' }}</p>
      </div>
      <v-list v-else density="compact" class="bg-transparent">
        <v-list-item
          v-for="n in filtered"
          :key="n.id"
          :class="{ 'bg-primary-container': !n.readAt && n.status !== 'read' }"
          class="rounded-lg mb-2"
        >
          <template #prepend>
            <v-icon :icon="iconForType(n.type)" size="small" class="mr-2" />
          </template>
          <v-list-item-title>{{ titleForType(n.type) }}</v-list-item-title>
          <v-list-item-subtitle v-if="n.payload?.message || n.payload?.title">
            {{ n.payload?.message || n.payload?.title }}
          </v-list-item-subtitle>
          <v-list-item-subtitle v-else class="text-caption">
            {{ formatDate(n.createdAt) }}
          </v-list-item-subtitle>
          <template #append>
            <div class="d-flex align-center gap-1">
              <v-btn
                v-if="!n.readAt && n.status !== 'read'"
                icon="mdi-check"
                variant="text"
                size="small"
                @click="markRead(n.id)"
              />
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                color="error"
                @click="remove(n.id)"
              />
            </div>
          </template>
        </v-list-item>
      </v-list>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useNotificationsStore } from '../../../stores/notifications'

const store = useNotificationsStore()
const { items, loading, unreadCount, types } = storeToRefs(store)
const filterType = ref<string | null>(null)

const typeOptions = computed(() => {
  const opts = (types.value || []).map((t) => ({ value: t, label: titleForType(t) }))
  return opts
})

const filtered = computed(() => {
  const list = items.value ?? []
  if (!filterType.value) return list
  return list.filter((n) => n.type === filterType.value)
})

function titleForType(type: string): string {
  const map: Record<string, string> = {
    'grade.created': 'Новая оценка',
    'assignment.due_soon': 'Скоро дедлайн задания',
    'schedule.changed': 'Изменение расписания',
    'document.status_changed': 'Изменение статуса документа',
  }
  return map[type] ?? type || 'Уведомление'
}

function iconForType(type: string): string {
  const map: Record<string, string> = {
    'grade.created': 'mdi-numeric',
    'assignment.due_soon': 'mdi-calendar-clock',
    'schedule.changed': 'mdi-calendar-sync',
    'document.status_changed': 'mdi-file-document-outline',
  }
  return map[type] ?? 'mdi-bell-outline'
}

function formatDate(s: string): string {
  if (!s) return ''
  const d = new Date(s)
  return d.toLocaleString('ru-RU', {
    dateStyle: 'short',
    timeStyle: 'short',
  })
}

async function load() {
  await store.load()
}

async function markRead(id: number) {
  await store.markRead(id)
}

async function markAllRead() {
  await store.markAllRead()
}

async function remove(id: number) {
  await store.delete(id)
}

onMounted(() => {
  load()
})
</script>
