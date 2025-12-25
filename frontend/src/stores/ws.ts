import { defineStore } from 'pinia'
import wsClient from '../ws/client'
import { useAuthStore } from './auth'
import type { WebSocketPayloadDTO } from '../api/dto'

interface WsState {
  connected: boolean
  subscribedChannels: string[]
}

export const useWsStore = defineStore('ws', {
  state: (): WsState => ({
    connected: false,
    subscribedChannels: [],
  }),

  getters: {
    isConnected: (state): boolean => {
      return state.connected && wsClient.connected
    },
  },

  actions: {
    /**
     * Initialize WebSocket connection
     */
    async init() {
      const auth = useAuthStore()

      if (!auth.isAuthenticated) {
        return
      }

      // Setup event handlers first
      this.setupEventHandlers()

      // Connect if not already connected
      if (!wsClient.connected) {
        wsClient.connect()
      }

      // Wait a bit for connection to establish
      await new Promise((resolve) => setTimeout(resolve, 100))

      // Update connection status
      this.updateConnectionStatus()

      // Subscribe to user channels
      await this.subscribeUserChannels()

      // Setup periodic status check
      this.setupStatusCheck()
    },

    /**
     * Connect to WebSocket
     */
    connect(url?: string) {
      wsClient.connect(url)
      this.updateConnectionStatus()
    },

    /**
     * Disconnect from WebSocket
     */
    disconnect() {
      wsClient.disconnect()
      this.connected = false
      this.subscribedChannels = []
    },

    /**
     * Subscribe to channel
     */
    subscribe(channel: string) {
      if (this.subscribedChannels.includes(channel)) {
        return
      }

      wsClient.subscribe(channel)
      this.subscribedChannels.push(channel)
    },

    /**
     * Unsubscribe from channel
     */
    unsubscribe(channel: string) {
      if (!this.subscribedChannels.includes(channel)) {
        return
      }

      wsClient.unsubscribe(channel)
      this.subscribedChannels = this.subscribedChannels.filter((c) => c !== channel)
    },

    /**
     * Register event handler
     */
    on(eventType: string, handler: (payload: WebSocketPayloadDTO) => void) {
      wsClient.on(eventType, handler)
    },

    /**
     * Remove event handler
     */
    off(eventType: string, handler?: (payload: WebSocketPayloadDTO) => void) {
      wsClient.off(eventType, handler)
    },

    /**
     * Acknowledge event
     */
    async ack(eventId: number) {
      await wsClient.ack(eventId)
    },

    /**
     * Subscribe to user channels based on user context
     */
    async subscribeUserChannels() {
      const auth = useAuthStore()

      if (!auth.user) {
        return
      }

      // Subscribe to user channel
      const userChannel = `user.${auth.user.id}`
      this.subscribe(userChannel)

      // Subscribe to teacher channel if user is a teacher
      // TODO: check if user has teacher role
      const teacherChannel = `teacher.${auth.user.id}`
      this.subscribe(teacherChannel)

      // Subscribe to group channels
      // TODO: load user's groups and subscribe to each
      // Example:
      // const groups = await loadUserGroups()
      // groups.forEach(group => {
      //   this.subscribe(`group.${group.id}`)
      // })
    },

    /**
     * Subscribe to group channel
     */
    subscribeToGroup(groupId: number) {
      const channel = `group.${groupId}`
      this.subscribe(channel)
    },

    /**
     * Unsubscribe from group channel
     */
    unsubscribeFromGroup(groupId: number) {
      const channel = `group.${groupId}`
      this.unsubscribe(channel)
    },

    /**
     * Update connection status
     */
    updateConnectionStatus() {
      this.connected = wsClient.connected
    },

    /**
     * Setup event handlers
     */
    setupEventHandlers() {
      // Listen to all events
      wsClient.on('*', (payload: WebSocketPayloadDTO) => {
        // Auto-acknowledge events
        if (payload.eventId) {
          this.ack(payload.eventId).catch((error) => {
            console.error('Failed to acknowledge event:', error)
          })
        }
      })

      // Listen to specific event types
      wsClient.on('notification.created', async (payload: WebSocketPayloadDTO) => {
        // notification.created: payload.payload == NotificationDTO
        try {
          const { useNotificationsStore } = await import('./notifications')
          const store = useNotificationsStore()
          store.addFromRealtime({ payload: payload.payload })
        } catch (error) {
          console.error('Failed to handle notification.created:', error)
        }
      })

      wsClient.on('chat.message_created', async (payload: WebSocketPayloadDTO) => {
        try {
          const { useChatStore } = await import('./chat')
          const chat = useChatStore()
          chat.handleWsMessage(payload)
        } catch (error) {
          console.error('Failed to handle chat.message_created:', error)
        }
      })

      wsClient.on('schedule.changed', async (payload: WebSocketPayloadDTO) => {
        try {
          const { useScheduleStore } = await import('./schedule')
          const schedule = useScheduleStore()
          await schedule.handleScheduleChanged(payload)
        } catch (error) {
          console.error('Failed to handle schedule.changed:', error)
        }
      })

      // Update connection status when events are received
      wsClient.on('*', () => {
        this.updateConnectionStatus()
      })
    },

    /**
     * Setup periodic status check
     */
    setupStatusCheck() {
      // Check connection status every 5 seconds
      setInterval(() => {
        this.updateConnectionStatus()
      }, 5000)
    },
  },
})