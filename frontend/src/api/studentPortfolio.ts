import api from './client'

export interface StudentPortfolioItem {
  id: number
  type: 'assignment' | 'contest' | 'certificate' | 'achievement'
  title: string
  description: string | null
  related_entity_type: string | null
  related_entity_id: number | null
  file_id: number | null
  is_featured: boolean
  created_at: string
}

export interface PortfolioParams {
  type?: string
  is_featured?: boolean
}

export const studentPortfolioApi = {
  getPortfolio(studentId: number, params?: PortfolioParams) {
    return api.get<{ data: StudentPortfolioItem[] }>(`/v1/students/${studentId}/portfolio`, {
      params,
    })
  },
  updateItem(studentId: number, itemId: number, data: { is_featured?: boolean; title?: string; description?: string }) {
    return api.patch(`/v1/students/${studentId}/portfolio/${itemId}`, data)
  },
  exportPortfolio(studentId: number) {
    return api.get(`/v1/students/${studentId}/portfolio/export`, {
      responseType: 'blob',
    }).then((response) => {
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      const dateStr = new Date().toISOString().split('T')[0]
      link.setAttribute('download', `portfolio_${studentId}_${dateStr}.pdf`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    })
  },
}


