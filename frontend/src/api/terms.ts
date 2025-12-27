import apiClient from './client'

export interface TermDTO {
  id: number
  academic_year_id: number
  name: string
  start_date: string
  end_date: string
  is_current?: boolean
  created_at?: string
  updated_at?: string
}

export const termsApi = {
  async list() {
    // TODO: Create backend endpoint GET /api/admin/terms
    const { data } = await apiClient.get<TermDTO[]>('/v1/admin/terms')
    return data
  },
}


