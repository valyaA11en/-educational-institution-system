import apiClient from './client'

export interface GradeChangeDTO {
  id: number
  grade_id: number
  changed_by: number
  before_json: Record<string, unknown>
  after_json: Record<string, unknown>
  reason: string | null
  created_at: string
  grade?: {
    id: number
    student_user_id: number
    value: number
    student?: { id: number; fio: string }
  }
  changer?: { id: number; fio: string }
}

export interface JournalGridParams {
  group_id: number
  subject_id: number
  date_from: string
  date_to: string
  subgroup_id?: number
}

export interface JournalGridLesson {
  id: number
  date: string
  topic: string | null
  time_slot_id: number
}

export interface JournalGridStudentLesson {
  lesson_id: number
  date: string
  topic: string | null
  grades: Array<{ id: number; value: number; weight: number; grade_type?: string; comment?: string | null }>
  attendance: { status: string; reason?: string | null } | null
}

export interface JournalGridStudent {
  id: number
  fio: string
  lessons: JournalGridStudentLesson[]
}

export interface JournalGridResponse {
  success: boolean
  data?: {
    students: JournalGridStudent[]
    lessons: JournalGridLesson[]
  }
}

export interface SaveGridGradesItem {
  student_user_id: number
  value: number
  weight?: number
  grade_type?: string
  comment?: string | null
  id?: number
}

export interface SaveGridAttendanceItem {
  student_user_id: number
  status: 'present' | 'absent' | 'late'
  reason?: string | null
}

export interface SaveGridPayload {
  lesson_id: number
  grades?: SaveGridGradesItem[]
  attendance?: SaveGridAttendanceItem[]
}

export const journalApi = {
  getGrid: async (params: JournalGridParams): Promise<JournalGridResponse> => {
    const { data } = await apiClient.get<JournalGridResponse>('/v1/journal/grid', { params })
    return data
  },

  saveGrid: async (payload: SaveGridPayload): Promise<{ success: boolean; message?: string; saved?: number; errors?: unknown[] }> => {
    const { data } = await apiClient.post('/v1/journal/grid/save', payload)
    return data
  },

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

    const contentType = response.headers['content-type'] ?? ''
    const blob = new Blob([response.data], { type: contentType || 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url

    const contentDisposition = response.headers['content-disposition']
    let filename = 'grade_changes_export.csv'
    if (contentDisposition) {
      const match = contentDisposition.match(/filename="?([^";\n]+)"?/i)
      if (match) filename = match[1].trim()
    }

    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  },
}
