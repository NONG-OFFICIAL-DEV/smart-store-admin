import axios from 'axios'
import router from '@/router'

const api = axios.create({
  baseURL: import.meta.env.VITE_APP_API_BASE_URL,
  headers: {
    'Access-Control-Allow-Origin': '*',
    'Content-type': 'application/json'
  }
})

// Request Interceptor
api.interceptors.request.use(async config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Requests to these endpoints must never trigger a silent-refresh attempt —
// each can legitimately 401/422 with no valid session in play yet (no
// token/refresh-token exists, or the one in play is intentionally being
// exchanged/validated by that very request), so retrying them through the
// refresh flow would either loop forever or refresh a session that was
// never meant to exist for this request.
const REFRESH_EXEMPT_PATHS = [
  '/login',
  '/refresh',
  '/two-factor/verify',
  '/business-register',
  '/forgot-password',
  '/reset-password'
]
const isRefreshExempt = config => REFRESH_EXEMPT_PATHS.some(path => config.url?.includes(path))

// Coalesce concurrent 401s behind a single in-flight refresh call instead of
// firing one refresh request per failed request.
let refreshPromise = null

async function logoutToLogin() {
  localStorage.removeItem('token')
  localStorage.removeItem('refresh_token')
  await router.push({ name: 'Login' })
}

// Response Interceptor
api.interceptors.response.use(
  response => response,

  async error => {
    const { config, response } = error

    if (response?.status === 401 && config && !config._retriedAfterRefresh && !isRefreshExempt(config)) {
      config._retriedAfterRefresh = true
      try {
        const { useAuthStore } = await import('@/stores/authStore')
        const authStore = useAuthStore()
        refreshPromise ??= authStore.refreshToken().finally(() => { refreshPromise = null })
        const newToken = await refreshPromise
        config.headers.Authorization = `Bearer ${newToken}`
        return api(config)
      } catch {
        await logoutToLogin()
        return Promise.reject(error)
      }
    }

    if (response?.status === 401) {
      await logoutToLogin()
    }
    return Promise.reject(error)
  }
)

export default api
