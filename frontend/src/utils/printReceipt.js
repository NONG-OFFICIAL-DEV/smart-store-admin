// composables/useReceipt.js
// ─────────────────────────────────────────────────────────────────────────────
// Real thermal-printer output (ESC/POS), ported from retail-pos's
// printReceipt.js — same QZ Tray (desktop) / WebUSB (Android) auto-detect,
// same canvas-rasterized Khmer text (thermal printers have no Khmer glyphs).
// Adapted for this app: currency comes from the tenant's own setting (USD or
// KHR) instead of being hardcoded to Riel, and the printer name is passed in
// per call (branch.printer_name) instead of one hardcoded constant — this
// app is multi-tenant/multi-branch, each branch's till may have its own
// printer registered in QZ Tray.
// ─────────────────────────────────────────────────────────────────────────────

import { ref } from 'vue'
import { formatCurrency, formatKHR } from '@nong-official-dev/core'
import { useAuthStore } from '@/stores/authStore'

// Lazy load qz-tray
let _qz = null
async function getQz() {
  if (_qz) return _qz
  try {
    const mod = await import('qz-tray')
    _qz = mod.default ?? mod
    return _qz
  } catch (_) {
    return null
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// CONFIG
// ─────────────────────────────────────────────────────────────────────────────
const PAPER_WIDTH_PX = 576 // 80mm @203dpi (approx)
const CHAR_WIDTH = 48

// ─────────────────────────────────────────────────────────────────────────────
// ESC/POS constants
// ─────────────────────────────────────────────────────────────────────────────
const LF = '\x0A'

// ─────────────────────────────────────────────────────────────────────────────
// Order type / payment labels (Khmer + English)
// ─────────────────────────────────────────────────────────────────────────────
const ORDER_TYPE_LABEL = {
  dine_in: 'ញ៉ាំនៅហាង / Dine In',
  takeaway: 'យកទៅ / Takeaway',
  delivery: 'ដឹកជញ្ជូន / Delivery'
}
const PAY_LABEL = {
  cash: 'សាច់ប្រាក់ / Cash',
  card: 'កាត / Card',
  qr_code: 'QR Code',
  qr: 'QR',
  online: 'ផ្ទេរប្រាក់ / Transfer',
  transfer: 'ផ្ទេរប្រាក់ / Transfer'
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
const hasKhmer = s => /[ក-៿]/.test(s ?? '')
const isAndroid = () => /android/i.test(navigator.userAgent)
const line = () => '-'.repeat(CHAR_WIDTH) + LF
const dLine = () => '='.repeat(CHAR_WIDTH) + LF

// ── Auto-reconnect to previously paired USB printer ───────────────────────
async function autoConnectUsb(state) {
  try {
    const devices = await navigator.usb.getDevices()
    if (!devices.length) return false
    const dev = devices[0]
    await dev.open()
    if (dev.configuration === null) await dev.selectConfiguration(1)
    const found = findBulkOutEndpoint(dev)
    if (!found) return false
    await dev.claimInterface(found.interfaceNumber)
    state.usbDevice.value = { dev, ...found }
    state.usbConnected.value = true
    state.printMethod.value = 'usb'
    return true
  } catch (e) {
    console.warn('[useReceipt] autoConnectUsb failed:', e.message)
    return false
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Canvas → ESC/POS raster image
// Works for BOTH QZ Tray (desktop) and WebUSB (Android)
// ─────────────────────────────────────────────────────────────────────────────
function textToEscPosImage(text, { bold = false, fontSize = 22, align = 'left' } = {}) {
  const canvas = document.createElement('canvas')
  const lineH = fontSize + 10
  canvas.width = PAPER_WIDTH_PX
  const fontStr = (bold ? 'bold ' : '') + `${fontSize}px Hanuman, "Noto Sans Khmer", Arial, sans-serif`
  const ctx = canvas.getContext('2d')
  ctx.font = fontStr

  // Word wrap
  const words = text.split(' ')
  const lines = []
  let cur = ''
  for (const w of words) {
    const test = cur ? cur + ' ' + w : w
    if (ctx.measureText(test).width > PAPER_WIDTH_PX - 8) {
      if (cur) lines.push(cur)
      cur = w
    } else {
      cur = test
    }
  }
  if (cur) lines.push(cur)

  canvas.height = Math.max(1, lines.length * lineH + 4)
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.fillStyle = '#000'
  ctx.font = fontStr
  ctx.textBaseline = 'top'

  lines.forEach((ln, i) => {
    const tw = ctx.measureText(ln).width
    let x = 4
    if (align === 'center') x = Math.max(0, (PAPER_WIDTH_PX - tw) / 2)
    if (align === 'right') x = Math.max(0, PAPER_WIDTH_PX - tw - 4)
    ctx.fillText(ln, x, i * lineH + 2)
  })

  return canvasToRaster(canvas)
}

function canvasToRaster(canvas) {
  const ctx = canvas.getContext('2d')
  const { width, height } = canvas
  const imgData = ctx.getImageData(0, 0, width, height).data
  const bytesPerRow = Math.ceil(width / 8)
  const rows = []

  for (let y = 0; y < height; y++) {
    const row = new Uint8Array(bytesPerRow)
    for (let x = 0; x < width; x++) {
      const idx = (y * width + x) * 4
      const lum = 0.299 * imgData[idx] + 0.587 * imgData[idx + 1] + 0.114 * imgData[idx + 2]
      if (lum < 160) row[Math.floor(x / 8)] |= 0x80 >> x % 8
    }
    rows.push(row)
  }

  const xL = bytesPerRow & 0xff
  const xH = (bytesPerRow >> 8) & 0xff
  const yL = height & 0xff
  const yH = (height >> 8) & 0xff
  const out = [0x1d, 0x76, 0x30, 0x00, xL, xH, yL, yH]
  for (const row of rows) out.push(...row)
  return out
}

// Draws a table row with fixed pixel positions for perfect alignment
function tableRowToEscPosImage(cols, { bold = false, fontSize = 20, marginTop = 10 } = {}) {
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  const lineH = fontSize + marginTop + 10

  canvas.width = PAPER_WIDTH_PX
  canvas.height = lineH

  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.fillStyle = '#000'
  ctx.font = `${bold ? 'bold ' : ''}${fontSize}px Hanuman, "Noto Sans Khmer", Arial, sans-serif`
  ctx.textBaseline = 'bottom'
  const drawY = canvas.height - 5

  const colNameX = 4
  const colQtyX = PAPER_WIDTH_PX * 0.52
  const colTotalX = PAPER_WIDTH_PX - 4

  ctx.textAlign = 'left'
  ctx.fillText(cols[0], colNameX, drawY)
  ctx.textAlign = 'left'
  ctx.fillText(cols[1], colQtyX, drawY)
  ctx.textAlign = 'right'
  ctx.fillText(cols[2], colTotalX, drawY)

  return canvasToRaster(canvas)
}

function totalsRowToEscPosImage(label, value, { bold = false, fontSize = 20, marginTop = 8 } = {}) {
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  const lineH = fontSize + marginTop + 10

  canvas.width = PAPER_WIDTH_PX
  canvas.height = lineH

  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.fillStyle = '#000'
  ctx.font = `${bold ? 'bold ' : ''}${fontSize}px Hanuman, "Noto Sans Khmer", Arial, sans-serif`
  ctx.textBaseline = 'bottom'

  const drawY = canvas.height - 5

  ctx.textAlign = 'left'
  ctx.fillText(label, 4, drawY)
  ctx.textAlign = 'right'
  ctx.fillText(value, PAPER_WIDTH_PX - 4, drawY)

  return canvasToRaster(canvas)
}

// ─────────────────────────────────────────────────────────────────────────────
// Build receipt totals — `money` is injected so it reflects the tenant's
// actual currency (USD/KHR), not a hardcoded one.
// ─────────────────────────────────────────────────────────────────────────────
function buildTotals(r) {
  const items = r.items ?? []
  const subtotal = parseFloat(r.subtotal ?? items.reduce((s, i) => s + i.qty * i.unit_price, 0))
  const discount = parseFloat(r.discount ?? 0)
  const tax = parseFloat(r.tax ?? 0)
  const serviceCharge = parseFloat(r.service_charge ?? 0)
  const total = parseFloat(r.total ?? subtotal - discount + tax + serviceCharge)
  const cash = parseFloat(r.cash_tendered ?? 0)
  const change = parseFloat(r.change_given ?? 0)
  const payLbl = PAY_LABEL[r.payment_method] ?? r.payment_method ?? '-'
  return { items, subtotal, discount, tax, serviceCharge, total, cash, change, payLbl }
}

// ─────────────────────────────────────────────────────────────────────────────
// Build shared byte array — used by BOTH QZ Tray and WebUSB
// Khmer text → canvas raster image bytes (works on any printer)
// ASCII text → raw charCodeAt bytes
// ─────────────────────────────────────────────────────────────────────────────
function buildBytes(r, money) {
  const { items, subtotal, discount, tax, serviceCharge, total, cash, change, payLbl } = buildTotals(r)

  const bytes = []
  const b = arr => bytes.push(...arr)

  // ASCII text → raw bytes
  const t = str => {
    for (let i = 0; i < str.length; i++) bytes.push(str.charCodeAt(i) & 0xff)
  }

  // Khmer or mixed text → canvas raster image bytes
  const img = (text, opts = {}) => b(textToEscPosImage(text, opts))

  const E = 0x1b
  const G = 0x1d

  // ── INIT ──
  b([E, 0x40])

  // ── HEADER ──
  b([E, 0x61, 0x01]) // center
  if (hasKhmer(r.branch_name)) {
    img(r.branch_name ?? 'MY STORE', { bold: true, fontSize: 26, align: 'center' })
  } else {
    b([E, 0x45, 0x01, E, 0x21, 0x10]) // bold + double height
    t((r.branch_name ?? 'MY STORE') + '\n')
    b([E, 0x21, 0x00, E, 0x45, 0x00])
  }
  if (r.branch_address) {
    if (hasKhmer(r.branch_address)) {
      img(r.branch_address, { fontSize: 18, align: 'center' })
    } else {
      t(r.branch_address + '\n')
    }
  }
  if (r.branch_phone) t('Tel: ' + r.branch_phone + '\n')

  // ── ORDER INFO ──
  b([E, 0x61, 0x00]) // left
  t(line())
  t(twoCol('Order #:', r.order_number ?? '-') + '\n')
  t(twoCol('Date:', r.printed_at ?? r.date ?? new Date().toLocaleString()) + '\n')

  if (r.cashier) {
    if (hasKhmer(r.cashier)) {
      t('Cashier: ')
      img(r.cashier, { fontSize: 20 })
    } else {
      t(twoCol('Cashier:', r.cashier) + '\n')
    }
  }
  if (r.order_type) {
    t(twoCol('Type:', ORDER_TYPE_LABEL[r.order_type] ?? r.order_type) + '\n')
  }
  if (r.table_number) {
    t(twoCol('Table:', String(r.table_number)) + '\n')
  }
  if (r.customer_name) {
    if (hasKhmer(r.customer_name)) {
      t('Customer: ')
      img(r.customer_name, { fontSize: 20 })
    } else {
      t(twoCol('Customer:', r.customer_name) + '\n')
    }
  }
  t(line())

  // ── ITEMS TABLE HEADER ──
  b([E, 0x61, 0x00]) // left
  b(tableRowToEscPosImage(['Name/ឈ្មោះ', 'Qty/ចំនួន', 'Total/សរុប'], { bold: true, fontSize: 18 }))
  t(line())

  // ── ITEMS ──
  for (const item of items) {
    const label = (item.name ?? '') + (item.unit ? ` (${item.unit})` : '')
    const qtyStr = `${item.qty} x ${money(item.unit_price)}`
    const totalAmt = money(item.total_price ?? item.qty * item.unit_price)

    b(tableRowToEscPosImage([label, qtyStr, totalAmt], { fontSize: 24, marginTop: 15 }))
  }

  // ── TOTALS ──
  t(line())
  b(totalsRowToEscPosImage('Subtotal / សរុបរង:', money(subtotal)))

  if (discount > 0) {
    b(totalsRowToEscPosImage('Discount / បញ្ចុះ:', '-' + money(discount)))
  }
  if (tax > 0) {
    b(totalsRowToEscPosImage('Tax / ពន្ធ:', money(tax)))
  }
  if (serviceCharge > 0) {
    b(totalsRowToEscPosImage('Service / សេវា:', money(serviceCharge)))
  }

  t(dLine())
  b(totalsRowToEscPosImage('Total / សរុបទាំងអស់:', money(total), { bold: true, fontSize: 26, marginTop: 12 }))
  t(dLine())

  // ── PAYMENT ── pay-later has nothing to report yet; flag it clearly
  // instead of printing a payment method that was never actually taken.
  if (r.payment_method === 'pay_later') {
    b(totalsRowToEscPosImage('UNPAID / មិនទាន់បង់ប្រាក់', '', { bold: true }))
  } else if (r.payment_method) {
    b(totalsRowToEscPosImage('Payment / បង់:', payLbl))
    if (cash > 0) {
      b(totalsRowToEscPosImage('Cash / ទទួល:', money(cash)))
    }
    if (change > 0) {
      b(totalsRowToEscPosImage('Change / អាប:', money(change), { bold: true }))
    }
  }

  // ── FOOTER ──
  t(line())
  b([E, 0x61, 0x01]) // center
  t('Thank you for your purchase!\n')
  img('អរគុណសម្រាប់ការទិញ!', { fontSize: 20, align: 'center' })
  t('\n\n\n')
  b([G, 0x56, 0x41, 0x05]) // cut

  return new Uint8Array(bytes)
}

function twoCol(l, r, w = CHAR_WIDTH) {
  const gap = Math.max(1, w - l.length - r.length)
  return l + ' '.repeat(gap) + r
}

// ─────────────────────────────────────────────────────────────────────────────
// QZ Tray — wraps byte array as base64 data objects
// ─────────────────────────────────────────────────────────────────────────────
function uint8ToBase64(bytes) {
  let bin = ''
  for (const b of bytes) bin += String.fromCharCode(b)
  return btoa(bin)
}

function buildQzJob(r, money) {
  const bytes = buildBytes(r, money)
  return [{ type: 'raw', format: 'base64', data: uint8ToBase64(bytes) }]
}

// ─────────────────────────────────────────────────────────────────────────────
// WebUSB helpers
// ─────────────────────────────────────────────────────────────────────────────
function findBulkOutEndpoint(device) {
  for (const iface of device.configuration.interfaces) {
    for (const alt of iface.alternates) {
      for (const ep of alt.endpoints) {
        if (ep.direction === 'out' && ep.type === 'bulk') {
          return { interfaceNumber: iface.interfaceNumber, endpoint: ep }
        }
      }
    }
  }
  return null
}

// ─────────────────────────────────────────────────────────────────────────────
// Composable
// ─────────────────────────────────────────────────────────────────────────────
export function useReceipt() {
  const authStore = useAuthStore()
  const money = v => (authStore.currency === 'KHR' ? formatKHR(v) : formatCurrency(v))

  const printing = ref(false)
  const error = ref(null)
  const printMethod = ref(null)

  const usbDevice = ref(null)
  const usbConnected = ref(false)
  const usbSupported = 'usb' in navigator

  // ── Connect USB (desktop Chrome/Edge over a cable, or Android) ───────────
  async function connectUsb() {
    error.value = null
    try {
      const dev = await navigator.usb.requestDevice({ filters: [] })
      await dev.open()
      if (dev.configuration === null) await dev.selectConfiguration(1)

      const found = findBulkOutEndpoint(dev)
      if (!found) throw new Error('No bulk-out USB endpoint found. Make sure printer is selected.')

      await dev.claimInterface(found.interfaceNumber)
      usbDevice.value = { dev, ...found }
      usbConnected.value = true
      printMethod.value = 'usb'
      return true
    } catch (e) {
      error.value = e.message
      usbConnected.value = false
      return false
    }
  }

  // ── Disconnect USB ────────────────────────────────────────────────────────
  async function disconnectUsb() {
    if (usbDevice.value) {
      try {
        await usbDevice.value.dev.releaseInterface(usbDevice.value.interfaceNumber)
        await usbDevice.value.dev.close()
      } catch (_) {}
      usbDevice.value = null
      usbConnected.value = false
    }
  }

  // ── Print via WebUSB ──────────────────────────────────────────────────────
  async function printUsb(receipt, _retry = false) {
    if (!usbConnected.value || !usbDevice.value) {
      const reconnected = await autoConnectUsb({ usbDevice, usbConnected, printMethod })
      if (!reconnected) {
        error.value = 'not_connected'
        return false
      }
    }
    const bytes = buildBytes(receipt, money)
    const CHUNK = 64
    try {
      for (let i = 0; i < bytes.length; i += CHUNK) {
        await usbDevice.value.dev.transferOut(usbDevice.value.endpoint.endpointNumber, bytes.slice(i, i + CHUNK))
      }
      return true
    } catch (e) {
      usbConnected.value = false
      usbDevice.value = null
      if (!_retry) {
        console.warn('[useReceipt] USB error, retrying once...', e.message)
        await new Promise(r => setTimeout(r, 600))
        return printUsb(receipt, true)
      }
      error.value = 'disconnected'
      return false
    }
  }

  // ── Print via QZ Tray ─────────────────────────────────────────────────────
  async function printQz(receipt, printerName) {
    const qz = await getQz()
    if (!qz) throw new Error('QZ Tray not loaded. Make sure qz-tray is installed.')
    if (!qz.websocket.isActive()) await qz.websocket.connect()
    const printer = await qz.printers.find(printerName)
    const config = qz.configs.create(printer)
    await qz.print(config, buildQzJob(receipt, money))
    return true
  }

  // ── Check connectivity without printing anything ─────────────────────────
  // USB is checked first wherever the browser supports it at all (desktop
  // Chrome/Edge with the printer on a cable, or Android) — is a device
  // already paired, or can we silently reconnect to one that was, with no
  // permission prompt? Falls back to QZ Tray only on platforms with no
  // WebUSB (desktop Safari/Firefox) or once a cable isn't actually
  // connected/paired. Used by the Settings > Printer tab's status
  // indicator.
  async function checkConnection(printerName) {
    if (usbSupported) {
      if (usbConnected.value) return { connected: true, method: 'usb' }
      const reconnected = await autoConnectUsb({ usbDevice, usbConnected, printMethod })
      if (reconnected) return { connected: true, method: 'usb' }
    }
    if (isAndroid()) {
      // No QZ Tray equivalent on Android — USB is the only path, and it
      // already failed above.
      return { connected: false, method: 'usb', reason: 'not_connected' }
    }
    if (!printerName) {
      return { connected: false, method: 'qz', reason: 'no_printer_configured' }
    }
    try {
      const qz = await getQz()
      if (!qz) return { connected: false, method: 'qz', reason: 'qz_not_loaded' }
      if (!qz.websocket.isActive()) await qz.websocket.connect()
      await qz.printers.find(printerName)
      return { connected: true, method: 'qz' }
    } catch (e) {
      return { connected: false, method: 'qz', reason: e.message }
    }
  }

  // ── Auto-detect and print ─────────────────────────────────────────────────
  // printerName: the branch's configured printer (branch.printer_name) —
  // only used for the QZ Tray fallback. A directly cabled/paired USB
  // printer is always tried first wherever the browser supports WebUSB at
  // all (desktop Chrome/Edge or Android) — printerName is irrelevant then.
  // QZ Tray only comes into play if no USB device is connected/reconnectable,
  // which is always true on Android (no QZ Tray equivalent there) and
  // optional on desktop.
  const print = async (receipt, printerName) => {
    if (!receipt) {
      error.value = 'No receipt data'
      return false
    }
    printing.value = true
    error.value = null
    try {
      if (usbSupported) {
        printMethod.value = 'usb'
        if (await printUsb(receipt)) return true
        // Clear the USB-attempt's error before falling through to QZ so a
        // genuine QZ failure isn't masked by the stale "not_connected".
        error.value = null
      }
      if (isAndroid()) {
        error.value = 'not_connected'
        return false
      }
      if (!printerName) {
        error.value = 'no_printer_configured'
        return false
      }
      printMethod.value = 'qz'
      return await printQz(receipt, printerName)
    } catch (e) {
      error.value = e.message ?? 'Print failed'
      console.error('[useReceipt]', e)
      return false
    } finally {
      printing.value = false
    }
  }

  return {
    printing,
    error,
    printMethod,
    usbConnected,
    usbSupported,
    print,
    checkConnection,
    connectUsb,
    disconnectUsb
  }
}
