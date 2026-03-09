import { defineStore } from 'pinia'
import {
  getAllTablesApi,
  getTableByIdApi,
  createTableApi,
  updateTableApi,
  deleteTableApi,
  getQrCode,
  downloadQrCode,
  regenerateQrCode
} from '../api/tableService'

export const useTableStore = defineStore('table', {
  state: () => ({
    tables: [],
    table: null,
    pagination: {},
    qrData: {}
  }),

  actions: {
    async fetchTables(filters) {
      const res = await getAllTablesApi(filters)
      this.tables = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchTableById(id) {
      const res = await getTableByIdApi(id)
      this.table = res.data.data
    },
    async createTable(data) {
      const res = await createTableApi(data)
      this.tables.unshift(res.data.data)
    },
    async updateTable(id, data) {
      const res = await updateTableApi(id, data)
      const index = this.tables.findIndex(item => item.id === id)
      if (index !== -1) this.tables[index] = res.data.data
    },
    async deleteTable(id) {
      await deleteTableApi(id)
      this.tables = this.tables.filter(item => item.id !== id)
    },
    // ── Open QR dialog ─────────────────────────────────────────────────────────
    async fetchQrCodeTable(tableId) {
      const res = await getQrCode(tableId)
      this.qrData = res.data.data
    },
    // ── Regenerate QR ──────────────────────────────────────────────────────────
    async regenerateQr(tableId) {
      this.qrLoading = true
      try {
        const res = await regenerateQrCode(tableId)
        this.qrData = res.data.data
      } catch (err) {
        console.error('Failed to regenerate QR', err)
      } finally {
        this.qrLoading = false
      }
    },

    // ── Download QR ────────────────────────────────────────────────────────────
    async downloadQr(table) {
      try {
        const res = await downloadQrCode(table.id)
        const url = URL.createObjectURL(res.data)
        const link = document.createElement('a')
        link.href = url
        link.download = `QR-Table-${table.table_number}.png`
        link.click()
        URL.revokeObjectURL(url)
      } catch (err) {
        console.error('Failed to download QR', err)
      }
    }
  }
})
