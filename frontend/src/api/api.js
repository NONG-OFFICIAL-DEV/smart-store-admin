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

// EnsureSubscriptionActive is deliberately still reachable on these two
// endpoints (see routes/api.php's withoutMiddleware('subscription.active'))
// so the tenant-billing page can always render even while blocked — a
// successful response from either must NOT be treated as "the block lifted",
// or the banner would clear while the tenant is still actually blocked.
const SUBSCRIPTION_GATE_EXEMPT = [/\/plans\/[^/]+\/billing/, /\/tenants\/[^/]+$/]
const isSubscriptionGateExempt = config => SUBSCRIPTION_GATE_EXEMPT.some(re => re.test(config.url ?? ''))

// Mirrors EnsureSubscriptionActive's two block codes on the backend.
const SUBSCRIPTION_BLOCK_CODES = ['TENANT_SUSPENDED', 'SUBSCRIPTION_STATUS_BLOCKED']

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
  async response => {
    // Any non-exempt success is proof the tenant isn't currently blocked —
    // clears a stale banner the moment access is restored, without waiting
    // for the next /me refresh.
    if (!isSubscriptionGateExempt(response.config)) {
      const { useAuthStore } = await import('@/stores/authStore')
      useAuthStore().setBlockedApiError(null)
    }
    return response
  },

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

    // Every /v1/... route 403s with one of these codes once a tenant is
    // suspended or its subscription is cancelled/suspended (see
    // EnsureSubscriptionActive) — without this, every page the tenant
    // visits just fails silently. Send them straight to the one page that
    // stays reachable (see SUBSCRIPTION_GATE_EXEMPT above) — Layout.vue's
    // banner (driven by authStore.blockedApiError) explains why they
    // landed there, translated by `code` rather than showing the raw
    // backend message verbatim.
    if (response?.status === 403 && SUBSCRIPTION_BLOCK_CODES.includes(response.data?.code)) {
      const { useAuthStore } = await import('@/stores/authStore')
      useAuthStore().setBlockedApiError({ code: response.data.code, message: response.data.message })

      if (router.currentRoute.value.path !== '/tenants-billing') {
        await router.push('/tenants-billing')
      }
    }

    return Promise.reject(error)
  }
)

export default api
