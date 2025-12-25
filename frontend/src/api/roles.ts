import { apiClient } from './client'
import type { RoleDTO } from './dto'

export const rolesApi = {
  async list() {
    const { data } = await apiClient.get<RoleDTO[]>('/v1/admin/roles')
    return data
  },
}

