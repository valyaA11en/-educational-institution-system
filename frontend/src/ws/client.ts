import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import type { WebSocketPayloadDTO } from '../api/dto'
import { realtimeApi } from '../api/realtime'

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo
  }
}

window.Pusher = Pusher

export type WebSocketChannel = 'user' | 'group' | 'teacher' | 'chat'

export type EventHandler = (payload: WebSocketPayloadDTO) => void

interface ChannelSubscription {
  channel: string
  echoChannel: any
  handlers: Map<string, EventHandler[]>
}

class WebSocketClient {
  private echo: Echo | null = null
  private subscriptions: Map<string, ChannelSubscription> = new Map()
  private eventHandlers: Map<string, EventHandler[]> = new Map()
  private lastEventId: number | null = null
  private reconnectAttempts = 0
  private maxReconnectAttempts = 10
  private reconnectTimeout: number | null = null
  private isConnecting = false
  private isConnected = false
  private wsUrl: string | null = null

  /**
   * Connect to WebSocket server
   */
  connect(url?: string): void {
    if (this.isConnecting || this.isConnected) {
      return
    }

    this.wsUrl = url || this.getDefaultUrl()
    this.isConnecting = true

    try {
      const config = this.getEchoConfig()
      this.echo = new Echo(config)

      // Setup connection event handlers
      this.setupConnectionHandlers()

      this.isConnected = true
      this.isConnecting = false
      this.reconnectAttempts = 0

      // Request replay for missed events
      this.requestReplay()
    } catch (error) {
      console.error('WebSocket connection error:', error)
      this.isConnecting = false
      this.scheduleReconnect()
    }
  }

