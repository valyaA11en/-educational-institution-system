import apiClient from './client'

export interface RiskDTO {
  id: number
  user_id: number
  term_id: number | null
  risk_type: 'avg_low' | 'absences_high' | 'debts_high' | 'no_activity'
  level: 'green' | 'yellow' | 'red'
  score: number
  details_json: Record<string, any>
  calculated_at: string | null
  created_at: string
  updated_at: string
  user?: {
    id: number
    fio: string
    email: string
  }
}

export interface RiskListResponse {
  data: RiskDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface TopicPerformanceStat {
  id: number
  subject_id: number
  ktp_topic_id: number
  term_id: number | null
  students_total: number
  students_failed: number
  fail_percent: number
  calculated_at: string
  subject?: {
    id: number
    name: string
  }
  topic?: {
    id: number
    title: string
  }
}

export interface TopicsParams {
  subjectId?: number
  termId?: number
  fail_percent?: number
  page?: number
  per_page?: number
}

export const analyticsApi = {
  getRisks: async (params?: {
    groupId?: number
    level?: 'green' | 'yellow' | 'red'
    risk_type?: 'avg_low' | 'absences_high' | 'debts_high' | 'no_activity'
    term_id?: number
    page?: number
    per_page?: number
  }): Promise<RiskListResponse> => {
    const response = await apiClient.get('/v1/analytics/risks', { params })
    return response.data
  },

  getMyRisks: async (params?: {
    term_id?: number
    level?: 'green' | 'yellow' | 'red'
    child_id?: number
  }): Promise<{ data: RiskDTO[] }> => {
    const response = await apiClient.get('/v1/analytics/risks/my', { params })
    return response.data
  },

  exportRisks: async (params?: {
    groupId?: number
    level?: 'green' | 'yellow' | 'red'
    risk_type?: 'avg_low' | 'absences_high' | 'debts_high' | 'no_activity'
  }): Promise<void> => {
    const response = await apiClient.get('/v1/analytics/risks/export', {
      params,
      responseType: 'blob',
    })

    // Create blob URL and trigger download
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    
    // Extract filename from Content-Disposition header or use default
    const contentDisposition = response.headers['content-disposition']
    let filename = 'risks_export.xlsx'
    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename="?(.+)"?/i)
      if (filenameMatch) {
        filename = filenameMatch[1]
      }
    }
    
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  },

  async getTopics(params?: TopicsParams) {
    const response = await apiClient.get<{
      data: TopicPerformanceStat[]
      current_page: number
      per_page: number
      total: number
      last_page: number
    }>('/v1/analytics/topics', { params })
    return response.data
  },
}

