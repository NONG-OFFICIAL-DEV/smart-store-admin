import { useLoadingStore } from '@/stores/loading'
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_APP_API_BASE_URL,
  headers: {
    'Access-Control-Allow-Origin': '*',
    'Content-type': 'application/json',
  },
})

// ── Request ────────────────────────────────────────────────────────────────
api.interceptors.request.use(async config => {
  const loaderType = config.meta?.loader || 'overlay'
  try {
    const token = localStorage.getItem('token')
    if (token) config.headers.Authorization = `Bearer ${token}`
    useLoadingStore().start(loaderType)
    return config
  } catch (error) {
    useLoadingStore().stop()
    return Promise.reject(error)
  }
})

// ── Response ───────────────────────────────────────────────────────────────
let isRefreshing = false
let queue = []

api.interceptors.response.use(
  response => {
    useLoadingStore().stop()
    return response
  },
  async error => {
    useLoadingStore().stop()
    const original = error.config
    const refreshToken = localStorage.getItem('refresh_token')

    // Auto-refresh on 401
    if (error.response?.status === 401 && !original._retry && refreshToken) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => queue.push({ resolve, reject }))
          .then(token => {
            original.headers.Authorization = `Bearer ${token}`
            return api(original)
          })
      }

      original._retry = true
      isRefreshing = true

      try {
        // Import here to avoid circular dependency
        const authService = (await import('./auth')).default
        const res = await authService.refreshToken()
        const newToken = res.data.token

        localStorage.setItem('token', newToken)
        localStorage.setItem('refresh_token', res.data.refresh_token)

        queue.forEach(p => p.resolve(newToken))
        queue = []

        original.headers.Authorization = `Bearer ${newToken}`
        return api(original)
      } catch {
        queue.forEach(p => p.reject())
        queue = []
        localStorage.removeItem('token')
        localStorage.removeItem('refresh_token')
        window.location.href = '/login'
        return Promise.reject(error)
      } finally {
        isRefreshing = false
      }
    }

    return Promise.reject(error)
  }
)

export default api