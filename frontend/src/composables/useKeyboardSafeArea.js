import { ref, onMounted, onUnmounted } from 'vue'

// Tracks the on-screen keyboard via the visualViewport API — the layout
// viewport (`window.innerHeight`) doesn't shrink when the keyboard opens on
// iOS/Android, only the *visual* viewport does, so that's the only reliable
// signal for "how much screen is actually left above the keyboard."
// Falls back to window.innerHeight on browsers without visualViewport
// (keyboardHeight just stays 0 there — no crash, no safe-area adjustment).
export function useKeyboardSafeArea() {
  const availableHeight = ref(window.visualViewport?.height ?? window.innerHeight)
  const keyboardHeight = ref(0)

  function update() {
    const vv = window.visualViewport
    if (!vv) return
    availableHeight.value = vv.height
    keyboardHeight.value = Math.max(0, window.innerHeight - vv.height)
  }

  onMounted(() => {
    update()
    window.visualViewport?.addEventListener('resize', update)
    window.visualViewport?.addEventListener('scroll', update)
  })
  onUnmounted(() => {
    window.visualViewport?.removeEventListener('resize', update)
    window.visualViewport?.removeEventListener('scroll', update)
  })

  // Scrolls the just-focused element back into view once the keyboard has
  // finished animating open — iOS/Android both take a couple hundred ms, so
  // calling this synchronously on `focus` scrolls before the viewport has
  // actually shrunk and ends up doing nothing.
  function keepVisible(el) {
    setTimeout(() => {
      el?.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
    }, 300)
  }

  return { availableHeight, keyboardHeight, keepVisible }
}
