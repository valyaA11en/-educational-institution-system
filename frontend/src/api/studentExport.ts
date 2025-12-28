import api from './client'

export interface ExportTimelineParams {
  dateFrom?: string
  dateTo?: string
  event_type?: string[]
}

export const studentExportApi = {
  exportStudent(studentId: number, format: 'pdf' = 'pdf') {
    return api.get(`/v1/students/${studentId}/export`, {
      params: { format },
      responseType: 'blob',
    }).then((response) => {
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `student_${studentId}_${new Date().toISOString().split('T')[0]}.pdf`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    })
  },
  exportTimeline(studentId: number, params?: ExportTimelineParams) {
    const queryParams: any = {}
    if (params?.dateFrom) queryParams.dateFrom = params.dateFrom
    if (params?.dateTo) queryParams.dateTo = params.dateTo
    if (params?.event_type && params.event_type.length > 0) {
      queryParams.event_type = params.event_type.join(',')
    }

    return api.get(`/v1/students/${studentId}/timeline/export`, {
      params: queryParams,
      responseType: 'blob',
    }).then((response) => {
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      const dateStr = new Date().toISOString().split('T')[0]
      link.setAttribute('download', `timeline_${studentId}_${dateStr}.pdf`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    })
  },
}

