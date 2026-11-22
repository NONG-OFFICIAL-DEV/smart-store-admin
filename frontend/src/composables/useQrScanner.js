import { ref } from 'vue'

// Thin wrapper around html5-qrcode's lower-level Html5Qrcode class (not the
// full Html5QrcodeScanner widget, which draws its own large default UI) —
// this just owns start/stop so a component can render its own minimal
// camera box. Any failure (no camera, permission denied, insecure context)
// rejects/catches rather than throwing into the caller — scanning is only
// ever an enhancement here, manual code entry always still works.
export function useQrScanner() {
  const scanning = ref(false)
  const error = ref(null)
  let instance = null

  async function start(elementId, onDecoded) {
    error.value = null
    try {
      const { Html5Qrcode } = await import('html5-qrcode')
      instance = new Html5Qrcode(elementId)
      await instance.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: 220 },
        decodedText => {
          onDecoded(decodedText)
        },
        () => {
          // Per-frame "nothing decoded yet" — expected on almost every
          // frame, not a real error.
        }
      )
      scanning.value = true
    } catch (e) {
      error.value = e?.message || 'Could not start the camera.'
      scanning.value = false
    }
  }

  async function stop() {
    if (!instance) return
    try {
      await instance.stop()
      instance.clear()
    } catch {
      // Already stopped/never started — fine to ignore.
    } finally {
      instance = null
      scanning.value = false
    }
  }

  return { scanning, error, start, stop }
}
