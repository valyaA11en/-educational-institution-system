import api from './client'

export interface Task {
  type: string
  title: string
  description: string
  priority: 'urgent' | 'high' | 'medium' | 'low'
  due_at?: string
  action_url: string
  entity_type: string | null
  entity_id: number | null
}

export interface TodayTasksResponse {
  data: Task[]
  count: number
  urgent_count: number
}

// Новый формат ответа от AssistantService
export interface LessonToday {
  id: number
  subject?: string
  room?: string
  time?: string
  teacher?: string
  group?: string
}

export interface Deadline {
  id: number
  title: string
  subject?: string
  due_at: string
  hours_left?: number
  days_left?: number
}

export interface OverdueAssignment {
  id: number
  title: string
  subject?: string
  due_at: string
  days_overdue: number
}

export interface Risk {
  id: number
  level: string
  risk_type: string
  score: number
  calculated_at?: string
  student_id?: number
  student_fio?: string
}

export interface UnreadMaterial {
  id: number
  title: string
  subject?: string
  created_at: string
}

export interface UncheckedSubmission {
  id: number
  title: string
  subject?: string
  due_at?: string
}

export interface LessonWithoutGrade {
  id: number
  date: string
  topic?: string
  subject?: string
  group?: string
}

export interface StudentWithRedRisk {
  id: number
  fio?: string
  risk_type: string
  score: number
}

export interface NewRisk {
  id: number
  student_id: number
  student_fio?: string
  level: string
  risk_type: string
  calculated_at?: string
}

export interface GroupOverdueAssignment {
  group_id: number
  count: number
  assignments: Array<{
    id: number
    title: string
    subject?: string
  }>
}

export interface LowAverageStudent {
  id: number
  fio?: string
  average_grade: number
}

export interface PendingDocument {
  id: number
  type: string
  number: string
  date: string
  created_by?: string
  pending_routes: number
}

export interface OverdueTicket {
  id: number
  title: string
  priority: string
  status: string
  sla_due_at?: string
  resolution_due_at?: string
  created_by?: string
}

export interface ScheduleConflict {
  item1_id: number
  item2_id: number
  date: string
  type: string
}

export interface SystemAlert {
  type: string
  message: string
  severity: string
}

export interface StudentTodayData {
  lessons_today: LessonToday[]
  deadlines_24h: Deadline[]
  deadlines_3d: Deadline[]
  overdue_assignments: OverdueAssignment[]
  risks: Risk[]
  unread_materials: UnreadMaterial[]
}

export interface TeacherTodayData {
  lessons_today: LessonToday[]
  unchecked_submissions: UncheckedSubmission[]
  lessons_without_grades: LessonWithoutGrade[]
  students_with_red_risk: StudentWithRedRisk[]
}

export interface CuratorTodayData {
  attendance_today: any[]
  new_risks: NewRisk[]
  group_overdue_assignments: GroupOverdueAssignment[]
  low_average_students: LowAverageStudent[]
}

export interface AdminTodayData {
  documents_pending_approval: PendingDocument[]
  tickets_sla_overdue: OverdueTicket[]
  schedule_conflicts: ScheduleConflict[]
  system_alerts: SystemAlert[]
}

export type TodayData = StudentTodayData | TeacherTodayData | CuratorTodayData | AdminTodayData

export interface AssistantTodayResponse {
  data: TodayData
  role: 'student' | 'teacher' | 'curator' | 'admin' | 'unknown'
}

export const assistantApi = {
  getTodayTasks() {
    return api.get<TodayTasksResponse>('/v1/assistant/today')
  },
  getToday() {
    return api.get<AssistantTodayResponse>('/v1/assistant/today')
  },
}


