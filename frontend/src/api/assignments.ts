import apiClient from './client'

export interface AssignmentDTO {
  id: number
  subject_id: number
  teacher_user_id: number
  title: string
  description?: string | null
  due_at?: string | null
  visibility_scope: string
  created_at?: string
  updated_at?: string
}

export const assignmentsApi = {
  async list(params?: { subject_id?: number; teacher_user_id?: number; group_id?: number }) {
    const { data } = await apiClient.get<{ data: AssignmentDTO[] }>('/v1/assignments', { params })
    return data?.data ?? []
  },
  async get(id: number) {
    const { data } = await apiClient.get<{ data: AssignmentDTO }>(`/v1/assignments/${id}`)
    return data?.data ?? data
  },
}
