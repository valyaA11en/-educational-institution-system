// WebSocket payload DTO matching backend

export interface WebSocketPayloadDTO {
  eventId: number
  eventType: string
  payload: Record<string, any>
  createdAt: string
}

