import { defineStore } from 'pinia'
import { chatApi, type ChatThreadDTO, type ChatMessageDTO } from '../api/chat'
import type { WebSocketPayloadDTO } from '../api/dto'

interface ChatState {
  threads: ChatThreadDTO[]
  threadsLoading: boolean
  threadsSearch: string
  currentThreadId: number | null
  messages: Record<number, ChatMessageDTO[]>
  messagesLoading: boolean
  sending: boolean
  typingUsers: Record<number, string[]> // threadId -> user names (каркас)
}

export const useChatStore = defineStore('chat', {
  state: (): ChatState => ({
    threads: [],
    threadsLoading: false,
    threadsSearch: '',
    currentThreadId: null,
    messages: {},
    messagesLoading: false,
    sending: false,
    typingUsers: {},
  }),

  getters: {
    filteredThreads(state): ChatThreadDTO[] {
      const q = state.threadsSearch.trim().toLowerCase()
      if (!q) return state.threads
      return state.threads.filter((t) => t.name.toLowerCase().includes(q))
    },

    currentThread(state): ChatThreadDTO | null {
      return state.threads.find((t) => t.id === state.currentThreadId) ?? null
    },

    currentMessages(state): ChatMessageDTO[] {
      if (!state.currentThreadId) return []
      return state.messages[state.currentThreadId] ?? []
    },
  },

  actions: {
    async loadThreads() {
      if (this.threadsLoading) return
      this.threadsLoading = true
      try {
        this.threads = await chatApi.listThreads()
        if (!this.currentThreadId && this.threads.length > 0) {
          this.selectThread(this.threads[0].id)
        }
      } finally {
        this.threadsLoading = false
      }
    },

    async selectThread(id: number) {
      if (this.currentThreadId === id) return
      this.currentThreadId = id
      await this.loadMessages(id)
      this.resetUnread(id)
    },

    async loadMessages(threadId: number) {
      this.messagesLoading = true
      try {
        const data = await chatApi.getMessages(threadId)
        this.messages = {
          ...this.messages,
          [threadId]: data,
        }
      } finally {
        this.messagesLoading = false
      }
    },

    async sendMessage(text: string) {
      if (!this.currentThreadId || !text.trim()) return
      if (this.sending) return

      this.sending = true
      try {
        const msg = await chatApi.sendMessage(this.currentThreadId, { text })
        if (!this.messages[this.currentThreadId]) {
          this.messages[this.currentThreadId] = []
        }
        this.messages[this.currentThreadId].push(msg)
      } finally {
        this.sending = false
      }
    },

    handleWsMessage(payload: WebSocketPayloadDTO) {
      const p = payload.payload || {}
      const threadId: number | undefined = p.thread_id ?? p.threadId
      const text: string | undefined = p.text

      if (!threadId || !text) return

      const message: ChatMessageDTO = {
        id: p.message_id ?? payload.eventId,
        threadId,
        userId: p.user_id ?? 0,
        text,
        attachments: p.attachments ?? null,
        createdAt: p.created_at ?? payload.createdAt,
      }

      if (!this.messages[threadId]) {
        this.messages[threadId] = []
      }
      this.messages[threadId].push(message)

      // Обновить lastMessage и unreadCount у треда
      const thread = this.threads.find((t) => t.id === threadId)
      if (thread) {
        thread.lastMessage = text
        if (this.currentThreadId !== threadId) {
          thread.unreadCount = (thread.unreadCount ?? 0) + 1
        }
      }
    },

    resetUnread(threadId: number) {
      const thread = this.threads.find((t) => t.id === threadId)
      if (thread) {
        thread.unreadCount = 0
      }
    },
  },
})



