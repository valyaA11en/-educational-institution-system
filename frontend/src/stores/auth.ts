import { defineStore } from 'pinia'
import { authApi, tenantsApi } from '../api'
import type { UserDTO, RoleDTO, PermissionDTO, LoginRequestDTO } from '../api/dto'
import type { TenantDTO } from '../api/tenants'

interface AuthState {
  user: UserDTO | null
  roles: RoleDTO[]
  permissions: PermissionDTO[]
  accessToken: string | null
  refreshToken: string | null
  expiresIn: number | null
  currentTenant: TenantDTO | null
  userTenants: TenantDTO[]
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
    currentTenant: null,
    userTenants: [],
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

        // Load current tenant
        try {
          this.currentTenant = await tenantsApi.getCurrent()
        } catch (error) {
          console.error('Failed to load current tenant:', error)
        }

        // Load user tenants if admin
        if (this.hasRole('admin')) {
          try {
            this.userTenants = await tenantsApi.getUserTenants()
          } catch (error) {
            console.error('Failed to load user tenants:', error)
          }
        }
      } catch (error) {
        console.error('Failed to load user profile:', error)
        this.clearAuth()
      }
    },

    async login(payload: LoginRequestDTO) {
      this.loading = true
      try {
        const response = await authApi.login(payload)

        // Check if 2FA is required
        if (response.requires_2fa && response.temp_token) {
          this.loading = false
          return {
            requires2fa: true,
            tempToken: response.temp_token,
          }
        }

        this.setTokens({
          accessToken: response.access_token,
          refreshToken: response.refresh_token,
          expiresIn: response.expires_in,
        })
        await this.loadUserProfile()

        // Connect to WebSocket after successful login
        const { useWsStore } = await import('./ws')
        const ws = useWsStore()
        await ws.init()

        return { requires2fa: false }
      } finally {
        this.loading = false
      }
    },

    async verify2FA(tempToken: string, code: string) {
      this.loading = true
      try {
        const tokens = await authApi.verify2FA(tempToken, code)

        this.setTokens({
          accessToken: tokens.access_token,
          refreshToken: tokens.refresh_token,
          expiresIn: tokens.expires_in,
        })
        await this.loadUserProfile()

        // Connect to WebSocket after successful login
        const { useWsStore } = await import('./ws')
        const ws = useWsStore()
        await ws.init()
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

    setTokens(tokens: { accessToken: string; refreshToken: string; expiresIn: number; tenant?: TenantDTO }) {
      this.accessToken = tokens.accessToken
      this.refreshToken = tokens.refreshToken
      this.expiresIn = Date.now() + tokens.expiresIn * 1000

      if (tokens.tenant) {
        this.currentTenant = tokens.tenant
      }

      localStorage.setItem(ACCESS_TOKEN_KEY, tokens.accessToken)
      localStorage.setItem(REFRESH_TOKEN_KEY, tokens.refreshToken)
      localStorage.setItem(TOKEN_EXPIRES_KEY, this.expiresIn.toString())
    },

    async switchTenant(tenantId: number) {
      try {
        const response = await tenantsApi.switch(tenantId)
        this.setTokens({
          accessToken: response.access_token,
          refreshToken: this.refreshToken || '',
          expiresIn: response.expires_in,
          tenant: response.tenant,
        })
        
        // Reload user profile to get updated permissions
        await this.loadUserProfile()
        
        return response.tenant
      } catch (error) {
        console.error('Failed to switch tenant:', error)
        throw error
      }
    },

    clearAuth() {
      this.user = null
      this.roles = []
      this.permissions = []
      this.accessToken = null
      this.refreshToken = null
      this.expiresIn = null
      this.currentTenant = null
      this.userTenants = []

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


