import { defineStore } from 'pinia'
import { authApi } from '../api'
import type { UserDTO, RoleDTO, PermissionDTO, LoginRequestDTO } from '../api/dto'

interface AuthState {
  user: UserDTO | null
  roles: RoleDTO[]
  permissions: PermissionDTO[]
  accessToken: string | null
  refreshToken: string | null
  expiresIn: number | null
  loading: boolean
}

const ACCESS_TOKEN_KEY = 'access_token'
const REFRESH_TOKEN_KEY = 'refresh_token'
const TOKEN_EXPIRES_KEY = 'token_expires'

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    roles: [],
    permissions: [],
    accessToken: null,
    refreshToken: null,
    expiresIn: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state): boolean => {
      return !!state.accessToken && !!state.user
    },
    hasPermission: (state) => (code: string): boolean => {
      return state.permissions.some((p) => p.code === code)
    },
    hasRole: (state) => (name: string): boolean => {
      return state.roles.some((r) => r.name === name)
    },
  },

  actions: {
    async init() {
      const accessToken = localStorage.getItem(ACCESS_TOKEN_KEY)
      const refreshToken = localStorage.getItem(REFRESH_TOKEN_KEY)
      const expiresIn = localStorage.getItem(TOKEN_EXPIRES_KEY)

      if (!accessToken || !refreshToken) {
        return
      }

      this.accessToken = accessToken
      this.refreshToken = refreshToken
      this.expiresIn = expiresIn ? parseInt(expiresIn, 10) : null

      // Check if token is expired
      if (this.expiresIn && Date.now() >= this.expiresIn) {
        // Token expired, try to refresh
        try {
          await this.refreshAccessToken()
        } catch {
          this.clearAuth()
          return
        }
      }

      // Load user profile
      await this.loadUserProfile()
    },

    async loadUserProfile() {
      try {
        const me = await authApi.me()
        this.user = me.user
        this.roles = me.roles
        this.permissions = me.permissions
      } catch (error) {
        console.error('Failed to load user profile:', error)
        this.clearAuth()
      }
    },

    async login(payload: LoginRequestDTO) {
      this.loading = true
      try {
        const tokens = await authApi.login(payload)

        this.setTokens(tokens)
        await this.loadUserProfile()

        // Connect to WebSocket after successful login
        const { useWsStore } = await import('./ws')
        const ws = useWsStore()
        await ws.init()
      } catch (error) {
        throw error
      } finally {
        this.loading = false
      }
    },

    async refreshAccessToken() {
      if (!this.refreshToken) {
        throw new Error('No refresh token available')
      }

      try {
        const tokens = await authApi.refresh(this.refreshToken)
        this.setTokens(tokens)
      } catch (error) {
        this.clearAuth()
        throw error
      }
    },

    setTokens(tokens: { accessToken: string; refreshToken: string; expiresIn: number }) {
      this.accessToken = tokens.accessToken
      this.refreshToken = tokens.refreshToken
      this.expiresIn = Date.now() + tokens.expiresIn * 1000

      localStorage.setItem(ACCESS_TOKEN_KEY, tokens.accessToken)
      localStorage.setItem(REFRESH_TOKEN_KEY, tokens.refreshToken)
      localStorage.setItem(TOKEN_EXPIRES_KEY, this.expiresIn.toString())
    },

    clearAuth() {
      this.user = null
      this.roles = []
      this.permissions = []
      this.accessToken = null
      this.refreshToken = null
      this.expiresIn = null

      localStorage.removeItem(ACCESS_TOKEN_KEY)
      localStorage.removeItem(REFRESH_TOKEN_KEY)
      localStorage.removeItem(TOKEN_EXPIRES_KEY)
    },

    async logout() {
      try {
        await authApi.logout()
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        // Disconnect WebSocket
        const { useWsStore } = await import('./ws')
        const ws = useWsStore()
        ws.disconnect()

        this.clearAuth()
      }
    },
  },
})


