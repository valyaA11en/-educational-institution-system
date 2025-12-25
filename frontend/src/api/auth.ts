import apiClient from './client'
import type { LoginRequestDTO, AuthTokensDTO, MeDTO } from './dto'

export const authApi = {
  async login(data: LoginRequestDTO): Promise<AuthTokensDTO> {
    const response = await apiClient.post<AuthTokensDTO>('/v1/auth/login', data)
    return response.data
  },

  async refresh(refreshToken: string): Promise<AuthTokensDTO> {
    const response = await apiClient.post<AuthTokensDTO>('/v1/auth/refresh', {
      refreshToken,
    })
    return response.data
  },

  async me(): Promise<MeDTO> {
    const response = await apiClient.get<MeDTO>('/v1/auth/me')
    return response.data
  },

  async logout(): Promise<void> {
    await apiClient.post('/v1/auth/logout')
  },
}

