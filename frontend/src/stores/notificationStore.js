import { defineStore } from 'pinia'
import { getAllNotificationsApi, getNotificationByIdApi, markNotificationAsReadApi, markAllNotificationsReadApi, deleteNotificationApi } from '../api/notificationService'

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notifications: [],
    notification:  null,
    pagination:    {},
  }),

  actions: {
    async fetchNotifications(filters) {
      const res              = await getAllNotificationsApi(filters)
      this.notifications     = res.data.data.data
      this.pagination        = res.data.data
    },
    async fetchNotificationById(id) {
      const res             = await getNotificationByIdApi(id)
      this.notification     = res.data.data
    },
    async markAsRead(id) {
      await markNotificationAsReadApi(id)
      const index = this.notifications.findIndex(item => item.id === id)
      if (index !== -1) this.notifications[index].read_at = new Date().toISOString()
    },
    async markAllAsRead() {
      await markAllNotificationsReadApi()
      this.notifications = this.notifications.map(item => ({ ...item, read_at: new Date().toISOString() }))
    },
    async deleteNotification(id) {
      await deleteNotificationApi(id)
      this.notifications = this.notifications.filter(item => item.id !== id)
    },
  },
})