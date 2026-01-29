import apiClient from './client'

export interface MaterialDTO {
  id: number
  subject_id: number
  title: string
  content?: string | null
  visibility_scope: 'group' | 'subgroup' | 'individual' | 'all'
  created_by: number
  created_at?: string
  updated_at?: string
  targets?: { id: number; material_id: number; group_id?: number; subgroup_id?: number; student_user_id?: number }[]
}

export interface MaterialTarget {
  group_id?: number
  subgroup_id?: number
  student_user_id?: number
}

export const materialsApi = {
  async list(params?: { subject_id?: number; created_by?: number }): Promise<MaterialDTO[]> {
    const { data } = await apiClient.get<{ data: MaterialDTO[] }>('/v1/materials', { params })
    return data?.data ?? data ?? []
  },

  async get(id: number): Promise<MaterialDTO> {
    const { data } = await apiClient.get<{ data: MaterialDTO }>(`/v1/materials/${id}`)
    return data?.data ?? data
  },

  async create(payload: {
    subject_id: number
    title: string
    content?: string | null
    visibility_scope: 'group' | 'subgroup' | 'individual' | 'all'
    targets?: MaterialTarget[]
  }): Promise<MaterialDTO> {
    const { data } = await apiClient.post<{ data: MaterialDTO }>('/v1/materials', payload)
    return data?.data ?? data
  },

  async update(
    id: number,
    payload: Partial<{
      subject_id: number
      title: string
      content: string | null
      visibility_scope: 'group' | 'subgroup' | 'individual' | 'all'
      targets: MaterialTarget[]
    }>
  ): Promise<MaterialDTO> {
    const { data } = await apiClient.put<{ data: MaterialDTO }>(`/v1/materials/${id}`, payload)
    return data?.data ?? data
  },

  async delete(id: number): Promise<void> {
    await apiClient.delete(`/v1/materials/${id}`)
  },

  async read(id: number): Promise<void> {
    await apiClient.post(`/v1/materials/${id}/read`)
  },

  async download(id: number): Promise<Blob> {
    const res = await apiClient.get(`/v1/materials/${id}/download`, { responseType: 'blob' })
    return res.data
  },
}
