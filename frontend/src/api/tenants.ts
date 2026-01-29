import apiClient from './client'

export interface TenantDTO {
  id: number
  name: string
  slug: string
  timezone: string
  created_at: string
  updated_at: string
}

export interface CreateTenantDTO {
  name: string
  slug: string
  timezone?: string
}

export interface UpdateTenantDTO {
  name?: string
  slug?: string
  timezone?: string
}

export interface SwitchTenantResponse {
  message: string
  access_token: string
  token_type: string
  expires_in: number
  tenant: TenantDTO
}

export const tenantsApi = {
  list: async (): Promise<TenantDTO[]> => {
    const response = await apiClient.get<TenantDTO[]>('/v1/admin/tenants')
    return response.data
  },

  get: async (id: number): Promise<TenantDTO> => {
    const response = await apiClient.get<TenantDTO>(`/v1/admin/tenants/${id}`)
    return response.data
  },

  create: async (data: CreateTenantDTO): Promise<TenantDTO> => {
    const response = await apiClient.post<TenantDTO>('/v1/admin/tenants', data)
    return response.data
  },

  update: async (id: number, data: UpdateTenantDTO): Promise<TenantDTO> => {
    const response = await apiClient.patch<TenantDTO>(`/v1/admin/tenants/${id}`, data)
    return response.data
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/v1/admin/tenants/${id}`)
  },

  switch: async (id: number): Promise<SwitchTenantResponse> => {
    const response = await apiClient.post<SwitchTenantResponse>(`/v1/admin/tenants/${id}/switch`)
    return response.data
  },

  getCurrent: async (): Promise<TenantDTO> => {
    // Real API call to Laravel backend
    const response = await apiClient.get<TenantDTO>('/v1/tenant/current')
    return response.data
  },

  getUserTenants: async (): Promise<TenantDTO[]> => {
    // Real API call to Laravel backend
    const response = await apiClient.get<TenantDTO[]>('/v1/tenant/list')
    return response.data
  },
}


