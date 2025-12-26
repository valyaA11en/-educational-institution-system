import { defineStore } from 'pinia'

export const useMainStore = defineStore('main', {
  state: () => ({
    connected: false,
    message: 'Welcome to PDO Project',
  }),
  actions: {
    setConnected(value: boolean) {
      this.connected = value
    },
    setMessage(value: string) {
      this.message = value
    },
  },
})


