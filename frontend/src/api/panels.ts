import api from './client'

export interface CuratorPanelData {
  groups: Array<{
    id: number
    name: string
    code: string | null
  }>
  attendance: any[]
  overdue_assignments: Array<{
    group_id: number
    count: number
    assignments: Array<{
      id: number
      title: string
      subject: string | null
      due_at: string
    }>
  }>
  risks: {
    red: Array<{
      id: number
      student_id: number
      student_fio: string | null
      risk_type: string
      score: number
      calculated_at: string | null
    }>
    yellow: Array<{
      id: number
      student_id: number
      student_fio: string | null
      risk_type: string
      score: number
      calculated_at: string | null
    }>
  }
  group_averages: Array<{
    group_id: number
    group_name: string
    average_grade: number | null
    students_count: number
  }>
}

export interface MethodistPanelData {
  ktp_completion: Array<{
    id: number
    name: string
    subject: string | null
    group: string | null
    total_topics: number
    completed_topics: number
    completion_percent: number
  }>
  failed_topics: Array<{
    id: number
    subject: string | null
    topic: string | null
    fail_percent: number
    students_total: number
    students_failed: number
    calculated_at: string
  }>
  check_delays: Array<{
    id: number
    title: string
    subject: string | null
    teacher: string | null
    due_at: string
    days_overdue: number
  }>
  discipline_risks: Array<{
    subject_id: number
    subject_name: string | null
    avg_fail_percent: number
    topics_count: number
  }>
}

export interface PrincipalPanelData {
  total_risks: {
    red: number
    yellow: number
    total: number
  }
  groups_with_problems: {
    total_groups: number
    groups_with_problems: number
    percent: number
  }
  teacher_workload: Array<{
    teacher_id: number
    teacher_fio: string | null
    lessons_count: number
  }>
  documents: {
    pending: number
    overdue: number
    total_pending: number
  }
}

export const panelsApi = {
  async getCuratorPanel() {
    const response = await api.get<{ data: CuratorPanelData }>('/v1/panels/curator')
    return response.data
  },
  async getMethodistPanel() {
    const response = await api.get<{ data: MethodistPanelData }>('/v1/panels/methodist')
    return response.data
  },
  async getPrincipalPanel() {
    const response = await api.get<{ data: PrincipalPanelData }>('/v1/panels/principal')
    return response.data
  },
}


