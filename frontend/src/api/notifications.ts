import apiClient from './client'

export interface NotificationItemDTO {
  id: number
  type: string
  payload: Record<string, any>
  status: 'new' | 'sent' | 'read' | string
  createdAt: string
  readAt: string | null
}

export const notificationsApi = {
  async list(): Promise<NotificationItemDTO[]> {
    const response = await apiClient.get<NotificationItemDTO[]>('/v1/notifications')
    return response.data
  },

  async markRead(id: number): Promise<void> {
    await apiClient.post(`/v1/notifications/${id}/read`)
  },

  async markAllRead(): Promise<void> {
    await apiClient.post('/v1/notifications/read-all')
  },
}


