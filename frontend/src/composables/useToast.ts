import { useNotificationsStore } from '../stores/notifications'

export function useToast() {
  const notifications = useNotificationsStore()

  const showToast = (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') => {
    notifications.showToast(message, type)
  }

  return {
    showToast,
  }
}

