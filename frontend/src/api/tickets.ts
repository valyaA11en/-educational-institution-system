import apiClient from './client'

export interface TicketDTO {
  id: number
  title: string
  description: string
  category: string
  priority: 'low' | 'normal' | 'medium' | 'high' | 'critical'
  status: 'open' | 'in_progress' | 'resolved' | 'closed'
  created_by: number
  assigned_to: number | null
  sla_hours: number
  first_response_due_at: string | null
  resolution_due_at: string | null
  first_response_at: string | null
  resolved_at: string | null
  is_overdue: boolean
  created_at: string
  updated_at: string
  creator?: {
    id: number
    fio: string
    email: string
  }
  assignee?: {
    id: number
    fio: string
    email: string
  } | null
}

export interface TicketMessageDTO {
  id: number
  ticket_id: number
  user_id: number
  text: string
  is_internal: boolean
  created_at: string
  updated_at: string
  user?: {
    id: number
    fio: string
    email: string
  }
}

export interface TicketListResponse {
  data: TicketDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface OverdueTicketDTO extends TicketDTO {
  overdue_hours: number
  overdue_minutes: number
}

export interface OverdueTicketListResponse {
  data: OverdueTicketDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const ticketsApi = {
  list: async (params?: {
    status?: string
    priority?: string
    assigned_to?: number
    created_by?: number
    overdue?: boolean
    page?: number
    per_page?: number
  }): Promise<TicketListResponse> => {
    const response = await apiClient.get('/v1/tickets', { params })
    return response.data
  },

  get: async (id: number): Promise<TicketDTO> => {
    const response = await apiClient.get(`/v1/tickets/${id}`)
    return response.data
  },

  create: async (data: {
    title: string
    description: string
    category: string
    priority: 'low' | 'normal' | 'medium' | 'high' | 'critical'
    assigned_to?: number | null
    attachments?: number[] // File IDs
  }): Promise<TicketDTO> => {
    const response = await apiClient.post('/v1/tickets', data)
    return response.data
  },

  update: async (
    id: number,
    data: {
      status?: 'open' | 'in_progress' | 'resolved' | 'closed'
      assigned_to?: number | null
      priority?: 'low' | 'normal' | 'medium' | 'high' | 'critical'
    }
  ): Promise<TicketDTO> => {
    const response = await apiClient.put(`/v1/tickets/${id}`, data)
    return response.data
  },

  getMessages: async (id: number): Promise<TicketMessageDTO[]> => {
    const response = await apiClient.get(`/v1/tickets/${id}/messages`)
    return response.data
  },

  sendMessage: async (
    id: number,
    data: {
      text: string
      is_internal?: boolean
    }
  ): Promise<TicketMessageDTO> => {
    const response = await apiClient.post(`/v1/tickets/${id}/messages`, data)
    return response.data
  },

  overdue: async (params?: {
    status?: string
    priority?: string
    assigned_to?: number
    created_by?: number
    category?: string
    overdue_hours_min?: number
    overdue_hours_max?: number
    sort_by?: string
    sort_order?: 'asc' | 'desc'
    page?: number
    per_page?: number
  }): Promise<OverdueTicketListResponse> => {
    const response = await apiClient.get('/v1/admin/tickets/overdue', { params })
    return response.data
  },
}


