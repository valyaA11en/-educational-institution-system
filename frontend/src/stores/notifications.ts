import { defineStore } from 'pinia'
import { notificationsApi, type NotificationItemDTO } from '../api/notifications'

export interface NotificationItem extends NotificationItemDTO {}

interface NotificationsState {
  items: NotificationItem[]
  loading: boolean
  filterType: string | null
  toastMessage: string | null
  toastType: 'success' | 'error' | 'info' | 'warning'
  toastVisible: boolean
}

export const useNotificationsStore = defineStore('notifications', {
  state: (): NotificationsState => ({
    items: [],
    loading: false,
    filterType: null,
    toastMessage: null,
    toastType: 'info',
    toastVisible: false,
  }),

  getters: {
    unreadCount: (state): number =>
      state.items.filter((n) => !n.readAt && n.status !== 'read').length,

    types: (state): string[] => {
      const set = new Set<string>()
      state.items.forEach((n) => set.add(n.type))
      return Array.from(set).sort()
    },

    filtered: (state): NotificationItem[] => {
      if (!state.filterType) return state.items
      return state.items.filter((n) => n.type === state.filterType)
    },
  },

  actions: {
    async load() {
      if (this.loading) return
      this.loading = true
      try {
        const data = await notificationsApi.list()
        // сортировка: новые сверху
        this.items = data.sort(
          (a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime(),
        )
      } finally {
        this.loading = false
      }
    },

    async markRead(id: number) {
      const item = this.items.find((n) => n.id === id)
      if (!item || item.readAt) return

      await notificationsApi.markRead(id)
      item.readAt = new Date().toISOString()
      item.status = 'read'
    },

    async markAllRead() {
      await notificationsApi.markAllRead()
      this.items = this.items.map((n) => ({
        ...n,
        readAt: n.readAt ?? new Date().toISOString(),
        status: 'read',
      }))
    },

    async delete(id: number) {
      await notificationsApi.delete(id)
      this.items = this.items.filter((n) => n.id !== id)
    },

    /** Добавить уведомление, полученное по WS (payload.payload == NotificationDTO) */
    addFromRealtime(dto: { payload: NotificationItemDTO }) {
      const notif = dto.payload
      this.items.unshift(notif)
      this.showToast(this.buildTitle(notif))
    },

    buildTitle(notif: NotificationItemDTO): string {
      switch (notif.type) {
        case 'grade.created':
          return 'Новая оценка'
        case 'assignment.due_soon':
          return 'Скоро дедлайн задания'
        case 'schedule.changed':
          return 'Изменение расписания'
        case 'document.status_changed':
          return 'Изменение статуса документа'
        default:
          return 'Новое уведомление'
      }
    },

    showToast(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
      this.toastMessage = message
      this.toastType = type
      this.toastVisible = true
    },

    hideToast() {
      this.toastVisible = false
    },
  },
})


