import { ref, onMounted, onUnmounted } from 'vue'
import authService from '@/api/auth'

// navigator.onLine only reflects the network interface (still true on wifi
// with no internet, or when this API's own host is unreachable but
// everything else works) — the periodic /me ping below is what actually
// catches "connected to a network, but not to us."
const PING_INTERVAL_MS = 30000

export function useOnlineStatus() {
  const isOnline = ref(navigator.onLine)

  function setOnline() {
    isOnline.value = true
  }
  function setOffline() {
    isOnline.value = false
  }

  async function ping() {
    if (!navigator.onLine) {
      isOnline.value = false
      return
    }
    try {
      await authService.me()
      isOnline.value = true
    } catch (err) {
      // A network-level failure (no response at all) means truly offline —
      // an auth/permission error (401/403/etc.) still proves the API is
      // reachable, so that's "online" even though this specific call failed.
      if (!err?.response) isOnline.value = false
    }
  }

  let interval = null

  onMounted(() => {
    window.addEventListener('online', setOnline)
    window.addEventListener('offline', setOffline)
    interval = setInterval(ping, PING_INTERVAL_MS)
  })

  onUnmounted(() => {
    window.removeEventListener('online', setOnline)
    window.removeEventListener('offline', setOffline)
    clearInterval(interval)
  })

  return { isOnline }
}
