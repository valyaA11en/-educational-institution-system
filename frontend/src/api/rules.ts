import apiClient from './client'

export interface RuleDTO {
  id: number
  name: string
  enabled: boolean
  scope: 'global' | 'org' | 'term'
  conditions_json: Array<{
    field: string
    operator: string
    value: any
  }>
  actions_json: Array<{
    type: string
    [key: string]: any
  }>
  created_by: number
  creator?: {
    id: number
    fio: string
    email: string
  }
  created_at: string
  updated_at: string
}

export interface RuleListResponse {
  data: RuleDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const rulesApi = {
  list: async (params?: { scope?: string; enabled?: boolean; page?: number; per_page?: number }): Promise<RuleListResponse> => {
    const response = await apiClient.get('/rules', { params })
    return response.data
  },

  get: async (id: number): Promise<RuleDTO> => {
    const response = await apiClient.get(`/rules/${id}`)
    return response.data
  },

  create: async (data: Omit<RuleDTO, 'id' | 'created_by' | 'creator' | 'created_at' | 'updated_at'>): Promise<RuleDTO> => {
    const response = await apiClient.post('/rules', data)
    return response.data
  },

  update: async (id: number, data: Partial<Omit<RuleDTO, 'id' | 'created_by' | 'creator' | 'created_at' | 'updated_at'>>): Promise<RuleDTO> => {
    const response = await apiClient.put(`/rules/${id}`, data)
    return response.data
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/rules/${id}`)
  },

  toggle: async (id: number): Promise<RuleDTO> => {
    const response = await apiClient.post(`/rules/${id}/toggle`)
    return response.data
  },
}

