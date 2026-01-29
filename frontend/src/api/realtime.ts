import apiClient from './client'
import type { WebSocketPayloadDTO } from './dto'

export const realtimeApi = {
  /**
   * Replay events since lastEventId (legacy for notification deliveries)
   */
  async replay(since: string | number, channel?: string): Promise<WebSocketPayloadDTO[]> {
    const params: Record<string, string> = {
      since: String(since),
    }
    if (channel) {
      params.channel = channel
    }

    const response = await apiClient.get<WebSocketPayloadDTO[]>('/realtime/replay', { params })
    return response.data
  },

  /**
   * Acknowledge event (legacy for notification deliveries)
   */
  async ack(eventId: number): Promise<void> {
    await apiClient.post(`/realtime/events/${eventId}/ack`)
  },

  /**
   * Acknowledge WebSocket delivery (for ws_event_deliveries)
   */
  async wsAck(deliveryId: number): Promise<void> {
    await apiClient.post('/v1/websocket/ws-ack', { deliveryId })
  },

  /**
   * Replay WebSocket events (for ws_event_deliveries)
   */
  async wsReplay(afterDeliveryId?: number): Promise<any[]> {
    const params: Record<string, string | number> = {}
    if (afterDeliveryId) {
      params.afterDeliveryId = afterDeliveryId
    }

    const response = await apiClient.get<{ events: any[] }>('/v1/websocket/ws-replay', { params })
    return response.data.events || []
  },
}









