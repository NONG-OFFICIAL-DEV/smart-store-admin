import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/authStore'
import { useDate } from '@/composables/useDate'
import { getEcho } from '@/utils/echo'
import {
  getAllNotificationsApi,
  getUnreadNotificationCountApi,
  markNotificationAsReadApi,
  markAllNotificationsReadApi
} from '@/api/notificationService'

// Echo/Reverb (see onMounted below) is the primary freshness mechanism — this
// is just a fallback in case a push event is missed (dropped socket, brief
// disconnect). The bell's own onMounted still fires one immediate fetch
// regardless of this value, so the badge is never stale on load.
const POLL_FALLBACK_MS = 5 * 60 * 1000

// Shared <AppNotificationBell> wiring — used by AppBarActions.vue (app bar)
// and SidebarFooter.vue (Sidebar-only/POS-focus footer) so the fetch
// functions and the live Echo listener exist in exactly one place.
export function useNotificationBell() {
  const { t } = useI18n()
  const { formatDateTime } = useDate()
  const authStore = useAuthStore()
  const router = useRouter()

  const bellRef = ref(null)

  async function fetchRecentNotifications({ perPage }) {
    const { data } = await getAllNotificationsApi({ perPage })
    return { items: data.data, unreadCount: data.meta.unread_count }
  }

  async function fetchUnreadNotificationCount() {
    const { data } = await getUnreadNotificationCountApi()
    return data.data.count
  }

  const goToNotifications = () => router.push('/notifications')

  // Live push — the router guard's fetchMe() call already connects Echo
  // before any layout mounts, so a live instance should already exist here.
  let notificationChannel = null

  onMounted(() => {
    const echo = getEcho()
    const userId = authStore.me?.id
    if (echo && userId) {
      notificationChannel = echo.private(`App.Models.User.${userId}`)
      notificationChannel.listen('.notification.created', () => {
        bellRef.value?.refresh()
      })
    }
  })

  onUnmounted(() => {
    if (notificationChannel) {
      notificationChannel.stopListening('.notification.created')
    }
  })

  return {
    bellRef,
    bellProps: {
      fetchRecent: fetchRecentNotifications,
      fetchUnreadCount: fetchUnreadNotificationCount,
      markRead: markNotificationAsReadApi,
      markAllRead: markAllNotificationsReadApi,
      message: n => n.title,
      formatDate: formatDateTime,
      title: t('notification.title'),
      emptyText: t('notification.no_notifications'),
      markAllReadText: t('notification.mark_all_read'),
      viewAllText: t('btn.view_all'),
      pollIntervalMs: POLL_FALLBACK_MS
    },
    goToNotifications
  }
}
