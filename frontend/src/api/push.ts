import apiClient from './client'

export interface PushSubscriptionDTO {
  id: number
  user_id: number
  endpoint: string
  keys_json: {
    p256dh: string
    auth: string
  }
  created_at: string
  updated_at: string
}

export interface PushSubscriptionRequest {
  endpoint: string
  keys: {
    p256dh: string
    auth: string
  }
}

export const pushApi = {
  async subscribe(payload: PushSubscriptionRequest): Promise<PushSubscriptionDTO> {
    const response = await apiClient.post<PushSubscriptionDTO>('/v1/push/subscribe', payload)
    return response.data
  },

  async unsubscribe(endpoint: string): Promise<void> {
    await apiClient.delete('/v1/push/unsubscribe', {
      data: { endpoint },
    })
  },
}








