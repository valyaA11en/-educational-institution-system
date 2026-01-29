<template>
  <v-layout class="h-100">
    <!-- Read-only Banner -->
    <ReadOnlyBanner />
    
    <!-- Topbar -->
    <v-app-bar app color="primary" dark>
      <v-app-bar-nav-icon @click="drawer = !drawer" />
      <v-toolbar-title>PDO</v-toolbar-title>
      <v-spacer />
      <TenantSelector />
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
          <v-list-item :to="{ name: 'settings-security' }">
            <v-list-item-title>Безопасность</v-list-item-title>
            <v-list-item-prepend>
              <v-icon>mdi-shield-lock</v-icon>
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
import TenantSelector from '../components/TenantSelector.vue'
import ReadOnlyBanner from '../components/ReadOnlyBanner.vue'
import { getMenuItemsForRole, canSeeMenuItem } from '../config/menu'

const drawer = ref(true)

const auth = useAuthStore()
const ws = useWsStore()
const router = useRouter()

const menuItems = computed(() => {
  if (!auth.isAuthenticated || !auth.user) {
    return []
  }

  // Get user roles
  const userRoles = auth.roles.map(r => r.name)
  const userPermissions = auth.permissions.map(p => p.code)

  // Get menu items for all user roles and merge them
  const allMenuItems: Array<{ to: any; title: string; icon: string; divider?: boolean }> = []
  const seenRoutes = new Set<string>()

  // Process each role the user has
  for (const roleName of userRoles) {
    const roleMenuItems = getMenuItemsForRole(roleName)
    
    for (const item of roleMenuItems) {
      // Create a unique key for the route
      const routeKey = item.to ? (item.to.name || JSON.stringify(item.to)) : item.title
      
      // Skip if we've already added this route (avoid duplicates)
      if (seenRoutes.has(routeKey)) {
        continue
      }

      // Check if user can see this menu item based on permissions
      if (canSeeMenuItem(item, userRoles, userPermissions)) {
        allMenuItems.push({
          to: item.to,
          title: item.title,
          icon: item.icon,
          divider: item.divider,
        })
        seenRoutes.add(routeKey)
      }
    }
  }

  // If user has no roles, show common items only
  if (allMenuItems.length === 0) {
    const commonItems = getMenuItemsForRole('')
    return commonItems
      .filter(item => canSeeMenuItem(item, userRoles, userPermissions))
      .map(item => ({
        to: item.to,
        title: item.title,
        icon: item.icon,
        divider: item.divider,
      }))
      .filter(item => item.to !== null)
  }

  // Filter out null routes and return
  return allMenuItems.filter(item => item.to !== null)
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


