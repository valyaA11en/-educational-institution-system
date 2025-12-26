import apiClient from './client'

export interface RuleDTO {
  id: number
  name: string
  enabled: boolean
  conditions_json: {
    event_type: string
    filters?: {
      groupId?: number
      role?: string
      subjectId?: number
    }
    thresholds?: {
      avg?: {
        operator: string
        value: number
        period_days?: number
      }
      absences?: {
        operator: string
        value: number
        period_days?: number
      }
      ungraded_days?: {
        operator: string
        value: number
      }
      debts?: {
        operator: string
        value: number
      }
    }
    [key: string]: any
  }
  actions_json: Array<{
    type: string
    [key: string]: any
  }>
  created_at: string
}

export interface RuleListResponse {
  data: RuleDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const rulesApi = {
  list: async (params?: {
    scope?: string
    enabled?: boolean
    page?: number
    per_page?: number
    q?: string
  }): Promise<RuleListResponse> => {
    const response = await apiClient.get('/v1/admin/rules', { params })
    return response.data
  },

  get: async (id: number): Promise<RuleDTO> => {
    const response = await apiClient.get(`/v1/admin/rules/${id}`)
    return response.data
  },

  create: async (data: Omit<RuleDTO, 'id' | 'created_at'>): Promise<RuleDTO> => {
    const response = await apiClient.post('/v1/admin/rules', data)
    return response.data
  },

  update: async (id: number, data: Partial<Omit<RuleDTO, 'id' | 'created_at'>>): Promise<RuleDTO> => {
    const response = await apiClient.patch(`/v1/admin/rules/${id}`, data)
    return response.data
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/v1/admin/rules/${id}`)
  },

  toggle: async (id: number): Promise<RuleDTO> => {
    const response = await apiClient.post(`/v1/admin/rules/${id}/toggle`)
    return response.data
  },
}

// Supported event types
export const EVENT_TYPES = [
  { value: 'schedule.changed', title: 'Расписание изменено' },
  { value: 'grade.created', title: 'Оценка создана' },
  { value: 'assignment.created', title: 'Задание создано' },
  { value: 'assignment.due_soon', title: 'Скоро дедлайн задания' },
  { value: 'submission.status_changed', title: 'Статус решения изменен' },
  { value: 'document.status_changed', title: 'Статус документа изменен' },
  { value: 'chat.message_created', title: 'Сообщение в чате создано' },
  { value: 'notification.created', title: 'Уведомление создано' },
  { value: 'ticket.created', title: 'Тикет создан' },
  { value: 'ticket.updated', title: 'Тикет обновлен' },
  { value: 'ticket.status_changed', title: 'Статус тикета изменен' },
  { value: 'ticket.priority_changed', title: 'Приоритет тикета изменен' },
  { value: 'ticket.assigned', title: 'Тикет назначен' },
  { value: 'ticket.message_created', title: 'Сообщение в тикете создано' },
  { value: 'ticket.first_response', title: 'Первый ответ на тикет' },
  { value: 'ticket.resolved', title: 'Тикет решен' },
  { value: 'ticket.overdue', title: 'Тикет просрочен' },
  { value: 'ticket.escalated', title: 'Тикет эскалирован' },
]

// JSON Templates
export const CONDITION_TEMPLATE = {
  event_type: 'grade.created',
  filters: {
    groupId: null,
    role: null,
    subjectId: null,
  },
  thresholds: {
    avg: {
      operator: 'less_than_or_equal',
      value: 2.5,
      period_days: 30,
    },
  },
}

export const ACTION_TEMPLATE = {
  type: 'create_notification',
  to: 'curator',
  notification_type: 'grade.low_average',
  message: 'Средняя оценка студента за последние 30 дней ниже 2.5',
  channel: 'in_app',
  payload: {},
}
