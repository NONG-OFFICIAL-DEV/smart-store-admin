import { useLoadingStore } from '@/stores/loading'
import axios from 'axios'
import router from '@/router'

const api = axios.create({
  baseURL: import.meta.env.VITE_APP_API_BASE_URL,
  headers: {
    'Access-Control-Allow-Origin': '*',
    'Content-type': 'application/json'
  }
})

// Get store instance
const loadingStore = useLoadingStore()
// Request Interceptor
api.interceptors.request.use(async config => {
  const loaderType = config.meta?.loader || 'overlay'
  try {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    loadingStore.start(loaderType)
    return config
  } catch (error) {
    useLoadingStore().stop()
    return Promise.reject(error)
  }
})

// Requests to these endpoints must never trigger a silent-refresh attempt —
// otherwise a 401 from /login or /refresh itself would try to refresh a
// token that doesn't exist yet / just failed to refresh, looping forever.
const REFRESH_EXEMPT_PATHS = ['/login', '/login-pin', '/refresh']
const isRefreshExempt = config => REFRESH_EXEMPT_PATHS.some(path => config.url?.includes(path))

// Coalesce concurrent 401s behind a single in-flight refresh call instead of
// firing one refresh request per failed request.
let refreshPromise = null

async function logoutToLogin() {
  localStorage.removeItem('token')
  await router.push({ name: 'Login' })
}

// Response Interceptor
api.interceptors.response.use(
  response => {
    useLoadingStore().stop()
    return response
  },

  async error => {
    useLoadingStore().stop()
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
