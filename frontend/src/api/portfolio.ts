import api from './client'

export interface PortfolioItem {
  id: number
  student_user_id: number
  type: 'achievement' | 'project' | 'certificate' | 'contest' | 'other'
  title: string
  description: string | null
  date: string
  file_path: string | null
  metadata_json: Record<string, any> | null
  is_public: boolean
  created_at: string
  updated_at: string
}

export interface PortfolioParams {
  student_id?: number
  type?: string
  is_public?: boolean
}

export interface CreatePortfolioItem {
  type: 'achievement' | 'project' | 'certificate' | 'contest' | 'other'
  title: string
  description?: string
  date: string
  file?: File
  is_public?: boolean
}

export const portfolioApi = {
  getItems(params?: PortfolioParams) {
    return api.get<{ data: PortfolioItem[] }>('/v1/portfolio', { params })
  },
  createItem(data: CreatePortfolioItem) {
    const formData = new FormData()
    formData.append('type', data.type)
    formData.append('title', data.title)
    if (data.description) formData.append('description', data.description)
    formData.append('date', data.date)
    if (data.file) formData.append('file', data.file)
    if (data.is_public !== undefined) formData.append('is_public', String(data.is_public))
    
    return api.post<{ data: PortfolioItem }>('/v1/portfolio', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  updateItem(id: number, data: Partial<CreatePortfolioItem>) {
    return api.put<{ data: PortfolioItem }>(`/v1/portfolio/${id}`, data)
  },
  deleteItem(id: number) {
    return api.delete(`/v1/portfolio/${id}`)
  },
}

