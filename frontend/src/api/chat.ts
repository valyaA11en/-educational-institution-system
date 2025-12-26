import apiClient from './client'

export interface ChatThreadDTO {
  id: number
  name: string
  lastMessage: string | null
  unreadCount: number
  pinned: boolean
}

export interface ChatMessageDTO {
  id: number
  threadId: number
  userId: number
  text: string
  attachments: any[] | null
  createdAt: string
}

export interface ChatReportDTO {
  id: number
  thread_id: number
  message_id: number
  reported_by: number
  reason: string
  status: 'open' | 'reviewed' | 'closed'
  created_at: string
  updated_at: string
  thread?: ChatThreadDTO
  message?: ChatMessageDTO
  reporter?: {
    id: number
    fio: string
  }
}

export interface ChatThreadSettingsDTO {
  id: number
  thread_id: number
  mode: 'standard' | 'announcements'
  quiet_hours: Array<{ start: string; end: string }> | null
  attachments_enabled: boolean
  created_at: string
  updated_at: string
}

export const chatApi = {
  async listThreads(): Promise<ChatThreadDTO[]> {
    const response = await apiClient.get<ChatThreadDTO[]>('/v1/chats/threads')
    return response.data
  },

  async getMessages(threadId: number): Promise<ChatMessageDTO[]> {
    const response = await apiClient.get<ChatMessageDTO[]>(
      `/v1/chats/threads/${threadId}/messages`,
    )
    return response.data
  },

  async sendMessage(
    threadId: number,
    payload: { text: string; attachments?: File[] },
  ): Promise<ChatMessageDTO> {
    // TODO: реализовать реальную загрузку файлов (FormData)
    const response = await apiClient.post<ChatMessageDTO>(
      `/v1/chats/threads/${threadId}/messages`,
      {
        text: payload.text,
        // attachments: TODO
      },
    )
    return response.data
  },

  async reportMessage(threadId: number, messageId: number, reason: string): Promise<ChatReportDTO> {
    const response = await apiClient.post<ChatReportDTO>(
      `/v1/chats/${threadId}/report-message`,
      {
        messageId,
        reason,
      },
    )
    return response.data
  },

  async getSettings(threadId: number): Promise<ChatThreadSettingsDTO | null> {
    try {
      const response = await apiClient.get<ChatThreadSettingsDTO>(
        `/v1/chats/${threadId}/settings`,
      )
      return response.data
    } catch (error: any) {
      if (error.response?.status === 404) {
        return null
      }
      throw error
    }
  },

  async updateSettings(
    threadId: number,
    settings: {
      mode?: 'standard' | 'announcements'
      quiet_hours?: Array<{ start: string; end: string }> | null
      attachments_enabled?: boolean
    },
  ): Promise<ChatThreadSettingsDTO> {
    const response = await apiClient.patch<ChatThreadSettingsDTO>(
      `/v1/chats/${threadId}/settings`,
      settings,
    )
    return response.data
  },

  async getReports(params?: {
    status?: string
    threadId?: number
    per_page?: number
    page?: number
  }): Promise<{ data: ChatReportDTO[]; current_page: number; per_page: number; total: number; last_page: number }> {
    const response = await apiClient.get('/v1/admin/chats/reports', { params })
    return response.data
  },

  async updateReport(id: number, status: 'open' | 'reviewed' | 'closed'): Promise<ChatReportDTO> {
    const response = await apiClient.patch<ChatReportDTO>(`/v1/admin/chats/reports/${id}`, {
      status,
    })
    return response.data
  },
}


