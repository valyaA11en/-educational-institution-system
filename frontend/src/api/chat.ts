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
}


