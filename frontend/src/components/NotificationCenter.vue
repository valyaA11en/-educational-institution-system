<template>
  <div>
    <!-- Bell icon with badge -->
    <v-btn icon @click="toggleDrawer" variant="text">
      <v-badge
        v-if="unreadCount > 0"
        :content="unreadCount"
        color="error"
        overlap
      >
        <v-icon>mdi-bell</v-icon>
      </v-badge>
      <v-icon v-else>mdi-bell-outline</v-icon>
    </v-btn>

    <!-- Notification drawer -->
    <v-navigation-drawer
      v-model="open"
      location="right"
      temporary
      width="420"
    >
      <v-toolbar flat>
        <v-toolbar-title>Уведомления</v-toolbar-title>
        <v-spacer />
        <v-btn
          v-if="unreadCount > 0"
          size="small"
          variant="text"
          @click="onMarkAllRead"
        >
          Пометить все прочитанными
        </v-btn>
      </v-toolbar>

      <v-divider />

      <v-card flat>
        <v-card-text>
          <v-select
            v-model="filterType"
            :items="typeOptions"
            item-title="label"
            item-value="value"
            label="Фильтр по типу"
            density="compact"
            clearable
          />
        </v-card-text>

        <v-divider />

        <v-list density="comfortable">
          <v-list-item
            v-for="item in filtered"
            :key="item.id"
            :class="[{ 'opacity-60': item.readAt }, 'notification-item']"
            @click="onClickNotification(item)"
          >
            <template #prepend>
              <v-icon :color="item.readAt ? 'grey' : 'primary'">
                {{ iconFor(item.type) }}
              </v-icon>
            </template>

            <v-list-item-title>{{ titleFor(item) }}</v-list-item-title>
            <v-list-item-subtitle>
              {{ subtitleFor(item) }}
            </v-list-item-subtitle>

            <template #append>
              <v-btn
                v-if="!item.readAt"
                icon="mdi-check"
                variant="text"
                size="small"
                @click.stop="onMarkRead(item.id)"
              />
            </template>
          </v-list-item>

          <v-list-item v-if="!loading && filtered.length === 0">
            <v-list-item-title>Нет уведомлений</v-list-item-title>
          </v-list-item>

          <v-list-item v-if="loading">
            <v-progress-circular indeterminate size="24" />
            <v-list-item-title class="ml-2">Загрузка...</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-card>
    </v-navigation-drawer>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '../stores/notifications'

const open = ref(false)
const router = useRouter()
const notifications = useNotificationsStore()

const loading = computed(() => notifications.loading)
const unreadCount = computed(() => notifications.unreadCount)
const filterType = computed({
  get: () => notifications.filterType,
  set: (val: string | null) => {
    notifications.filterType = val
  },
})

const filtered = computed(() => notifications.filtered)

const typeOptions = computed(() => [
  { label: 'Все типы', value: null },
  ...notifications.types.map((t) => ({
    label: labelForType(t),
    value: t,
  })),
])

const toggleDrawer = () => {
  open.value = !open.value
  if (open.value && !notifications.items.length && !notifications.loading) {
    notifications.load()
  }
}

const onMarkRead = async (id: number) => {
  await notifications.markRead(id)
}

const onMarkAllRead = async () => {
  await notifications.markAllRead()
}

const onClickNotification = (item: (typeof notifications.items)[number]) => {
  goToNotification(item)
}

function labelForType(type: string): string {
  switch (type) {
    case 'grade.created':
      return 'Оценки'
    case 'assignment.due_soon':
      return 'Дедлайны'
    case 'schedule.changed':
      return 'Расписание'
    case 'document.status_changed':
      return 'Документы'
    default:
      return type
  }
}

function titleFor(item: (typeof notifications.items)[number]): string {
  return notifications.buildTitle(item)
}

function subtitleFor(item: (typeof notifications.items)[number]): string {
  const p = item.payload || {}
  switch (item.type) {
    case 'grade.created':
      return `Студент: ${p.student_name ?? p.student_id}, предмет: ${
        p.subject_name ?? p.subject_id ?? ''
      }`
    case 'assignment.due_soon':
      return p.title ?? `Задание #${p.assignment_id ?? item.id}`
    case 'schedule.changed':
      return p.description ?? `Группа: ${p.group_name ?? p.group_id ?? ''}`
    case 'document.status_changed':
      return p.title ?? `Документ #${p.document_id ?? item.id}`
    default:
      return ''
  }
}

function iconFor(type: string): string {
  switch (type) {
    case 'grade.created':
      return 'mdi-school'
    case 'assignment.due_soon':
      return 'mdi-calendar-clock'
    case 'schedule.changed':
      return 'mdi-calendar-sync'
    case 'document.status_changed':
      return 'mdi-file-document-outline'
    default:
      return 'mdi-bell'
  }
}

function goToNotification(item: (typeof notifications.items)[number]) {
  const p = item.payload || {}

  switch (item.type) {
    case 'grade.created': {
      const query: Record<string, string> = {}
      if (p.student_id) query.student = String(p.student_id)
      if (p.subject_id) query.subject = String(p.subject_id)
      router.push({ name: 'journal', query })
      break
    }
    case 'assignment.due_soon': {
      const id = p.assignment_id ?? item.id
      router.push({ name: 'tasks', params: { id: String(id) } })
      break
    }
    case 'schedule.changed': {
      const query: Record<string, string> = {}
      if (p.group_id) query.group = String(p.group_id)
      router.push({ name: 'schedule', query })
      break
    }
    case 'document.status_changed': {
      const id = p.document_id ?? item.id
      router.push({ name: 'documents', params: { id: String(id) } })
      break
    }
    default:
      router.push({ name: 'notifications' })
  }
}

onMounted(() => {
  // Ленивая загрузка: если уже открыт drawer, загрузим сразу
  if (open.value && !notifications.items.length) {
    notifications.load()
  }
})
</script>

<style scoped>
.notification-item {
  cursor: pointer;
}
</style>

