import apiClient from './client'

export interface WebhookEndpointDTO {
  id: number
  name: string
  url: string
  secret: string
  enabled: boolean
  event_types: string[]
  created_at?: string
  updated_at?: string
}

export interface CreateWebhookEndpointDTO {
  name: string
  url: string
  secret?: string
  enabled?: boolean
  event_types: string[]
}

export interface UpdateWebhookEndpointDTO {
  name?: string
  url?: string
  secret?: string
  enabled?: boolean
  event_types?: string[]
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const webhooksApi = {
  async list(params?: { q?: string; enabled?: boolean; per_page?: number; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<WebhookEndpointDTO>>('/v1/admin/webhooks', { params })
    return data
  },
  async get(id: number) {
    const { data } = await apiClient.get<WebhookEndpointDTO>(`/v1/admin/webhooks/${id}`)
    return data
  },
  async create(payload: CreateWebhookEndpointDTO) {
    const { data } = await apiClient.post<WebhookEndpointDTO>('/v1/admin/webhooks', payload)
    return data
  },
  async update(id: number, payload: UpdateWebhookEndpointDTO) {
    const { data } = await apiClient.patch<WebhookEndpointDTO>(`/v1/admin/webhooks/${id}`, payload)
    return data
  },
  async delete(id: number) {
    await apiClient.delete(`/v1/admin/webhooks/${id}`)
  },
}


