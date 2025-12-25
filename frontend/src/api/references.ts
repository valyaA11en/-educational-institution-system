import { apiClient } from './client'
import type { UserDTO } from './users'

export interface TermDTO {
  id: number
  academic_year_id: number
  name: string
  start_date: string
  end_date: string
  is_current?: boolean
}

export const referencesApi = {
  async listTerms() {
    // TODO: Create backend endpoint GET /api/admin/terms
    try {
      const { data } = await apiClient.get<TermDTO[]>('/v1/admin/terms')
      return data
    } catch (error) {
      console.warn('Terms endpoint not implemented, returning empty array')
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
        user.roles?.some((role) => role.name.toLowerCase().includes('куратор'))
      )
    } catch (error) {
      console.error('Failed to load curators:', error)
      return []
    }
  },
}

