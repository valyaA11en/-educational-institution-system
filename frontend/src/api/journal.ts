import apiClient from './client'

export interface GradeChangeDTO {
  id: number
  grade_id: number
  changed_by: number
  before_json: Record<string, any>
  after_json: Record<string, any>
  reason: string | null
  created_at: string
  grade?: {
    id: number
    student_user_id: number
    value: number
    student?: {
      id: number
      fio: string
    }
  }
  changer?: {
    id: number
    fio: string
  }
}

export const journalApi = {
  exportGradeChanges: async (params?: {
    student_id?: number
    subject_id?: number
    date_from?: string
    date_to?: string
  }): Promise<void> => {
    const response = await apiClient.get('/v1/journal/reports/grade-changes/export', {
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
    let filename = 'grade_changes_export.xlsx'
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
}









