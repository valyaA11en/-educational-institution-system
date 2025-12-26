import apiClient from './client'

export interface ScheduleVersionDTO {
  id: number
  term_id: number
  status: 'draft' | 'published' | 'archived'
  created_by: number
  published_at: string | null
  created_at: string
  updated_at: string
  creator?: {
    id: number
    fio: string
  }
}

export interface ScheduleItemDTO {
  id: number
  version_id: number
  date: string
  time_slot_id: number
  group_id: number
  subgroup_id: number | null
  subject_id: number
  teacher_user_id: number
  room_id: number
  group?: {
    id: number
    name: string
  }
  subject?: {
    id: number
    name: string
  }
  teacher?: {
    id: number
    fio: string
  }
  room?: {
    id: number
    name: string
  }
}

export const scheduleApi = {
  getVersions: async (params?: {
    status?: string
    term_id?: number
    page?: number
    per_page?: number
  }): Promise<{
    data: ScheduleVersionDTO[]
    current_page: number
    per_page: number
    total: number
    last_page: number
  }> => {
    const response = await apiClient.get('/v1/schedule/versions', { params })
    return response.data
  },

  createVersion: async (payload: {
    term_id: number
    status?: 'draft' | 'published' | 'archived'
  }): Promise<ScheduleVersionDTO> => {
    const response = await apiClient.post('/v1/schedule/versions', payload)
    return response.data
  },

  publishVersion: async (id: number): Promise<ScheduleVersionDTO> => {
    const response = await apiClient.post(`/v1/schedule/versions/${id}/publish`)
    return response.data
  },

  archiveVersion: async (id: number): Promise<ScheduleVersionDTO> => {
    const response = await apiClient.post(`/v1/schedule/versions/${id}/archive`)
    return response.data
  },

  getChangelog: async (id: number, params?: {
    item_id?: number
  }): Promise<{
    data: Array<{
      id: number
      action: string
      before_json: any
      after_json: any
      created_at: string
      changer?: {
        id: number
        fio: string
      }
      schedule_item?: ScheduleItemDTO
    }>
  }> => {
    const response = await apiClient.get(`/v1/schedule/versions/${id}/changelog`, { params })
    return response.data
  },

  compareVersions: async (payload: {
    version1_id: number
    version2_id: number
  }): Promise<{
    added: ScheduleItemDTO[]
    removed: ScheduleItemDTO[]
    modified: Array<{
      item: ScheduleItemDTO
      before: any
      after: any
    }>
  }> => {
    const response = await apiClient.post('/v1/schedule/versions/compare', payload)
    return response.data
  },

  getItems: async (params?: {
    version_id?: number
    date?: string
    group_id?: number
    teacher_id?: number
    room_id?: number
    page?: number
    per_page?: number
  }): Promise<{
    data: ScheduleItemDTO[]
    current_page: number
    per_page: number
    total: number
    last_page: number
  }> => {
    const response = await apiClient.get('/v1/schedule/items', { params })
    return response.data
  },

  getChanges: async (params: {
    versionId: number
    dateFrom?: string
    dateTo?: string
  }): Promise<{
    data: Array<{
      id: number
      version_id: number
      action: string
      schedule_item_id: number | null
      actor_user_id: number
      before_json: any
      after_json: any
      created_at: string
      actor?: {
        id: number
        fio: string
      }
      scheduleItem?: ScheduleItemDTO
    }>
  }> => {
    const response = await apiClient.get('/v1/schedule/changes', { params })
    return response.data
  },

  getDiff: async (params: {
    fromVersionId: number
    toVersionId: number
  }): Promise<{
    added: ScheduleItemDTO[]
    removed: ScheduleItemDTO[]
    changed: Array<{
      from: ScheduleItemDTO
      to: ScheduleItemDTO
    }>
  }> => {
    const response = await apiClient.get('/v1/schedule/diff', { params })
    return response.data
  },
}
