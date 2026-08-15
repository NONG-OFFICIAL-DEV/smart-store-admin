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
      this.tables = res.data.data
      this.pagination = res.data.meta
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

    // ── Download QR as PNG ────────────────────────────────────────────────────
    async downloadQr(table) {
      try {
        // 1. Fetch the SVG blob from backend
        const res = await downloadQrCode(table.id)
        const svgBlob = res.data

        // 2. Read SVG as text
        const svgText = await svgBlob.text()

        // 3. Create an Image from the SVG
        const svgDataUrl =
          'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgText)

        const img = new Image()
        img.src = svgDataUrl

        await new Promise((resolve, reject) => {
          img.onload = resolve
          img.onerror = reject
        })

        // 4. Draw onto canvas at 2x resolution (high quality)
        const scale = 4 // ← increase for higher DPI (4 = 4× size)
        const canvas = document.createElement('canvas')
        canvas.width = img.width * scale
        canvas.height = img.height * scale

        const ctx = canvas.getContext('2d')
        ctx.imageSmoothingEnabled = false // crisp QR pixels
        ctx.scale(scale, scale)
        ctx.drawImage(img, 0, 0)

        // 5. Export as PNG and download
        canvas.toBlob(blob => {
          const url = URL.createObjectURL(blob)
          const link = document.createElement('a')
          link.href = url
          link.download = `QR-Table-${table.table_number}.png`
          link.click()
          URL.revokeObjectURL(url)
        }, 'image/png')
      } catch (err) {
        console.error('Failed to download QR as PNG', err)
      }
    }
  }
})
