// WebSocket payload DTO matching backend

export interface WebSocketPayloadDTO {
  eventId?: number // Legacy for notification deliveries
  deliveryId?: number // For ws_event_deliveries
  eventType: string
  payload: Record<string, any>
  createdAt?: string
  timestamp?: string
  type?: string // Event type from server
}









