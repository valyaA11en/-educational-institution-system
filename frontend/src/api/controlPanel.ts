import api from './client'

export interface CuratorPanelData {
  group: {
    id: number
    name: string
  }
  statistics: {
    students_count: number
    average_grade: number
    attendance_rate: number
  }
  low_performers: Array<{
    id: number
    fio: string
    avg_grade: number
  }>
  low_attendance: Array<{
    id: number
    fio: string
    attendance_rate: number
  }>
}

export interface MethodistPanelData {
  statistics: {
    total_students: number
    total_groups: number
    average_grade_all: number
  }
  low_performing_groups: Array<{
    id: number
    name: string
    students_count: number
    average_grade: number
  }>
  draft_schedules: number
}

export interface HeadmasterPanelData {
  statistics: {
    total_students: number
    total_teachers: number
    total_groups: number
    average_grade: number
    attendance_rate: number
  }
  tasks: {
    active_assignments: number
    pending_submissions: number
    open_tickets: number
  }
}

export const controlPanelApi = {
  getCuratorPanel(groupId: number) {
    return api.get<CuratorPanelData>('/v1/control-panel/curator', {
      params: { group_id: groupId },
    })
  },
  getMethodistPanel() {
    return api.get<MethodistPanelData>('/v1/control-panel/methodist')
  },
  getHeadmasterPanel() {
    return api.get<HeadmasterPanelData>('/v1/control-panel/headmaster')
  },
}


