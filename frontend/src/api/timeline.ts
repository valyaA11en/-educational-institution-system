import api from './client'

export interface TimelineEvent {
  id: number
  event_type: string
  event_date: string
  title: string
  description: string | null
  related_entity_type: string | null
  related_entity_id: number | null
  payload: Record<string, any> | null
  created_at: string
}

export interface TimelineParams {
  dateFrom?: string
  dateTo?: string
  event_type?: string | string[]
}

export const timelineApi = {
  getTimeline(studentId: number, params?: TimelineParams) {
    return api.get<{ data: TimelineEvent[] }>(`/v1/students/${studentId}/timeline`, { params })
  },
}

