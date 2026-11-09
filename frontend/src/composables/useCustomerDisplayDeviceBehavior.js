import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Device-level behavior for an always-on customer-facing screen: keep it
 * awake, and hide the cursor once nobody's touching it — a paired tablet
 * sitting on a counter should never show a blinking mouse pointer or go
 * dark mid-order.
 */
export function useCustomerDisplayDeviceBehavior() {
  const cursorHidden = ref(false)

  // ── Screen wake lock ───────────────────────────────────────────────────
  let wakeLock = null

  async function requestWakeLock() {
    if (!('wakeLock' in navigator)) return
    try {
      wakeLock = await navigator.wakeLock.request('screen')
    } catch {
      // Not fatal — e.g. the tab isn't visible yet, or the device doesn't
      // actually support it despite the API existing. Nothing to show the
      // customer about this, it just means the screen might sleep.
      wakeLock = null
    }
  }

  // The OS releases the lock whenever the tab/page loses visibility (tab
  // switch, screen lock) — re-request the moment it's visible again, or a
  // tablet that briefly went to the lock screen never reacquires it.
  function handleVisibilityChange() {
    if (document.visibilityState === 'visible') requestWakeLock()
  }

  // ── Cursor auto-hide after 3s idle ──────────────────────────────────────
  let cursorTimer = null

  function resetCursorTimer() {
    cursorHidden.value = false
    clearTimeout(cursorTimer)
    cursorTimer = setTimeout(() => {
      cursorHidden.value = true
    }, 3000)
  }

  onMounted(() => {
    requestWakeLock()
    document.addEventListener('visibilitychange', handleVisibilityChange)

    resetCursorTimer()
    window.addEventListener('mousemove', resetCursorTimer)
    window.addEventListener('touchstart', resetCursorTimer)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    window.removeEventListener('mousemove', resetCursorTimer)
    window.removeEventListener('touchstart', resetCursorTimer)
    clearTimeout(cursorTimer)
    wakeLock?.release().catch(() => {})
  })

  return { cursorHidden }
}
