import Echo from 'laravel-echo'

let echoInstance: Echo | null = null

export function useEcho(): Echo | null {
  if (echoInstance) {
    return echoInstance
  }

  try {
    // TODO: get from env or config
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || ''
    const pusherHost = import.meta.env.VITE_PUSHER_HOST || 'localhost'
    const pusherPort = import.meta.env.VITE_PUSHER_PORT || '6001'
    const pusherScheme = import.meta.env.VITE_PUSHER_SCHEME || 'http'

    const isValidKey = pusherKey && 
                       pusherKey !== 'local-key' && 
                       pusherKey !== 'app-key' && 
                       pusherKey.trim() !== ''
    
    if (!isValidKey) {
      console.warn('Pusher key not configured, WebSocket disabled')
      return null
    }

    echoInstance = new Echo({
      broadcaster: 'pusher',
      key: pusherKey,
      wsHost: pusherHost,
      wsPort: pusherPort,
      wssPort: pusherPort,
      forceTLS: pusherScheme === 'https',
      encrypted: pusherScheme === 'https',
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/api/broadcasting/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('access_token')}`,
        },
      },
    })

    return echoInstance
  } catch (error) {
    console.error('Failed to initialize Echo:', error)
    return null
  }
}








