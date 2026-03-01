import { defineStore } from 'pinia'
import auditLogService from '../api/auditLog'

export const useAuditLogStore = defineStore('auditLogStore', {
  state: () => ({
    logs: [],
    log: {},
  }),

  actions: {
    async getAllAuditLogs(filters) {
      const res = await auditLogService.getAll(filters)
      this.logs = res.data
    },
    async getById(id) {
      const res = await auditLogService.getById(id)
      this.log = res.data
    },
    exportCSV() {
      auditLogService.export()
    }
  }
})
