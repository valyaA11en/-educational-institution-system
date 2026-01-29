import apiClient from './client'

export interface ReportJournalFilters {
  group_id?: number
  subject_id?: number
  date_from?: string
  date_to?: string
}

export interface ReportJournalData {
  summary: { lessons_count: number; grades_count: number; attendance_count: number }
  filters: ReportJournalFilters
}

export interface ReportScheduleFilters {
  version_id?: number
  date_from?: string
  date_to?: string
  group_id?: number
}

export interface ReportScheduleData {
  summary: { total: number; returned: number }
  items: Array<Record<string, unknown>>
  filters: ReportScheduleFilters
}

export interface ReportGradeSheetFilters {
  group_id?: number
  subject_id?: number
  student_user_id?: number
}

export interface ReportGradeSheetData {
  grades: Array<{
    id: number
    student_user_id: number
    student_fio: string
    value: number | null
    weight: number | null
    lesson_id: number | null
    assignment_id: number | null
    subject_name: string | null
  }>
  filters: ReportGradeSheetFilters
}

export interface ReportOrderFilters {
  date_from?: string
  date_to?: string
  status?: 'draft' | 'on_review' | 'approved' | 'signed' | 'archived'
}

export interface ReportOrderData {
  summary: { total: number; returned: number }
  orders: Array<Record<string, unknown>>
  filters: ReportOrderFilters
}

const base = '/v1/reports'

export const reportsApi = {
  journal: async (params?: ReportJournalFilters): Promise<ReportJournalData> => {
    const { data } = await apiClient.get<{ data: ReportJournalData }>(`${base}/journal`, { params })
    return data.data
  },

  schedule: async (params?: ReportScheduleFilters): Promise<ReportScheduleData> => {
    const { data } = await apiClient.get<{ data: ReportScheduleData }>(`${base}/schedule`, { params })
    return data.data
  },

  gradeSheet: async (params?: ReportGradeSheetFilters): Promise<ReportGradeSheetData> => {
    const { data } = await apiClient.get<{ data: ReportGradeSheetData }>(`${base}/grade-sheet`, { params })
    return data.data
  },

  order: async (params?: ReportOrderFilters): Promise<ReportOrderData> => {
    const { data } = await apiClient.get<{ data: ReportOrderData }>(`${base}/order`, { params })
    return data.data
  },
}
