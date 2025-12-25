import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosResponse } from 'axios'
import { useAuthStore } from '../stores/auth'

const apiClient: AxiosInstance = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Request interceptor: add auth token
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const auth = useAuthStore()

    if (auth.accessToken) {
      config.headers = config.headers || {}
      config.headers.Authorization = `Bearer ${auth.accessToken}`
    }

    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor: handle 401 and refresh token (only once)
let isRefreshing = false
let failedQueue: Array<{
  resolve: (value?: any) => void
  reject: (error?: any) => void
}> = []

const processQueue = (error: any, token: string | null = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve(token)
    }
  })
  failedQueue = []
}

apiClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error) => {
    const auth = useAuthStore()
    const originalRequest = error.config

    // If 401 and not already retrying, try to refresh token (only once)
    if (error.response?.status === 401 && !originalRequest._retry && auth.refreshToken) {
      if (isRefreshing) {
        // If already refreshing, queue this request
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        })
          .then((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`
            return apiClient(originalRequest)
          })
          .catch((err) => {
            return Promise.reject(err)
          })
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        await auth.refreshAccessToken()
        const token = auth.accessToken
        processQueue(null, token)
        // Retry original request with new token
        originalRequest.headers.Authorization = `Bearer ${token}`
        return apiClient(originalRequest)
      } catch (refreshError) {
        processQueue(refreshError, null)
        // Refresh failed, logout user
        await auth.logout()
        window.location.href = '/login'
        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    return Promise.reject(error)
  }
)

export default apiClient

