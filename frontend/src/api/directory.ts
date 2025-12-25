import { apiClient } from './client'

export interface GroupDTO {
  id: number
  term_id?: number
  name: string
  level?: number
  curator_user_id?: number
  created_at?: string
  updated_at?: string
}

export interface SubgroupDTO {
  id: number
  group_id: number
  name: string
  group?: GroupDTO
  created_at?: string
  updated_at?: string
}

export interface SubjectDTO {
  id: number
  name: string
  created_at?: string
  updated_at?: string
}

export interface RoomDTO {
  id: number
  name: string
  code: string
  capacity?: number
  attributes?: Record<string, any>
  created_at?: string
  updated_at?: string
}

export interface TimeSlotDTO {
  id: number
  name: string
  start_time: string
  end_time: string
  order: number
  created_at?: string
  updated_at?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const directoryApi = {
  // Groups (admin endpoints)
  async listGroups(params?: { q?: string; per_page?: number; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<GroupDTO>>('/v1/admin/directory/groups', { params })
    return data
  },
  async getGroup(id: number) {
    const { data } = await apiClient.get<GroupDTO>(`/v1/admin/directory/groups/${id}`)
    return data
  },
  async createGroup(payload: { name: string; code: string }) {
    const { data } = await apiClient.post<GroupDTO>('/v1/admin/directory/groups', payload)
    return data
  },
  async updateGroup(id: number, payload: { name: string; code: string }) {
    const { data } = await apiClient.put<GroupDTO>(`/v1/admin/directory/groups/${id}`, payload)
    return data
  },
  async deleteGroup(id: number) {
    await apiClient.delete(`/v1/admin/directory/groups/${id}`)
  },

  // Subgroups (admin endpoints)
  async listSubgroups(params?: { group_id?: number; q?: string; per_page?: number; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<SubgroupDTO>>('/v1/admin/directory/subgroups', { params })
    return data
  },
  async getSubgroup(id: number) {
    const { data } = await apiClient.get<SubgroupDTO>(`/v1/admin/directory/subgroups/${id}`)
    return data
  },
  async createSubgroup(payload: { group_id: number; name: string; code: string }) {
    const { data } = await apiClient.post<SubgroupDTO>('/v1/admin/directory/subgroups', payload)
    return data
  },
  async updateSubgroup(id: number, payload: { group_id: number; name: string; code: string }) {
    const { data } = await apiClient.put<SubgroupDTO>(`/v1/admin/directory/subgroups/${id}`, payload)
    return data
  },
  async deleteSubgroup(id: number) {
    await apiClient.delete(`/v1/admin/directory/subgroups/${id}`)
  },

  // Subjects (admin endpoints)
  async listSubjects(params?: { q?: string; per_page?: number; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<SubjectDTO>>('/v1/admin/directory/subjects', { params })
    return data
  },
  async getSubject(id: number) {
    const { data } = await apiClient.get<SubjectDTO>(`/v1/admin/directory/subjects/${id}`)
    return data
  },
  async createSubject(payload: { name: string }) {
    const { data } = await apiClient.post<SubjectDTO>('/v1/admin/directory/subjects', payload)
    return data
  },
  async updateSubject(id: number, payload: { name: string }) {
    const { data } = await apiClient.put<SubjectDTO>(`/v1/admin/directory/subjects/${id}`, payload)
    return data
  },
  async deleteSubject(id: number) {
    await apiClient.delete(`/v1/admin/directory/subjects/${id}`)
  },

  // Rooms (admin endpoints)
  async listRooms(params?: { q?: string; per_page?: number; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<RoomDTO>>('/v1/admin/directory/rooms', { params })
    return data
  },
  async getRoom(id: number) {
    const { data } = await apiClient.get<RoomDTO>(`/v1/admin/directory/rooms/${id}`)
    return data
  },
  async createRoom(payload: { name: string; type?: string; capacity?: number; attributes?: Record<string, any> }) {
    const { data } = await apiClient.post<RoomDTO>('/v1/admin/directory/rooms', payload)
    return data
  },
  async updateRoom(id: number, payload: { name: string; type?: string; capacity?: number; attributes?: Record<string, any> }) {
    const { data } = await apiClient.put<RoomDTO>(`/v1/admin/directory/rooms/${id}`, payload)
    return data
  },
  async deleteRoom(id: number) {
    await apiClient.delete(`/v1/admin/directory/rooms/${id}`)
  },

  // Time Slots (admin endpoints)
  async listTimeSlots(params?: { q?: string; per_page?: number; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<TimeSlotDTO>>('/v1/admin/directory/time-slots', { params })
    return data
  },
  async getTimeSlot(id: number) {
    const { data } = await apiClient.get<TimeSlotDTO>(`/v1/admin/directory/time-slots/${id}`)
    return data
  },
  async createTimeSlot(payload: { name: string; start_time: string; end_time: string; order: number }) {
    const { data } = await apiClient.post<TimeSlotDTO>('/v1/admin/directory/time-slots', payload)
    return data
  },
  async updateTimeSlot(id: number, payload: { name: string; start_time: string; end_time: string; order: number }) {
    const { data } = await apiClient.put<TimeSlotDTO>(`/v1/admin/directory/time-slots/${id}`, payload)
    return data
  },
  async deleteTimeSlot(id: number) {
    await apiClient.delete(`/v1/admin/directory/time-slots/${id}`)
  },
}

