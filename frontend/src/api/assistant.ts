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

export const assistantApi = {
  getTodayTasks() {
    return api.get<TodayTasksResponse>('/v1/assistant/today')
  },
}

