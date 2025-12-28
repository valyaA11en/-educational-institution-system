import { createRouter, createWebHistory, type RouteRecordRaw, type RouteLocationRaw } from 'vue-router'
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
        path: 'schedule/versions',
        name: 'schedule-versions',
        component: () => import('../pages/ScheduleVersions.vue'),
        meta: { permission: 'schedule.read' },
      },
      {
        path: 'journal',
        name: 'journal',
        component: () => import('../views/modules/journal/JournalView.vue'),
        meta: { module: 'journal' },
      },
      {
        path: 'timeline',
        name: 'timeline',
        component: () => import('../pages/Timeline.vue'),
        meta: { module: 'student' },
      },
      {
        path: 'students/:id/timeline',
        name: 'student-timeline',
        component: () => import('../pages/StudentTimeline.vue'),
        meta: { module: 'student' },
      },
      {
        path: 'assistant',
        name: 'assistant',
        component: () => import('../pages/Assistant.vue'),
      },
      {
        path: 'portfolio',
        name: 'portfolio',
        component: () => import('../pages/Portfolio.vue'),
        meta: { module: 'student' },
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
        path: 'settings/notifications',
        name: 'settings-notifications',
        component: () => import('../pages/settings/Notifications.vue'),
      },
      {
        path: 'settings/security',
        name: 'settings-security',
        component: () => import('../pages/settings/Security.vue'),
      },
      {
        path: 'chat',
        name: 'chat',
        component: () => import('../pages/Chat.vue'),
        meta: { module: 'chat' },
      },
      {
        path: 'exams',
        name: 'exams',
        component: () => import('../pages/Exams.vue'),
        meta: { permission: 'exams.read' },
      },
      {
        path: 'exams/new',
        name: 'exam-new',
        component: () => import('../pages/ExamNew.vue'),
        meta: { permission: 'exams.create' },
      },
      {
        path: 'exams/:id',
        name: 'exam-detail',
        component: () => import('../pages/ExamDetail.vue'),
        meta: { permission: 'exams.read' },
      },
      {
        path: 'contests',
        name: 'contests',
        component: () => import('../pages/Contests.vue'),
        meta: { permission: 'contests.view' },
      },
      {
        path: 'contests/new',
        name: 'contest-new',
        component: () => import('../pages/ContestNew.vue'),
        meta: { permission: 'contests.manage' },
      },
      {
        path: 'contests/:id',
        name: 'contest-detail',
        component: () => import('../pages/ContestDetail.vue'),
        meta: { permission: 'contests.view' },
      },
      {
        path: 'documents',
        name: 'documents',
        component: () => import('../pages/Documents.vue'),
        meta: { permission: 'documents.view' },
      },
      {
        path: 'documents/new',
        name: 'document-new',
        component: () => import('../pages/DocumentNew.vue'),
        meta: { permission: 'documents.create' },
      },
      {
        path: 'documents/:id',
        name: 'document-view',
        component: () => import('../pages/DocumentView.vue'),
        meta: { permission: 'documents.view' },
      },
      {
        path: 'ktp',
        name: 'ktp',
        component: () => import('../pages/Ktp.vue'),
        meta: { permission: 'curriculum.view' },
      },
      {
        path: 'ktp/:id',
        name: 'ktp-view',
        component: () => import('../pages/KtpView.vue'),
        meta: { permission: 'curriculum.view' },
      },
      {
        path: 'tickets',
        name: 'tickets',
        component: () => import('../pages/Tickets.vue'),
      },
      {
        path: 'tickets/:id',
        name: 'ticket-view',
        component: () => import('../pages/TicketView.vue'),
      },
      {
        path: 'analytics/risks',
        name: 'analytics-risks',
        component: () => import('../pages/AnalyticsRisks.vue'),
        meta: { permission: 'analytics.view' },
      },
      {
        path: 'me/risks',
        name: 'my-risks',
        component: () => import('../pages/MyRisks.vue'),
      },
      {
        path: 'parent/child/:id/risks',
        name: 'parent-child-risks',
        component: () => import('../pages/ParentChildRisks.vue'),
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
          {
            path: 'rules',
            name: 'admin-rules',
            component: () => import('../pages/admin/Rules.vue'),
            meta: { permission: 'rules.manage' },
          },
          {
            path: 'tickets/overdue',
            name: 'admin-tickets-overdue',
            component: () => import('../pages/admin/TicketsOverdue.vue'),
            meta: { permission: 'tickets.manage' },
          },
          {
            path: 'chat-reports',
            name: 'admin-chat-reports',
            component: () => import('../pages/admin/ChatReports.vue'),
            meta: { permission: 'chat.moderate' },
          },
          {
            path: 'webhooks',
            name: 'admin-webhooks',
            component: () => import('../pages/admin/Webhooks.vue'),
            meta: { role: 'admin' },
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

router.beforeEach((to, _from, next) => {
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
  const permission = to.meta.permission as string | undefined
  if (permission && !auth.hasPermission(permission)) {
    next({ name: 'dashboard' } as RouteLocationRaw)
    return
  }

  next()
})

export default router

