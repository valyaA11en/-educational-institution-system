import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosResponse, type AxiosError } from 'axios'
import { useAuthStore } from '../stores/auth'

const apiClient: AxiosInstance = axios.create({
  baseURL: '/api',  // Use relative path - Nginx will proxy to Laravel
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 10000, // Increased timeout for development
  withCredentials: false, // JWT tokens don't need cookies
})

// Retry configuration
const MAX_RETRIES = 3
const RETRY_DELAY = 1000 // 1 second
const RETRYABLE_STATUS_CODES = [408, 429, 500, 502, 503, 504]

// Retry logic helper
const shouldRetry = (error: AxiosError, retryCount: number): boolean => {
  if (retryCount >= MAX_RETRIES) {
    return false
  }

  // Retry on network errors
  if (!error.response) {
    return true
  }

  // Retry on specific status codes
  if (error.response.status && RETRYABLE_STATUS_CODES.includes(error.response.status)) {
    return true
  }

  return false
}

const delay = (ms: number): Promise<void> => {
  return new Promise(resolve => setTimeout(resolve, ms))
}

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
  async (error: AxiosError) => {
    const auth = useAuthStore()
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retryCount?: number; _retry?: boolean }

    if (!originalRequest) {
      return Promise.reject(error)
    }

    // Initialize retry count
    originalRequest._retryCount = originalRequest._retryCount || 0

    // Handle 503 read-only mode
    if (error.response?.status === 503 && 
        error.response?.data?.message?.includes('read-only')) {
      // Set read-only mode in store if available
      try {
        const { useReadOnlyStore } = await import('../stores/readOnly')
        const readOnlyStore = useReadOnlyStore()
        readOnlyStore.setReadOnly(true)
      } catch (e) {
        // Store might not be initialized yet
      }
    }

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

    // Retry logic for network errors and specific status codes
    if (shouldRetry(error, originalRequest._retryCount)) {
      originalRequest._retryCount++
      
      // Exponential backoff: 1s, 2s, 4s
      const delayMs = RETRY_DELAY * Math.pow(2, originalRequest._retryCount - 1)
      
      await delay(delayMs)
      
      return apiClient(originalRequest)
    }

    return Promise.reject(error)
  }
)

export default apiClient
export { apiClient }

