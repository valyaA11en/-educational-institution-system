import { defineStore } from 'pinia'
import { scheduleApi, type ScheduleItemDTO, type ScheduleFilter } from '../api/schedule'
import type { WebSocketPayloadDTO } from '../api/dto'

type ViewMode = 'group' | 'teacher' | 'room'

interface ScheduleState {
  mode: ViewMode
  weekStart: string // ISO
  groupId: number | null
  teacherId: number | null
  roomId: number | null
  items: ScheduleItemDTO[]
  loading: boolean
  conflicts: any[] | null
}

export const useScheduleStore = defineStore('schedule', {
  state: (): ScheduleState => ({
    mode: 'group',
    weekStart: getWeekStart(new Date()).toISOString().slice(0, 10),
    groupId: null,
    teacherId: null,
    roomId: null,
    items: [],
    loading: false,
    conflicts: null,
  }),

  getters: {
    filter(state): ScheduleFilter {
      return {
        mode: state.mode,
        weekStart: state.weekStart,
        groupId: state.groupId ?? undefined,
        teacherId: state.teacherId ?? undefined,
        roomId: state.roomId ?? undefined,
      }
    },
  },

  actions: {
    async load() {
      if (this.loading) return
      this.loading = true
      try {
        this.items = await scheduleApi.listItems(this.filter)
      } finally {
        this.loading = false
      }
    },

    async createItem(payload: {
      groupId: number
      subgroupId?: number | null
      subjectId: number
      teacherUserId: number
      roomId: number
      date: string
      timeSlotId: number
    }) {
      try {
        const item = await scheduleApi.createItem(payload)
        this.items.push(item)
        this.conflicts = null
      } catch (error: any) {
        if (error.response?.status === 409) {
          this.conflicts = error.response.data?.conflicts ?? []
        }
        throw error
      }
    },

    async forceCreateItem(
      payload: {
        groupId: number
        subgroupId?: number | null
        subjectId: number
        teacherUserId: number
        roomId: number
        date: string
        timeSlotId: number
      },
      overrideReason: string,
    ) {
      const item = await scheduleApi.createItem(payload, {
        force: true,
        overrideReason,
      })
      this.items.push(item)
      this.conflicts = null
    },

    async handleScheduleChanged(payload: WebSocketPayloadDTO) {
      const p = payload.payload || {}
      const groupId: number | undefined = p.group_id ?? p.groupId
      const teacherId: number | undefined = p.teacher_id ?? p.teacherId
      const roomId: number | undefined = p.room_id ?? p.roomId

      // Если событие не относится к текущему фильтру — игнорируем
      if (this.mode === 'group' && this.groupId && groupId !== this.groupId) return
      if (this.mode === 'teacher' && this.teacherId && teacherId !== this.teacherId) return
      if (this.mode === 'room' && this.roomId && roomId !== this.roomId) return

      // Пока что просто перезагружаем данные
      await this.load()
    },
  },
})

function getWeekStart(date: Date): Date {
  const d = new Date(date)
  const day = d.getDay() || 7 // 1..7, где 1 — понедельник
  if (day !== 1) {
    d.setHours(-24 * (day - 1), 0, 0, 0)
  } else {
    d.setHours(0, 0, 0, 0)
  }
  return d
}



