import apiClient from './client'

export interface ScheduleItemDTO {
  id: number
  date: string
  timeSlotId: number
  groupId: number
  subgroupId: number | null
  subjectId: number
  teacherUserId: number
  roomId: number
}

export interface TimeSlotDTO {
  id: number
  name: string
  startTime: string
  endTime: string
}

export interface ScheduleFilter {
  mode: 'group' | 'teacher' | 'room'
  groupId?: number | null
  teacherId?: number | null
  roomId?: number | null
  weekStart: string // ISO date
}

export const scheduleApi = {
  async listItems(filter: ScheduleFilter): Promise<ScheduleItemDTO[]> {
    const params: Record<string, string> = {
      mode: filter.mode,
      week_start: filter.weekStart,
    }
    if (filter.groupId) params.group_id = String(filter.groupId)
    if (filter.teacherId) params.teacher_id = String(filter.teacherId)
    if (filter.roomId) params.room_id = String(filter.roomId)

    const response = await apiClient.get<ScheduleItemDTO[]>('/v1/schedule/items', {
      params,
    })
    return response.data
  },

  async createItem(
    payload: {
      groupId: number
      subgroupId?: number | null
      subjectId: number
      teacherUserId: number
      roomId: number
      date: string
      timeSlotId: number
    },
    options?: { force?: boolean; overrideReason?: string },
  ): Promise<ScheduleItemDTO> {
    const body: any = {
      group_id: payload.groupId,
      subgroup_id: payload.subgroupId ?? null,
      subject_id: payload.subjectId,
      teacher_user_id: payload.teacherUserId,
      room_id: payload.roomId,
      date: payload.date,
      time_slot_id: payload.timeSlotId,
    }

    if (options?.force) {
      body.force = true
      body.override_reason = options.overrideReason ?? null
    }

    const response = await apiClient.post<ScheduleItemDTO>('/v1/schedule/items', body)
    return response.data
  },

  async suggestRoom(payload: {
    date: string
    timeSlotId: number
    groupId: number
  }): Promise<{ roomId: number; roomName?: string }> {
    // TODO: убедиться, что backend реализует /v1/schedule/suggest-room
    const response = await apiClient.post<{ roomId: number; roomName?: string }>(
      '/v1/schedule/suggest-room',
      {
        date: payload.date,
        time_slot_id: payload.timeSlotId,
        group_id: payload.groupId,
      },
    )
    return response.data
  },
}


