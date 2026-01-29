import apiClient from './client'
import type { UserDTO } from './users'
import type { RoleDTO } from './dto'

export interface TermDTO {
  id: number
  academic_year_id: number
  name: string
  start_date: string
  end_date: string
  is_current?: boolean
}

export const referencesApi = {
  async listTerms(params?: { academic_year_id?: number; is_current?: boolean }) {
    try {
      const { data } = await apiClient.get<TermDTO[]>('/v1/admin/terms', { params })
      return Array.isArray(data) ? data : []
    } catch (error) {
      console.warn('Terms endpoint failed, returning empty array', error)
      return []
    }
  },

  async listCurators() {
    // TODO: Implement role filter in backend: GET /api/admin/users?role=curator
    // For now, use query search as fallback
    try {
      const { data } = await apiClient.get<{
        data: UserDTO[]
      }>('/v1/admin/users', {
        params: { q: 'куратор', per_page: 1000 },
      })
      // Filter by role name on frontend (temporary workaround)
      return data.data.filter((user) =>
        user.roles?.some((role: RoleDTO) => role.name.toLowerCase().includes('куратор'))
      )
    } catch (error) {
      console.error('Failed to load curators:', error)
      return []
    }
  },

  async listGroups(params?: { q?: string; per_page?: number; page?: number }) {
    try {
      const { data } = await apiClient.get<{
        data: Array<{ id: number; name: string; code?: string }>
        current_page: number
        per_page: number
        total: number
        last_page: number
      }>('/v1/admin/directory/groups', { params })
      return data.data
    } catch (error) {
      console.error('Failed to load groups:', error)
      return []
    }
  },

  async listSubjects(params?: { q?: string; per_page?: number; page?: number }) {
    try {
      const { data } = await apiClient.get<{
        data: Array<{ id: number; name: string; code?: string }>
        current_page: number
        per_page: number
        total: number
        last_page: number
      }>('/v1/admin/directory/subjects', { params })
      return data.data
    } catch (error) {
      console.error('Failed to load subjects:', error)
      return []
    }
  },

  async listTeachers(params?: { q?: string; per_page?: number; page?: number }) {
    try {
      const { data } = await apiClient.get<{
        data: UserDTO[]
        current_page: number
        per_page: number
        total: number
        last_page: number
      }>('/v1/admin/users', {
        params: { ...params, role: 'teacher', per_page: params?.per_page || 1000 },
      })
      return data.data
    } catch (error) {
      console.error('Failed to load teachers:', error)
      return []
    }
  },

  async listRooms(params?: { q?: string; per_page?: number; page?: number }) {
    try {
      const { data } = await apiClient.get<{
        data: Array<{ id: number; name: string; capacity?: number }>
        current_page: number
        per_page: number
        total: number
        last_page: number
      }>('/v1/admin/directory/rooms', { params })
      return data.data
    } catch (error) {
      console.error('Failed to load rooms:', error)
      return []
    }
  },

  async listTimeSlots(params?: { q?: string; per_page?: number; page?: number }) {
    try {
      const { data } = await apiClient.get<{
        data: Array<{ id: number; start_time: string; end_time: string; order?: number }>
        current_page: number
        per_page: number
        total: number
        last_page: number
      }>('/v1/admin/directory/time-slots', { params })
      return data.data.sort((a, b) => (a.order || 0) - (b.order || 0))
    } catch (error) {
      console.error('Failed to load time slots:', error)
      return []
    }
  },
}









