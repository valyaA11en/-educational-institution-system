import { apiClient } from './index'

export interface PrintScheduleParams {
  view: 'group' | 'teacher' | 'room'
  id: number
  from?: string
  to?: string
  format?: 'pdf'
}

export interface PrintJournalParams {
  groupId: number
  subjectId?: number
  termId?: number
  format?: 'pdf'
}

export interface PrintAttendanceParams {
  groupId: number
  from?: string
  to?: string
  format?: 'pdf'
}

export const printApi = {
  async schedule(params: PrintScheduleParams): Promise<Blob> {
    const response = await apiClient.get('/print/schedule', {
      params,
      responseType: 'blob',
    })
    return response.data
  },

  async journal(params: PrintJournalParams): Promise<Blob> {
    const response = await apiClient.get('/print/journal', {
      params,
      responseType: 'blob',
    })
    return response.data
  },

  async attendance(params: PrintAttendanceParams): Promise<Blob> {
    const response = await apiClient.get('/print/attendance', {
      params,
      responseType: 'blob',
    })
    return response.data
  },
}

