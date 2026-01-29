import { defineStore } from 'pinia'
import apiClient from '../api/client'

export const useReadOnlyStore = defineStore('readOnly', {
  state: () => ({
    isReadOnly: false,
    loading: false,
  }),

  getters: {
    isEnabled: (state) => state.isReadOnly,
  },

  actions: {
    async checkReadOnlyMode() {
      this.loading = true
      try {
        // Real API call to Laravel backend
        const response = await apiClient.get('/v1/meta')
        if (response.data?.read_only !== undefined) {
          this.isReadOnly = response.data.read_only
        }
      } catch (error: any) {
        // Check if error is 503 with read-only message
        if (error.response?.status === 503 && 
            error.response?.data?.message?.includes('read-only')) {
          this.isReadOnly = true
        }
        // If meta endpoint fails, assume not read-only
        this.isReadOnly = false
      } finally {
        this.loading = false
      }
    },

    setReadOnly(value: boolean) {
      this.isReadOnly = value
    },
  },
})

