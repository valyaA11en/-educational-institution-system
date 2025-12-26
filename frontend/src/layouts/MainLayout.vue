<template>
  <v-layout class="h-100">
    <!-- Topbar -->
    <v-app-bar app color="primary" dark>
      <v-app-bar-nav-icon @click="drawer = !drawer" />
      <v-toolbar-title>PDO</v-toolbar-title>
      <v-spacer />
      <v-chip v-if="ws.connected" color="success" size="small" class="mr-2">
        <v-icon start size="small">mdi-circle</v-icon>
        WS подключен
      </v-chip>
      <NotificationCenter class="mr-2" />
      <v-menu>
        <template v-slot:activator="{ props }">
          <v-btn icon="mdi-account-circle" v-bind="props" />
        </template>
        <v-list>
          <v-list-item>
            <v-list-item-title>{{ auth.user?.fio || 'Пользователь' }}</v-list-item-title>
            <v-list-item-subtitle>{{ auth.user?.email || auth.user?.phone || '' }}</v-list-item-subtitle>
          </v-list-item>
          <v-divider />
          <v-list-item :to="{ name: 'settings-notifications' }">
            <v-list-item-title>Настройки уведомлений</v-list-item-title>
            <v-list-item-prepend>
              <v-icon>mdi-bell-cog</v-icon>
            </v-list-item-prepend>
          </v-list-item>
          <v-divider />
          <v-list-item @click="onLogout">
            <v-list-item-title>Выйти</v-list-item-title>
            <v-list-item-prepend>
              <v-icon>mdi-logout</v-icon>
            </v-list-item-prepend>
          </v-list-item>
        </v-list>
      </v-menu>
    </v-app-bar>

    <!-- Sidebar -->
    <v-navigation-drawer v-model="drawer" app>
      <v-list density="compact" nav>
        <template v-for="(item, index) in menuItems" :key="index">
          <v-divider v-if="item.divider" class="my-2" />
          <v-list-item
            v-else
            :to="item.to"
            :title="item.title"
            :prepend-icon="item.icon"
            link
          />
        </template>
      </v-list>
    </v-navigation-drawer>

    <!-- Main Content Slot -->
    <v-main>
      <v-container fluid class="pa-4">
        <router-view />
      </v-container>
    </v-main>
  </v-layout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useWsStore } from '../stores/ws'
import { useRouter } from 'vue-router'
import NotificationCenter from '../components/NotificationCenter.vue'

const drawer = ref(true)

const auth = useAuthStore()
const ws = useWsStore()
const router = useRouter()

const menuItems = computed(() => {
  const items: Array<{ to: any; title: string; icon: string; divider?: boolean }> = [
    { to: { name: 'dashboard' }, title: 'Панель управления', icon: 'mdi-view-dashboard' },
    { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar' },
    { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant' },
    { to: { name: 'tasks' }, title: 'Задания', icon: 'mdi-clipboard-text' },
    { to: { name: 'materials' }, title: 'Материалы', icon: 'mdi-file-document-multiple' },
    { to: { name: 'notifications' }, title: 'Уведомления', icon: 'mdi-bell' },
    { to: { name: 'chat' }, title: 'Чаты', icon: 'mdi-forum' },
    { to: { name: 'tickets' }, title: 'Тикеты', icon: 'mdi-ticket' },
    { to: { name: 'exams' }, title: 'Экзамены', icon: 'mdi-school' },
    { to: { name: 'contests' }, title: 'Конкурсы', icon: 'mdi-trophy' },
  ]

  // Analytics/Risks (visible if has analytics.view permission or is student)
  if (auth.hasPermission('analytics.view')) {
    items.push({ to: { name: 'analytics-risks' }, title: 'Риски студентов', icon: 'mdi-alert-circle' })
  } else {
    // Student can see their own risks
    items.push({ to: { name: 'my-risks' }, title: 'Мои риски', icon: 'mdi-alert-circle' })
  }

  // Documents (visible if has documents.view permission)
  if (auth.hasPermission('documents.view')) {
    items.push({ to: { name: 'documents' }, title: 'Документы', icon: 'mdi-file-document' })
  }

  // KTP (visible if has curriculum.view permission)
  if (auth.hasPermission('curriculum.view')) {
    items.push({ to: { name: 'ktp' }, title: 'КТП', icon: 'mdi-calendar-text' })
  }

  // Admin menu items (visible if has directory.manage or users.read permission)
  if (auth.hasPermission('directory.manage') || auth.hasPermission('users.read')) {
    items.push({ to: null, title: 'Админка', icon: 'mdi-cog', divider: true })
    
    if (auth.hasPermission('directory.manage')) {
      items.push({ to: { name: 'admin-groups' }, title: 'Группы', icon: 'mdi-account-group' })
      items.push({ to: { name: 'admin-subgroups' }, title: 'Подгруппы', icon: 'mdi-account-group-outline' })
      items.push({ to: { name: 'admin-subjects' }, title: 'Предметы', icon: 'mdi-book-open-variant' })
      items.push({ to: { name: 'admin-rooms' }, title: 'Кабинеты', icon: 'mdi-door' })
      items.push({ to: { name: 'admin-time-slots' }, title: 'Слоты пар', icon: 'mdi-clock-outline' })
    }
    
    if (auth.hasPermission('users.read')) {
      items.push({ to: { name: 'admin-users' }, title: 'Пользователи', icon: 'mdi-account-multiple' })
    }
    
    if (auth.hasPermission('rules.manage')) {
      items.push({ to: { name: 'admin-rules' }, title: 'Правила', icon: 'mdi-auto-fix' })
    }
    
    if (auth.hasPermission('tickets.manage')) {
      items.push({ to: { name: 'admin-tickets-overdue' }, title: 'Просроченные тикеты', icon: 'mdi-alert-circle' })
    }
    
    if (auth.hasPermission('documents.registry')) {
      items.push({ to: { name: 'admin-document-templates' }, title: 'Шаблоны документов', icon: 'mdi-file-document-edit' })
      items.push({ to: { name: 'admin-document-registry' }, title: 'Реестр документов', icon: 'mdi-book-open-variant' })
    }
    
    if (auth.hasPermission('chat.moderate')) {
      items.push({ to: { name: 'admin-chat-reports' }, title: 'Жалобы на сообщения', icon: 'mdi-alert-circle' })
    }
  }

  return items.filter(item => item.to !== null)
})

const onLogout = async () => {
  ws.disconnect()
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.h-100 {
  height: 100%;
}
</style>


