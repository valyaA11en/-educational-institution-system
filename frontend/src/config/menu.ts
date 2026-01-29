// Menu configuration based on user roles
// Each role has its own set of menu items

export interface MenuItem {
  to: any
  title: string
  icon: string
  divider?: boolean
  roles?: string[] // Roles that can see this item
  permissions?: string[] // Permissions required to see this item
  anyPermission?: boolean // If true, user needs ANY of the permissions, otherwise ALL
}

// Common menu items available to all authenticated users
const commonItems: MenuItem[] = [
  { to: { name: 'dashboard' }, title: 'Панель управления', icon: 'mdi-view-dashboard' },
  { to: { name: 'notifications' }, title: 'Уведомления', icon: 'mdi-bell' },
]

// Student menu items
const studentMenu: MenuItem[] = [
  { to: { name: 'assistant' }, title: 'Что делать сегодня', icon: 'mdi-check-circle' },
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar', permissions: ['schedule.view'] },
  { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant', permissions: ['journal.view'] },
  { to: { name: 'timeline' }, title: 'История обучения', icon: 'mdi-timeline' },
  { to: { name: 'portfolio' }, title: 'Портфолио', icon: 'mdi-briefcase' },
  { to: { name: 'tasks' }, title: 'Задания', icon: 'mdi-clipboard-text', permissions: ['assignments.view'] },
  { to: { name: 'materials' }, title: 'Материалы', icon: 'mdi-file-document-multiple', permissions: ['materials.view'] },
  { to: { name: 'chat' }, title: 'Чаты', icon: 'mdi-forum', permissions: ['chat.view'] },
  { to: { name: 'tickets' }, title: 'Тикеты', icon: 'mdi-ticket' },
  { to: { name: 'exams' }, title: 'Экзамены', icon: 'mdi-school' },
  { to: { name: 'contests' }, title: 'Конкурсы', icon: 'mdi-trophy' },
  { to: { name: 'my-risks' }, title: 'Мои риски', icon: 'mdi-alert-circle' },
]

// Parent menu items
const parentMenu: MenuItem[] = [
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar', permissions: ['schedule.view'] },
  { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant', permissions: ['journal.view'] },
  { to: { name: 'tasks' }, title: 'Задания', icon: 'mdi-clipboard-text', permissions: ['assignments.view'] },
  { to: { name: 'documents' }, title: 'Документы', icon: 'mdi-file-document', permissions: ['documents.view'] },
  { to: { name: 'chat' }, title: 'Чаты', icon: 'mdi-forum', permissions: ['chat.view'] },
  { to: { name: 'notifications' }, title: 'Уведомления', icon: 'mdi-bell', permissions: ['notifications.view'] },
]

// Teacher menu items
const teacherMenu: MenuItem[] = [
  { to: { name: 'assistant' }, title: 'Что делать сегодня', icon: 'mdi-check-circle' },
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar', permissions: ['schedule.view'] },
  { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant', permissions: ['journal.view'] },
  { to: { name: 'tasks' }, title: 'Задания', icon: 'mdi-clipboard-text', permissions: ['assignments.view'] },
  { to: { name: 'materials' }, title: 'Материалы', icon: 'mdi-file-document-multiple', permissions: ['materials.view'] },
  { to: { name: 'chat' }, title: 'Чаты', icon: 'mdi-forum', permissions: ['chat.view'] },
  { to: { name: 'tickets' }, title: 'Тикеты', icon: 'mdi-ticket' },
  { to: { name: 'exams' }, title: 'Экзамены', icon: 'mdi-school' },
  { to: { name: 'contests' }, title: 'Конкурсы', icon: 'mdi-trophy' },
]

// Curator menu items
const curatorMenu: MenuItem[] = [
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar', permissions: ['schedule.view'] },
  { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant', permissions: ['journal.view'] },
  { to: { name: 'panel-curator' }, title: 'Панель куратора', icon: 'mdi-account-group' },
  { to: { name: 'chat' }, title: 'Чаты', icon: 'mdi-forum', permissions: ['chat.view'] },
]

// Methodist menu items
const methodistMenu: MenuItem[] = [
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar', permissions: ['schedule.view'] },
  { to: { name: 'panel-methodist' }, title: 'Панель методиста', icon: 'mdi-school' },
  { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant', permissions: ['journal.reports.view'] },
  { to: { name: 'reports' }, title: 'Отчёты', icon: 'mdi-chart-box', permissions: ['reports.view'] },
  { to: { name: 'documents' }, title: 'Документы', icon: 'mdi-file-document', permissions: ['documents.registry'] },
  { to: { name: 'ktp' }, title: 'КТП', icon: 'mdi-calendar-text', permissions: ['curriculum.view'] },
  { divider: true },
  { to: { name: 'admin-groups' }, title: 'Группы', icon: 'mdi-account-group', permissions: ['directory.manage'] },
  { to: { name: 'admin-subgroups' }, title: 'Подгруппы', icon: 'mdi-account-group-outline', permissions: ['directory.manage'] },
  { to: { name: 'admin-subjects' }, title: 'Предметы', icon: 'mdi-book-open-variant', permissions: ['directory.manage'] },
  { to: { name: 'admin-rooms' }, title: 'Кабинеты', icon: 'mdi-door', permissions: ['directory.manage'] },
  { to: { name: 'admin-time-slots' }, title: 'Слоты пар', icon: 'mdi-clock-outline', permissions: ['directory.manage'] },
]

// Scheduler menu items
const schedulerMenu: MenuItem[] = [
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar', permissions: ['schedule.view'] },
  { to: { name: 'schedule-versions' }, title: 'Версии расписания', icon: 'mdi-calendar-clock', permissions: ['schedule.edit'] },
  { to: { name: 'schedule-list' }, title: 'Список версий', icon: 'mdi-format-list-bulleted', permissions: ['schedule.read'] },
]

// Management menu items
const managementMenu: MenuItem[] = [
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar', permissions: ['schedule.view'] },
  { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant', permissions: ['journal.reports.view'] },
  { to: { name: 'reports' }, title: 'Отчёты', icon: 'mdi-chart-box', permissions: ['reports.view'] },
  { to: { name: 'analytics-risks' }, title: 'Риски студентов', icon: 'mdi-alert-circle', permissions: ['analytics.view'] },
  { to: { name: 'documents' }, title: 'Документы', icon: 'mdi-file-document', permissions: ['documents.view'] },
  { to: { name: 'panel-principal' }, title: 'Панель директора', icon: 'mdi-account-tie' },
]

// Admin menu items
const adminMenu: MenuItem[] = [
  { to: { name: 'assistant' }, title: 'Что делать сегодня', icon: 'mdi-check-circle' },
  { to: { name: 'schedule' }, title: 'Расписание', icon: 'mdi-calendar' },
  { to: { name: 'journal' }, title: 'Журнал', icon: 'mdi-book-open-page-variant' },
  { to: { name: 'reports' }, title: 'Отчёты', icon: 'mdi-chart-box' },
  { to: { name: 'analytics-risks' }, title: 'Риски студентов', icon: 'mdi-alert-circle' },
  { to: { name: 'documents' }, title: 'Документы', icon: 'mdi-file-document' },
  { to: { name: 'ktp' }, title: 'КТП', icon: 'mdi-calendar-text' },
  { to: { name: 'tasks' }, title: 'Задания', icon: 'mdi-clipboard-text' },
  { to: { name: 'materials' }, title: 'Материалы', icon: 'mdi-file-document-multiple' },
  { to: { name: 'chat' }, title: 'Чаты', icon: 'mdi-forum' },
  { to: { name: 'tickets' }, title: 'Тикеты', icon: 'mdi-ticket' },
  { to: { name: 'exams' }, title: 'Экзамены', icon: 'mdi-school' },
  { to: { name: 'contests' }, title: 'Конкурсы', icon: 'mdi-trophy' },
  { divider: true },
  { to: { name: 'admin-groups' }, title: 'Группы', icon: 'mdi-account-group' },
  { to: { name: 'admin-subgroups' }, title: 'Подгруппы', icon: 'mdi-account-group-outline' },
  { to: { name: 'admin-subjects' }, title: 'Предметы', icon: 'mdi-book-open-variant' },
  { to: { name: 'admin-rooms' }, title: 'Кабинеты', icon: 'mdi-door' },
  { to: { name: 'admin-time-slots' }, title: 'Слоты пар', icon: 'mdi-clock-outline' },
  { to: { name: 'admin-users' }, title: 'Пользователи', icon: 'mdi-account-multiple' },
  { to: { name: 'admin-rules' }, title: 'Правила', icon: 'mdi-auto-fix', permissions: ['rules.manage'] },
  { to: { name: 'admin-tickets-overdue' }, title: 'Просроченные тикеты', icon: 'mdi-alert-circle', permissions: ['tickets.manage'] },
  { to: { name: 'admin-document-templates' }, title: 'Шаблоны документов', icon: 'mdi-file-document-edit', permissions: ['documents.registry'] },
  { to: { name: 'admin-document-registry' }, title: 'Реестр документов', icon: 'mdi-book-open-variant', permissions: ['documents.registry'] },
  { to: { name: 'admin-chat-reports' }, title: 'Жалобы на сообщения', icon: 'mdi-alert-circle', permissions: ['chat.moderate'] },
  { to: { name: 'admin-webhooks' }, title: 'Webhooks', icon: 'mdi-webhook' },
  { to: { name: 'admin-audit' }, title: 'Журнал аудита', icon: 'mdi-file-document-edit-outline' },
]

/**
 * Get menu items for a specific role
 */
export function getMenuItemsForRole(roleName: string): MenuItem[] {
  switch (roleName.toLowerCase()) {
    case 'admin':
      return [...commonItems, ...adminMenu]
    case 'management':
      return [...commonItems, ...managementMenu]
    case 'methodist':
      return [...commonItems, ...methodistMenu]
    case 'teacher':
      return [...commonItems, ...teacherMenu]
    case 'curator':
      return [...commonItems, ...curatorMenu]
    case 'scheduler':
      return [...commonItems, ...schedulerMenu]
    case 'student':
      return [...commonItems, ...studentMenu]
    case 'parent':
      return [...commonItems, ...parentMenu]
    default:
      // Return only common items for unknown roles
      return commonItems
  }
}

/**
 * Check if user has required permissions for a menu item
 */
export function canSeeMenuItem(
  item: MenuItem,
  userRoles: string[],
  userPermissions: string[]
): boolean {
  // If item has roles, check if user has any of them
  if (item.roles && item.roles.length > 0) {
    const hasRole = item.roles.some(role => userRoles.includes(role))
    if (!hasRole) return false
  }

  // If item has permissions, check if user has them
  if (item.permissions && item.permissions.length > 0) {
    if (item.anyPermission) {
      // User needs ANY of the permissions
      const hasPermission = item.permissions.some(perm => userPermissions.includes(perm))
      if (!hasPermission) return false
    } else {
      // User needs ALL of the permissions
      const hasAllPermissions = item.permissions.every(perm => userPermissions.includes(perm))
      if (!hasAllPermissions) return false
    }
  }

  return true
}
