// src/composables/useTerminal.js
import FingerprintJS from '@fingerprintjs/fingerprintjs'

let cachedTerminalId = null

export function useTerminal() {
  async function getTerminalId() {
    if (cachedTerminalId) return cachedTerminalId
    const fp = await FingerprintJS.load()
    const result = await fp.get()
    cachedTerminalId = result.visitorId
    return cachedTerminalId
  }

  function getDeviceName() {
    const ua = navigator.userAgent
    const platform = navigator.platform

    const browser = /Chrome/.test(ua) ? 'Chrome'
      : /Firefox/.test(ua) ? 'Firefox'
      : /Safari/.test(ua) ? 'Safari'
      : /Edge/.test(ua) ? 'Edge'
      : 'Browser'

    if (/iPhone/.test(ua)) return `iPhone – ${browser}`
    if (/iPad/.test(ua)) return `iPad – ${browser}`
    if (/Android/.test(ua)) return `Android – ${browser}`
    if (/Mac/.test(platform)) return `Mac – ${browser}`
    if (/Win/.test(platform)) return `Windows – ${browser}`
    if (/Linux/.test(platform)) return `Linux – ${browser}`
    return `${platform} – ${browser}`
  }

  return { getTerminalId, getDeviceName }
}