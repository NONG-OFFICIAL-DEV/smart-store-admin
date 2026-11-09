import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { customerDisplayBroadcastingAuthApi } from '@/api/customerDisplayService'

window.Pusher = Pusher

let echo = null

/*
 * Separate Echo instance from src/utils/echo.js on purpose — that one is
 * staff-JWT-only and only ever connects after a real login. A customer
 * display never logs in; it authorizes its private channel with its own
 * device_token (see CustomerDisplayController::broadcastingAuth()), so it
 * needs its own authorizer hitting a different, unauthenticated endpoint.
 */
function authorizer(channel, deviceToken) {
  return {
    authorize(socketId, callback) {
      customerDisplayBroadcastingAuthApi(socketId, channel.name, deviceToken)
        .then(response => callback(null, response.data))
        .catch(error => callback(error, null))
    }
  }
}

export function connectCustomerDisplayEcho(deviceToken) {
  if (echo) return echo

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: channel => authorizer(channel, deviceToken)
  })

  return echo
}

export function disconnectCustomerDisplayEcho() {
  echo?.disconnect()
  echo = null
}
