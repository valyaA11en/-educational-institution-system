import { useEcho } from './useEcho'

export function useRealtime() {
  const echo = useEcho()

  const subscribe = (channel: string, callback: (event: any) => void) => {
    if (!echo) {
      console.warn('Echo not available')
      return () => {}
    }

    echo.private(channel)
      .listen('.document.status_changed', (event: any) => {
        callback(event)
      })

    return () => {
      echo.leave(channel)
    }
  }

  const unsubscribe = (channel: string) => {
    if (!echo) {
      return
    }
    echo.leave(channel)
  }

  return {
    subscribe,
    unsubscribe,
  }
}








