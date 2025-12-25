import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../pages/Login.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/',
    component: () => import('../layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('../pages/Dashboard.vue'),
      },
      {
        path: 'schedule',
        name: 'schedule',
        component: () => import('../pages/Schedule.vue'),
        meta: { module: 'schedule' },
      },
      {
        path: 'journal',
        name: 'journal',
        component: () => import('../views/modules/journal/JournalView.vue'),
        meta: { module: 'journal' },
      },
      {
        path: 'tasks',
        name: 'tasks',
        component: () => import('../views/modules/tasks/TasksView.vue'),
        meta: { module: 'tasks' },
      },
      {
        path: 'materials',
        name: 'materials',
        component: () => import('../views/modules/materials/MaterialsView.vue'),
        meta: { module: 'materials' },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: () => import('../pages/Notifications.vue'),
        meta: { module: 'notifications' },
      },
      {
        path: 'chat',
        name: 'chat',
        component: () => import('../pages/Chat.vue'),
        meta: { module: 'chat' },
      },
      {
        path: 'documents',
        name: 'documents',
        component: () => import('../pages/Documents.vue'),
        meta: { permission: 'documents.view' },
      },
      {
        path: 'documents/:id',
        name: 'document-view',
        component: () => import('../pages/DocumentView.vue'),
        meta: { permission: 'documents.view' },
      },
      // Admin routes
      {
        path: 'admin',
        children: [
          {
            path: 'groups',
            name: 'admin-groups',
            component: () => import('../pages/admin/Groups.vue'),
            meta: { permission: 'directory.manage' },
          },
          {
            path: 'subgroups',
            name: 'admin-subgroups',
            component: () => import('../pages/admin/Subgroups.vue'),
            meta: { permission: 'directory.manage' },
          },
          {
            path: 'subjects',
            name: 'admin-subjects',
            component: () => import('../pages/admin/Subjects.vue'),
            meta: { permission: 'directory.manage' },
          },
          {
            path: 'rooms',
            name: 'admin-rooms',
            component: () => import('../pages/admin/Rooms.vue'),
            meta: { permission: 'directory.manage' },
          },
          {
            path: 'time-slots',
            name: 'admin-time-slots',
            component: () => import('../pages/admin/TimeSlots.vue'),
            meta: { permission: 'directory.manage' },
          },
          {
            path: 'users',
            name: 'admin-users',
            component: () => import('../pages/admin/Users.vue'),
            meta: { permission: 'users.read' },
          },
        ],
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()

  // Public routes (login page)
  if (to.meta.requiresAuth === false) {
    if (auth.isAuthenticated) {
      // Already authenticated, redirect to dashboard
      next({ name: 'dashboard' })
    } else {
      next()
    }
    return
  }

  // Protected routes - require authentication
  if (!auth.isAuthenticated) {
    // Not authenticated, redirect to login with return path
    next({ name: 'login', query: { redirect: to.fullPath } })
    return
  }

  // Check per-route permissions
  if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
    next({ name: 'dashboard' })
    return
  }

  next()
})

export default router

