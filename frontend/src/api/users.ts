import { apiClient } from './client'
import type { RoleDTO } from './dto'

export interface UserDTO {
  id: number
  fio: string
  email?: string
  phone?: string
  status: 'active' | 'blocked'
  roles?: RoleDTO[]
  created_at?: string
  updated_at?: string
}

export interface CreateUserDTO {
  fio: string
  email?: string
  phone?: string
  password: string
  status?: 'active' | 'blocked'
  role_ids?: number[]
}

export interface UpdateUserDTO {
  fio?: string
  email?: string
  phone?: string
  password?: string
  status?: 'active' | 'blocked'
  role_ids?: number[]
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export const usersApi = {
  async list(params?: { q?: string; per_page?: number; page?: number }) {
    const { data } = await apiClient.get<PaginatedResponse<UserDTO>>('/v1/admin/users', { params })
    return data
  },
  async get(id: number) {
    const { data } = await apiClient.get<UserDTO>(`/v1/admin/users/${id}`)
    return data
  },
  async create(payload: CreateUserDTO) {
    const { data } = await apiClient.post<UserDTO>('/v1/admin/users', payload)
    return data
  },
  async update(id: number, payload: UpdateUserDTO) {
    const { data } = await apiClient.patch<UserDTO>(`/v1/admin/users/${id}`, payload)
    return data
  },
  async delete(id: number) {
    await apiClient.delete(`/v1/admin/users/${id}`)
  },
  async assignRoles(id: number, roleIds: number[]) {
    const { data } = await apiClient.post<UserDTO>(`/v1/admin/users/${id}/roles`, { role_ids: roleIds })
    return data
  },
}

