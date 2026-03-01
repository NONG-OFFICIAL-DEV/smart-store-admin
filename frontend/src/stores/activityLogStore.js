import { defineStore } from 'pinia'
import {
  getAllActivityLogsApi,
  getActivityLogByIdApi
} from '../api/activityLogService'

export const useActivityLogStore = defineStore('activityLog', {
  state: () => ({
    activityLogs: [],
    activityLog: null,
    pagination: {}
  }),

  actions: {
    async fetchActivityLogs(filters) {
      const res = await getAllActivityLogsApi(filters)
      this.activityLogs = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchActivityLogById(id) {
      const res = await getActivityLogByIdApi(id)
      this.activityLog = res.data.data
    }
  }
})
