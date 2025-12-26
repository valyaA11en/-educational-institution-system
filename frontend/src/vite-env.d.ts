/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL: string
  readonly VITE_WEBSOCKET_URL: string
  readonly VITE_WEBSOCKET_HOST?: string
  readonly VITE_WEBSOCKET_PORT?: string
  readonly VITE_PUSHER_APP_KEY?: string
  readonly VITE_PUSHER_APP_CLUSTER?: string
  readonly VITE_VAPID_PUBLIC_KEY?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

