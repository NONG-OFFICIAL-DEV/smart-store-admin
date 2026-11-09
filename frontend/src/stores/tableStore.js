import { defineStore } from 'pinia'
import {
  getAllTablesApi,
  getTableByIdApi,
  createTableApi,
  updateTableApi,
  updateTableStatusApi,
  getActiveOrderByTableApi,
  getOrdersByTableApi,
  deleteTableApi,
  reorderTablesApi,
  getQrCode,
  downloadQrCode,
  regenerateQrCode
} from '../api/tableService'

export const useTableStore = defineStore('table', {
  state: () => ({
    tables: [],
    table: null,
    pagination: {},
    qrData: {},
    // Lazily populated per table id, only when its detail is actually
    // opened — avoids one active-order request per table on every load.
    activeOrders: {}
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
    async updateTableStatus(id, status) {
      const res = await updateTableStatusApi(id, status)
      const index = this.tables.findIndex(item => item.id === id)
      if (index !== -1) this.tables[index] = res.data.data
    },
    async fetchActiveOrderForTable(id) {
      const res = await getActiveOrderByTableApi(id)
      this.activeOrders = { ...this.activeOrders, [id]: res.data.data }
      return res.data.data
    },
    async fetchOrdersForTable(id) {
      const res = await getOrdersByTableApi(id)
      return res.data.data
    },
    async deleteTable(id) {
      await deleteTableApi(id)
      this.tables = this.tables.filter(item => item.id !== id)
    },
    async reorderTables(tableIds) {
      await reorderTablesApi(tableIds)
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

    // ── Download QR as a branded PNG card ────────────────────────────────────
    // Bakes everything the dialog's live preview already shows (store name,
    // table label, "scan to order" caption, optional promo tagline, size +
    // colors) into the actual exported file — it used to just rasterize the
    // bare QR straight from the backend, discarding all of that.
    // Caller passes already-translated strings (tableLabel/scanLabel) since
    // this store has no access to i18n.
    async downloadQr(table, options = {}) {
      const {
        branchName = '',
        promoText = '',
        tableLabel = `Table ${table.table_number}`,
        scanLabel = 'Scan to order',
        size = 200,
        fgColor = '#1a1a2e',
        bgColor = '#ffffff'
      } = options

      try {
        // 1. Fetch the SVG blob from backend
        const res = await downloadQrCode(table.id)
        const svgText = await res.data.text()

        // 2. Create an Image from the SVG
        const svgDataUrl =
          'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgText)
        const img = new Image()
        img.src = svgDataUrl
        await new Promise((resolve, reject) => {
          img.onload = resolve
          img.onerror = reject
        })

        // 3. Lay out the branded card — same proportions as the on-screen
        // .qr-card preview (TableQRDialog.vue) at a high-res scale for
        // print quality.
        const scale = 4
        const padding = 24
        const gap = 10
        const brandFontSize = 15
        const labelFontSize = 16
        const captionFontSize = 11
        const promoFontSize = 10
        const cardWidth = size + padding * 2

        let cardHeight = padding + size + gap + labelFontSize + 6 + captionFontSize + padding
        if (branchName) cardHeight += brandFontSize + gap
        if (promoText) cardHeight += 6 + promoFontSize

        const canvas = document.createElement('canvas')
        canvas.width = cardWidth * scale
        canvas.height = cardHeight * scale
        const ctx = canvas.getContext('2d')
        ctx.scale(scale, scale)

        // Background
        ctx.fillStyle = bgColor
        if (ctx.roundRect) {
          ctx.beginPath()
          ctx.roundRect(0, 0, cardWidth, cardHeight, 18)
          ctx.fill()
        } else {
          ctx.fillRect(0, 0, cardWidth, cardHeight)
        }

        ctx.textAlign = 'center'
        let y = padding

        // Store name, centered above the QR
        if (branchName) {
          ctx.fillStyle = fgColor
          ctx.font = `700 ${brandFontSize}px sans-serif`
          ctx.fillText(branchName.toUpperCase(), cardWidth / 2, y + brandFontSize)
          y += brandFontSize + gap
        }

        // QR — crisp pixels, no smoothing
        ctx.imageSmoothingEnabled = false
        ctx.drawImage(img, padding, y, size, size)
        y += size + gap

        // Table label
        ctx.fillStyle = fgColor
        ctx.font = `700 ${labelFontSize}px sans-serif`
        ctx.fillText(tableLabel, cardWidth / 2, y + labelFontSize)
        y += labelFontSize + 6

        // "Scan to order" caption
        ctx.globalAlpha = 0.6
        ctx.font = `${captionFontSize}px sans-serif`
        ctx.fillText(scanLabel, cardWidth / 2, y + captionFontSize)
        ctx.globalAlpha = 1
        y += captionFontSize

        // Optional promo tagline (e.g. "Follow us @..."), fainter still
        if (promoText) {
          y += 6
          ctx.globalAlpha = 0.45
          ctx.font = `${promoFontSize}px sans-serif`
          ctx.fillText(promoText, cardWidth / 2, y + promoFontSize)
          ctx.globalAlpha = 1
        }

        // 4. Export as PNG and download
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
    },

    // ── Download the raw QR only, no card/branding ───────────────────────────
    // For tenants who'll design their own flyer/table-tent in Canva/
    // Illustrator/etc. — plain vector SVG, straight from the backend, so it
    // never pixelates no matter how large they scale it.
    async downloadQrOnly(table) {
      try {
        const res = await downloadQrCode(table.id)
        const url = URL.createObjectURL(res.data)
        const link = document.createElement('a')
        link.href = url
        link.download = `QR-Table-${table.table_number}.svg`
        link.click()
        URL.revokeObjectURL(url)
      } catch (err) {
        console.error('Failed to download plain QR', err)
      }
    }
  }
})
