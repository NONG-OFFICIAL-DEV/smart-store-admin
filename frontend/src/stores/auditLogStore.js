import { defineStore } from 'pinia'
import auditLogService from '../api/auditLog'

export const useAuditLogStore = defineStore('auditLogStore', {
  state: () => ({
    logs: [],
    log: {},
    pagination: {}
  }),

  actions: {
    async getAllAuditLogs(filters) {
      const res = await auditLogService.getAll(filters)
      this.logs = res.data.data
      this.pagination = res.data.meta
    },
    async getById(id) {
      const res = await auditLogService.getById(id)
      this.log = res.data.data
    },
    exportCSV() {
      auditLogService.export()
    }
  }
})