  /**
   * Disconnect from WebSocket server
   */
  disconnect(): void {
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout)
      this.reconnectTimeout = null
    }

    this.subscriptions.forEach((sub) => {
      this.echo?.leave(sub.channel)
    })
    this.subscriptions.clear()

    if (this.echo) {
      this.echo.disconnect()
      this.echo = null
    }

    this.isConnected = false
    this.isConnecting = false
    this.reconnectAttempts = 0
  }

  /**
   * Subscribe to channel
   */
  subscribe(channel: string): void {
    if (this.subscriptions.has(channel)) {
      return
    }

    if (!this.echo) {
      console.warn('WebSocket not connected. Call connect() first.')
      return
    }

    try {
      const echoChannel = this.echo.private(channel)
      
      // Listen to all events on this channel
      echoChannel.listen('.event', (payload: WebSocketPayloadDTO) => {
        this.handleEvent(payload)
      })

      this.subscriptions.set(channel, {
        channel,
        echoChannel,
        handlers: new Map(),
      })
    } catch (error) {
      console.error(`Failed to subscribe to channel ${channel}:`, error)
    }
  }

  /**
   * Unsubscribe from channel
   */
  unsubscribe(channel: string): void {
    const subscription = this.subscriptions.get(channel)
    if (!subscription) {
      return
    }

    try {
      this.echo?.leave(channel)
      this.subscriptions.delete(channel)
    } catch (error) {
      console.error(`Failed to unsubscribe from channel ${channel}:`, error)
    }
  }

  /**
   * Register event handler
   */
  on(eventType: string, handler: EventHandler): void {
    if (!this.eventHandlers.has(eventType)) {
      this.eventHandlers.set(eventType, [])
    }
    this.eventHandlers.get(eventType)!.push(handler)
  }

  /**
   * Remove event handler
   */
  off(eventType: string, handler?: EventHandler): void {
    if (!handler) {
      // Remove all handlers for this event type
      this.eventHandlers.delete(eventType)
      return
    }

    const handlers = this.eventHandlers.get(eventType)
    if (handlers) {
      const index = handlers.indexOf(handler)
      if (index > -1) {
        handlers.splice(index, 1)
      }
      if (handlers.length === 0) {
        this.eventHandlers.delete(eventType)
      }
    }
  }

  /**
   * Acknowledge event
   */
  async ack(eventId: number): Promise<void> {
    try {
      await realtimeApi.ack(eventId)
    } catch (error) {
      console.error(`Failed to acknowledge event ${eventId}:`, error)
    }
  }

  /**
   * Get connection status
   */
  get connected(): boolean {
    return this.isConnected && !!this.echo
  }

  /**
   * Get last event ID
   */
  getLastEventId(): number | null {
    return this.lastEventId
  }

  private getDefaultUrl(): string {
    const defaultHost = window.location.hostname || 'localhost'
    const wsUrl = import.meta.env.VITE_WEBSOCKET_URL as string | undefined

    let wsHost = import.meta.env.VITE_WEBSOCKET_HOST || defaultHost
    let wsPort = Number(import.meta.env.VITE_WEBSOCKET_PORT || 6001)

    if (wsUrl) {
      try {
        const url = new URL(wsUrl)
        wsHost = url.hostname
        wsPort = Number(url.port || wsPort)
      } catch {
        // ignore invalid URL
      }
    }

    return `ws://${wsHost}:${wsPort}`
  }

  private getEchoConfig() {
    const defaultHost = window.location.hostname || 'localhost'
    const wsUrl = import.meta.env.VITE_WEBSOCKET_URL as string | undefined

    let wsHost = import.meta.env.VITE_WEBSOCKET_HOST || defaultHost
    let wsPort = Number(import.meta.env.VITE_WEBSOCKET_PORT || 6001)

    if (wsUrl) {
      try {
        const url = new URL(wsUrl)
        wsHost = url.hostname
        wsPort = Number(url.port || wsPort)
      } catch {
        // ignore invalid URL
      }
    }

    const getAuthHeaders = () => {
      const token = localStorage.getItem('access_token')
      return token ? { Authorization: `Bearer ${token}` } : {}
    }

    return {
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY || 'local-key',
      wsHost,
      wsPort,
      wssPort: wsPort,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
      authEndpoint: `${import.meta.env.VITE_API_URL || '/api'}/broadcasting/auth`,
      auth: {
        headers: getAuthHeaders(),
      },
    }
  }

  private setupConnectionHandlers(): void {
    if (!this.echo) {
      return
    }

    const socket = this.echo.connector?.socket

    if (socket) {
      socket.on('connect', () => {
        this.isConnected = true
        this.isConnecting = false
        this.reconnectAttempts = 0

        // Resubscribe to all channels
        const channels = Array.from(this.subscriptions.keys())
        this.subscriptions.clear()
        channels.forEach((channel) => {
          this.subscribe(channel)
        })

        // Request replay for missed events
        this.requestReplay()
      })

      socket.on('disconnect', () => {
        this.isConnected = false
        this.scheduleReconnect()
      })

      socket.on('error', (error: any) => {
        console.error('WebSocket error:', error)
        this.isConnected = false
        this.scheduleReconnect()
      })
    }

    // Also check Pusher connection state
    const pusher = this.echo.connector?.pusher
    if (pusher) {
      pusher.connection.bind('connected', () => {
        this.isConnected = true
        this.isConnecting = false
        this.reconnectAttempts = 0
      })

      pusher.connection.bind('disconnected', () => {
        this.isConnected = false
        this.scheduleReconnect()
      })

      pusher.connection.bind('error', (error: any) => {
        console.error('Pusher connection error:', error)
        this.isConnected = false
        this.scheduleReconnect()
      })
    }
  }

  private handleEvent(payload: WebSocketPayloadDTO): void {
    // Update last event ID
    if (payload.eventId && (!this.lastEventId || payload.eventId > this.lastEventId)) {
      this.lastEventId = payload.eventId
      localStorage.setItem('ws_last_event_id', String(this.lastEventId))
    }

    // Call handlers for specific event type
    const handlers = this.eventHandlers.get(payload.eventType) || []
    handlers.forEach((handler) => {
      try {
        handler(payload)
      } catch (error) {
        console.error(`Error in event handler for ${payload.eventType}:`, error)
      }
    })

    // Call global handlers (for all events)
    const globalHandlers = this.eventHandlers.get('*') || []
    globalHandlers.forEach((handler) => {
      try {
        handler(payload)
      } catch (error) {
        console.error('Error in global event handler:', error)
      }
    })
  }

  private scheduleReconnect(): void {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      console.error('Max reconnect attempts reached')
      return
    }

    if (this.reconnectTimeout) {
      return
    }

    // Exponential backoff: 1s, 2s, 4s, 8s, 16s, 32s, 60s (max)
    const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 60000)
    this.reconnectAttempts++

    this.reconnectTimeout = window.setTimeout(() => {
      this.reconnectTimeout = null
      if (!this.isConnected && !this.isConnecting) {
        console.log(`Reconnecting... (attempt ${this.reconnectAttempts})`)
        this.connect(this.wsUrl || undefined)
      }
    }, delay)
  }

  private async requestReplay(): Promise<void> {
    if (!this.lastEventId) {
      // Try to load from localStorage
      const stored = localStorage.getItem('ws_last_event_id')
      if (stored) {
        this.lastEventId = parseInt(stored, 10)
      }
    }

    if (!this.lastEventId) {
      return
    }

    try {
      // Request replay for all subscribed channels
      const channels = Array.from(this.subscriptions.keys())
      
      for (const channel of channels) {
        const events = await realtimeApi.replay(this.lastEventId, channel)
        
        // Process replayed events
        events.forEach((event) => {
          this.handleEvent(event)
        })
      }
    } catch (error) {
      console.error('Failed to request replay:', error)
    }
  }
}

// Create singleton instance
const wsClient = new WebSocketClient()

// Export singleton instance
export default wsClient

// Also export class for testing
export { WebSocketClient }
