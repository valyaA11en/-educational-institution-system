import apiClient from './client'
import type { WebSocketPayloadDTO } from './dto'

export const realtimeApi = {
  /**
   * Replay events since lastEventId
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
   * Acknowledge event
   */
  async ack(eventId: number): Promise<void> {
    await apiClient.post(`/realtime/events/${eventId}/ack`)
  },
}









