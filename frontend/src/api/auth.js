import http from './api'
import FingerprintJS from '@fingerprintjs/fingerprintjs'

let cachedTerminalId = null

async function getTerminalId() {
  if (cachedTerminalId) return cachedTerminalId
  const fp = await FingerprintJS.load()
  const result = await fp.get()
  cachedTerminalId = result.visitorId
  return cachedTerminalId
}

function getDeviceName() {
  const ua = navigator.userAgent
  const p = navigator.platform
  const browser = /Chrome/.test(ua)
    ? 'Chrome'
    : /Firefox/.test(ua)
      ? 'Firefox'
      : /Safari/.test(ua)
        ? 'Safari'
        : /Edge/.test(ua)
          ? 'Edge'
          : 'Browser'
  if (/iPhone/.test(ua)) return `iPhone – ${browser}`
  if (/iPad/.test(ua)) return `iPad – ${browser}`
  if (/Android/.test(ua)) return `Android – ${browser}`
  if (/Mac/.test(p)) return `Mac – ${browser}`
  if (/Win/.test(p)) return `Windows – ${browser}`
  return `${p} – ${browser}`
}

export default {
  async userLogin(email, password) {
    const terminal_id = await getTerminalId()
    return http.post('/login', {
      email,
      password,
      terminal_id,
      device_name: getDeviceName()
    })
  },

  async loginByPin(pin_code, branch_id) {
    const terminal_id = await getTerminalId()
    return http.post('/login-pin', { pin_code, branch_id, terminal_id })
  },

  async refreshToken() {
    const terminal_id = await getTerminalId()
    const refresh_token = localStorage.getItem('refresh_token')
    return http.post('/refresh', { refresh_token, terminal_id })
  },

  userLogout() {
    return http.post('/logout')
  },

  me() {
    return http.get('/me', { meta: { loader: 'skeleton' } })
  },
  setPin(pin_code) {
    return http.put('/set-pin', {
      pin_code,
      pin_code_confirmation: pin_code
    })
  }
}
