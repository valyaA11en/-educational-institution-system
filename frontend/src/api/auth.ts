import apiClient from './client'
import type { LoginRequestDTO, AuthTokensDTO, MeDTO } from './dto'

export interface LoginResponseDTO {
  requires_2fa?: boolean
  temp_token?: string
  access_token?: string
  refresh_token?: string
  token_type?: string
  expires_in?: number
  user?: any
}

export interface Verify2FAResponseDTO {
  access_token: string
  refresh_token: string
  token_type: string
  expires_in: number
  user: any
}

export const authApi = {
  async login(data: LoginRequestDTO): Promise<LoginResponseDTO> {
    const response = await apiClient.post<LoginResponseDTO>('/v1/auth/login', data)
    return response.data
  },

  async verify2FA(tempToken: string, code: string): Promise<Verify2FAResponseDTO> {
    const response = await apiClient.post<Verify2FAResponseDTO>('/v1/auth/2fa/verify', {
      temp_token: tempToken,
      code,
    })
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









