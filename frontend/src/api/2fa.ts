import apiClient from './client'

export interface Setup2FAResponse {
  secret: string
  qr_code_url: string
  recovery_codes: string[]
}

export interface Enable2FAResponse {
  message: string
  recovery_codes: string[]
}

export interface TwoFactorStatus {
  enabled: boolean
  has_secret: boolean
}

export const twoFactorApi = {
  status: async (): Promise<TwoFactorStatus> => {
    const response = await apiClient.get<TwoFactorStatus>('/v1/auth/2fa/status')
    return response.data
  },

  setup: async (): Promise<Setup2FAResponse> => {
    const response = await apiClient.post<Setup2FAResponse>('/v1/auth/2fa/setup')
    return response.data
  },

  enable: async (code: string): Promise<Enable2FAResponse> => {
    const response = await apiClient.post<Enable2FAResponse>('/v1/auth/2fa/enable', { code })
    return response.data
  },

  disable: async (code: string): Promise<void> => {
    await apiClient.post('/v1/auth/2fa/disable', { code })
  },
}

