import { apiClient } from './client'

export interface DocumentDTO {
  id: number
  type: string
  number: string
  date: string
  status: 'draft' | 'on_review' | 'approved' | 'signed' | 'archived'
  template_id: number
  data_json: Record<string, any>
  created_by: number
  verify_hash: string
  created_at: string
  updated_at: string
  template?: {
    id: number
    name: string
    type: string
  }
  creator?: {
    id: number
    fio: string
    email: string
  }
}

export interface DocumentListResponse {
  data: DocumentDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const documentsApi = {
  async list(params?: {
    page?: number
    per_page?: number
    type?: string
    status?: string
  }): Promise<DocumentListResponse> {
    const response = await apiClient.get('/v1/documents', { params })
    return response.data
  },

  async get(id: number): Promise<DocumentDTO> {
    const response = await apiClient.get(`/v1/documents/${id}`)
    return response.data
  },

  async export(id: number, format: 'docx' | 'pdf'): Promise<Blob> {
    const response = await apiClient.get(`/v1/documents/${id}/download`, {
      params: { format },
      responseType: 'blob',
    })
    return response.data
  },
}

