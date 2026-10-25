import { ref, onMounted, onUnmounted } from 'vue'

// Real touch-capability detection for keyboard-handling decisions (NumPad vs
// system keyboard, autofocus suppression) — deliberately NOT the same as the
// `mdAndDown` "touch" breakpoint used elsewhere in POS for layout density.
// A narrow desktop browser window is `mdAndDown` but has no on-screen
// keyboard; a landscape iPad can exceed `md` width while still being a
// coarse-pointer touch device. `(pointer: coarse)` is the actual signal.
//
// Initialized synchronously (not inside onMounted) — the native `autofocus`
// HTML attribute fires at DOM insertion, which happens as part of the first
// render. Computing isTouch a tick later in onMounted would race that: the
// element could already have opened the keyboard before Vue's reactive
// update removed the attribute.
export function useTouchDevice() {
  const mql = typeof window !== 'undefined' ? window.matchMedia('(pointer: coarse)') : null
  const isTouch = ref(mql?.matches ?? false)

  function update() {
    isTouch.value = mql?.matches ?? false
  }

  onMounted(() => mql?.addEventListener('change', update))
  onUnmounted(() => mql?.removeEventListener('change', update))

  return { isTouch }
}
