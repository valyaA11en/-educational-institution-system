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
  async list(params?: { status?: string }): Promise<NotificationItemDTO[]> {
    const { data } = await apiClient.get<{ data?: NotificationItemDTO[] }>('/v1/notifications', {
      params: params?.status ? { status: params.status } : undefined,
    })
    const arr = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
    return arr as NotificationItemDTO[]
  },

  async markRead(id: number): Promise<void> {
    await apiClient.post(`/v1/notifications/${id}/read`)
  },

  async markAllRead(): Promise<void> {
    await apiClient.post('/v1/notifications/read-all')
  },

  async delete(id: number): Promise<void> {
    await apiClient.delete(`/v1/notifications/${id}`)
  },
}










