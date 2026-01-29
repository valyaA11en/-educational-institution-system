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
    // Real API call to Laravel backend
    const response = await apiClient.post<LoginResponseDTO>('/v1/auth/login', {
      emailOrPhone: data.emailOrPhone,
      password: data.password,
    })
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
    // Send refresh token in Authorization header or body
    const response = await apiClient.post<{
      access_token: string
      refresh_token: string
      expires_in: number
    }>('/v1/auth/refresh', {
      refreshToken,
    }, {
      headers: {
        // Also try to send as Bearer token if available
        ...(refreshToken ? { Authorization: `Bearer ${refreshToken}` } : {})
      }
    })
    // Convert snake_case to camelCase for frontend
    return {
      accessToken: response.data.access_token,
      refreshToken: response.data.refresh_token,
      expiresIn: response.data.expires_in,
    }
  },

  async me(): Promise<MeDTO> {
    // Real API call to Laravel backend
    const response = await apiClient.get<MeDTO>('/v1/auth/me')
    return response.data
  },

  async logout(): Promise<void> {
    await apiClient.post('/v1/auth/logout')
  },
}









