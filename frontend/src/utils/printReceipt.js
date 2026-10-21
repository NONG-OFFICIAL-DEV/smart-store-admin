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
import i18n from '@/plugins/i18n'

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

// Renders the WHOLE header info block (Order #/Date/Cashier/Type/Table/
// Customer) as ONE image. Mixing raw ESC/POS text rows (printer's built-in
// Font A, 12x24 dots) with an image row at a different canvas font size is
// what made "Type:" print visibly smaller than "Order #:"/"Date:"/
// "Table:" — rendering every row of this block together, same font/size,
// removes the mismatch structurally instead of trying to hand-match sizes
// between two completely different rendering paths. fontSize 24 / lineH 30
// approximates Font A's 12x24-dot glyph height on this 576-dot-wide paper.
function buildHeaderInfoImage(rows, { fontSize = 24, lineHeight = 30 } = {}) {
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  canvas.width = PAPER_WIDTH_PX
  canvas.height = rows.length * lineHeight

  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.fillStyle = '#000'
  ctx.font = `${fontSize}px Hanuman, "Noto Sans Khmer", Arial, sans-serif`
  ctx.textBaseline = 'bottom'

  rows.forEach(({ label, value }, i) => {
    const drawY = (i + 1) * lineHeight - 5
    ctx.textAlign = 'left'
    ctx.fillText(label, 4, drawY)
    ctx.textAlign = 'right'
    ctx.fillText(value, PAPER_WIDTH_PX - 4, drawY)
  })

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
  // One image for the whole block (see buildHeaderInfoImage()) — every row
  // the same font/size regardless of whether its value contains Khmer, so
  // "Type:" can't print smaller than "Order #:"/"Date:"/"Table:" again.
  b([E, 0x61, 0x00]) // left
  t(line())
  const infoRows = [
    { label: 'Order #:', value: r.order_number ?? '-' },
    { label: 'Date:', value: r.printed_at ?? r.date ?? new Date().toLocaleString() }
  ]
  if (r.cashier) infoRows.push({ label: 'Cashier:', value: r.cashier })
  if (r.order_type) infoRows.push({ label: 'Type:', value: ORDER_TYPE_LABEL[r.order_type] ?? r.order_type })
  if (r.table_number) infoRows.push({ label: 'Table:', value: String(r.table_number) })
  if (r.customer_name) infoRows.push({ label: 'Customer:', value: r.customer_name })
  b(buildHeaderInfoImage(infoRows))
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

// ─────────────────────────────────────────────────────────────────────────────
// QZ Tray — whole-receipt-as-one-image renderer.
//
// QZ Tray (desktop print spooler) does NOT pass a mixed stream of raw
// ESC/POS command bytes + plain-ASCII text bytes + embedded raster-image
// bytes through to the printer faithfully the way WebUSB does (WebUSB talks
// straight to the printer's bulk endpoint; QZ's default raw/base64 job gets
// re-interpreted somewhere in the spooler/driver layer, garbling every
// byte it touches — not just the Khmer raster data). Rendering the ENTIRE
// receipt onto one canvas and sending it as a single ESC/POS raster image
// sidesteps this: there is no text for anything downstream to mis-encode.
// buildBytes()/printUsb() (WebUSB) is untouched — it already works.
// ─────────────────────────────────────────────────────────────────────────────
function wrapLines(ctx, text, fontSize, bold) {
  ctx.font = `${bold ? 'bold ' : ''}${fontSize}px Hanuman, "Noto Sans Khmer", Arial, sans-serif`
  const words = (text ?? '').split(' ')
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
  return lines
}

const FONT_STACK = 'Hanuman, "Noto Sans Khmer", Arial, sans-serif'

// Builds one tall canvas top-to-bottom in two passes: first collect "ops"
// (each knows its own height + how to draw itself), then lay them out once
// the total height is known — same content/formatting as buildBytes(),
// just rendered as pixels instead of bytes, so Khmer and ASCII are handled
// identically (no more hasKhmer() branching needed for this path).
function buildReceiptPng(r, money) {
  const { items, subtotal, discount, tax, serviceCharge, total, cash, change, payLbl } = buildTotals(r)
  const measure = document.createElement('canvas').getContext('2d')
  const ops = []

  const addCentered = (text, { bold = false, fontSize = 20 } = {}) => {
    if (!text) return
    const lines = wrapLines(measure, text, fontSize, bold)
    const lineH = fontSize + 10
    ops.push({
      height: lines.length * lineH,
      draw: (ctx, y) => {
        ctx.font = `${bold ? 'bold ' : ''}${fontSize}px ${FONT_STACK}`
        ctx.textAlign = 'center'
        ctx.textBaseline = 'top'
        lines.forEach((ln, i) => ctx.fillText(ln, PAPER_WIDTH_PX / 2, y + i * lineH + 2))
      }
    })
  }

  const addLeft = (text, { fontSize = 20 } = {}) => {
    if (!text) return
    const lineH = fontSize + 10
    ops.push({
      height: lineH,
      draw: (ctx, y) => {
        ctx.font = `${fontSize}px ${FONT_STACK}`
        ctx.textAlign = 'left'
        ctx.textBaseline = 'top'
        ctx.fillText(text, 4, y + 2)
      }
    })
  }

  const addRow = (label, value, { bold = false, fontSize = 20, marginTop = 8 } = {}) => {
    const lineH = fontSize + marginTop + 10
    ops.push({
      height: lineH,
      draw: (ctx, y) => {
        ctx.font = `${bold ? 'bold ' : ''}${fontSize}px ${FONT_STACK}`
        ctx.textBaseline = 'bottom'
        const drawY = y + lineH - 5
        ctx.textAlign = 'left'
        ctx.fillText(label, 4, drawY)
        ctx.textAlign = 'right'
        ctx.fillText(value, PAPER_WIDTH_PX - 4, drawY)
      }
    })
  }

  const addTableRow = (cols, { bold = false, fontSize = 20, marginTop = 10 } = {}) => {
    const lineH = fontSize + marginTop + 10
    ops.push({
      height: lineH,
      draw: (ctx, y) => {
        ctx.font = `${bold ? 'bold ' : ''}${fontSize}px ${FONT_STACK}`
        ctx.textBaseline = 'bottom'
        const drawY = y + lineH - 5
        ctx.textAlign = 'left'
        ctx.fillText(cols[0], 4, drawY)
        ctx.textAlign = 'left'
        ctx.fillText(cols[1], PAPER_WIDTH_PX * 0.52, drawY)
        ctx.textAlign = 'right'
        ctx.fillText(cols[2], PAPER_WIDTH_PX - 4, drawY)
      }
    })
  }

  const addDivider = (double = false) => {
    const h = 16
    ops.push({
      height: h,
      draw: (ctx, y) => {
        ctx.fillStyle = '#000'
        if (double) {
          ctx.fillRect(4, y + 5, PAPER_WIDTH_PX - 8, 2)
          ctx.fillRect(4, y + 9, PAPER_WIDTH_PX - 8, 2)
        } else {
          ctx.fillRect(4, y + 7, PAPER_WIDTH_PX - 8, 2)
        }
      }
    })
  }

  // ── HEADER ──
  addCentered(r.branch_name ?? 'MY STORE', { bold: true, fontSize: 26 })
  if (r.branch_address) addCentered(r.branch_address, { fontSize: 18 })
  if (r.branch_phone) addLeft('Tel: ' + r.branch_phone)
  addDivider()
  addRow('Order #:', r.order_number ?? '-')
  addRow('Date:', r.printed_at ?? r.date ?? new Date().toLocaleString())
  if (r.cashier) addRow('Cashier:', r.cashier)
  if (r.order_type) addRow('Type:', ORDER_TYPE_LABEL[r.order_type] ?? r.order_type)
  if (r.table_number) addRow('Table:', String(r.table_number))
  if (r.customer_name) addRow('Customer:', r.customer_name)
  addDivider()

  // ── ITEMS ──
  addTableRow(['Name/ឈ្មោះ', 'Qty/ចំនួន', 'Total/សរុប'], { bold: true, fontSize: 18 })
  addDivider()
  for (const item of items) {
    const label = (item.name ?? '') + (item.unit ? ` (${item.unit})` : '')
    const qtyStr = `${item.qty} x ${money(item.unit_price)}`
    const totalAmt = money(item.total_price ?? item.qty * item.unit_price)
    addTableRow([label, qtyStr, totalAmt], { fontSize: 24, marginTop: 15 })
  }

  // ── TOTALS ──
  addDivider()
  addRow('Subtotal / សរុបរង:', money(subtotal))
  if (discount > 0) addRow('Discount / បញ្ចុះ:', '-' + money(discount))
  if (tax > 0) addRow('Tax / ពន្ធ:', money(tax))
  if (serviceCharge > 0) addRow('Service / សេវា:', money(serviceCharge))
  addDivider(true)
  addRow('Total / សរុបទាំងអស់:', money(total), { bold: true, fontSize: 26, marginTop: 12 })
  addDivider(true)

  // ── PAYMENT ──
  if (r.payment_method === 'pay_later') {
    addRow('UNPAID / មិនទាន់បង់ប្រាក់', '', { bold: true })
  } else if (r.payment_method) {
    addRow('Payment / បង់:', payLbl)
    if (cash > 0) addRow('Cash / ទទួល:', money(cash))
    if (change > 0) addRow('Change / អាប:', money(change), { bold: true })
  }

  // ── FOOTER ──
  addDivider()
  addCentered('Thank you for your purchase!', { fontSize: 20 })
  addCentered('អរគុណសម្រាប់ការទិញ!', { fontSize: 20 })

  // ── RENDER ──
  const PAD = 20
  const totalHeight = PAD * 2 + ops.reduce((sum, op) => sum + op.height, 0)
  const canvas = document.createElement('canvas')
  canvas.width = PAPER_WIDTH_PX
  canvas.height = Math.max(1, totalHeight)
  const ctx = canvas.getContext('2d')
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.fillStyle = '#000'

  let y = PAD
  for (const op of ops) {
    op.draw(ctx, y)
    y += op.height
  }

  return canvas.toDataURL('image/png').split(',')[1]
}

// A short diagnostic image — one Khmer line, used by the "Test QZ Encoding"
// button to isolate whether the image path alone renders correctly.
function buildTestKhmerPng(text) {
  const measure = document.createElement('canvas').getContext('2d')
  const fontSize = 28
  const lines = wrapLines(measure, text, fontSize, false)
  const lineH = fontSize + 10
  const PAD = 10
  const canvas = document.createElement('canvas')
  canvas.width = PAPER_WIDTH_PX
  canvas.height = PAD * 2 + lines.length * lineH
  const ctx = canvas.getContext('2d')
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.fillStyle = '#000'
  ctx.font = `${fontSize}px ${FONT_STACK}`
  ctx.textAlign = 'center'
  ctx.textBaseline = 'top'
  lines.forEach((ln, i) => ctx.fillText(ln, PAPER_WIDTH_PX / 2, PAD + i * lineH + 2))
  return canvas.toDataURL('image/png').split(',')[1]
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
// Browser print (window.print(), via a hidden iframe)
//
// Printing the main page directly used to let Vuetify's dialog overlay
// (teleported outside #app, so "#app { display:none }" print CSS never
// touched it) print on top of the receipt, and could pick up whichever
// OTHER print-only copy happened to still be in the DOM at the same
// moment (e.g. BillDrawer's "bill" copy printing instead of PaymentDialog's
// own "receipt" copy, since both shared the id #receipt-print-root). A
// hidden iframe's document contains ONLY what we explicitly write into it
// — nothing else the app renders can ever appear on paper, and there is no
// shared DOM/id to collide on.
// ─────────────────────────────────────────────────────────────────────────────
function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

function rowHtml(label, value, cls = '') {
  return `<div class="row ${cls}"><span class="label">${escapeHtml(label)}</span><span class="value">${escapeHtml(value)}</span></div>`
}

// width 70mm with asymmetric padding (0 3mm 0 2mm) — the printable area
// measured slightly narrower on the right than the left on the target
// printer, so the extra 1mm on the right keeps the price column from
// clipping without shifting the left edge.
const PRINT_IFRAME_STYLE = `
  @page { size: 80mm auto; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    width: 70mm;
    max-width: 100%;
    padding: 0 3mm 0 2mm;
    font-family: 'Hanuman', 'Noto Sans Khmer', Arial, sans-serif;
    font-size: 12px;
    font-weight: 500;
    color: #000;
  }
  .center { text-align: center; }
  .name { font-size: 14px; font-weight: 700; }
  .muted { font-size: 11px; }
  .title { font-size: 13px; font-weight: 700; margin-top: 4px; }
  .subtitle { font-size: 11px; font-weight: 700; }
  hr { border: none; border-top: 1px dashed #000; margin: 6px 0; width: 100%; }
  .row { display: flex; justify-content: space-between; align-items: baseline; gap: 4px; width: 100%; max-width: 100%; margin-bottom: 3px; }
  .row .label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
  .row .value { white-space: nowrap; flex-shrink: 0; font-weight: 600; }
  .row.total .label, .row.total .value { font-weight: 700; font-size: 14px; }
  .item { width: 100%; max-width: 100%; margin-bottom: 6px; }
  .item-note { font-size: 10px; margin-top: 1px; }
`

// Mirrors PosReceiptContent.vue's template 1:1 (same i18n keys, same
// bilingual payment-method labels, same mode-driven BILL/NOT PAID vs
// RECEIPT/PAID banner) — built as a plain HTML string instead of Vue DOM
// since the iframe is a brand-new document with no access to the parent
// page's component tree.
function buildReceiptHtml(r, mode) {
  const authStore = useAuthStore()
  const money = v => (authStore.currency === 'KHR' ? formatKHR(v) : formatCurrency(v))
  const t = (key, params) => i18n.global.t(key, params)

  const rate = Number(r.exchange_rate) > 0 ? Number(r.exchange_rate) : Number(authStore.posSettings?.khr_exchange_rate) || 4100
  const rateDisplay = new Intl.NumberFormat('en-US').format(rate)
  function otherCurrency(amountBase) {
    if (authStore.currency === 'KHR') return formatCurrency(Number(amountBase || 0) / rate)
    const rounding = Number(authStore.posSettings?.khr_rounding) || 100
    const raw = Number(amountBase || 0) * rate
    return formatKHR(Math.round(raw / rounding) * rounding)
  }

  let body = '<div class="center">'
  if (r.branch_name) body += `<div class="name">${escapeHtml(r.branch_name)}</div>`
  if (r.branch_address) body += `<div class="muted">${escapeHtml(r.branch_address)}</div>`
  if (r.branch_phone) body += `<div class="muted">${escapeHtml(r.branch_phone)}</div>`
  body += '</div>'

  if (mode === 'bill') {
    body += '<div class="center"><div class="title">BILL / វិក្កយបត្រ</div><div class="subtitle">NOT PAID / មិនទាន់បង់</div></div>'
  } else if (mode === 'receipt') {
    body += '<div class="center"><div class="title">RECEIPT / បង្កាន់ដៃ</div><div class="subtitle">PAID / បានបង់</div></div>'
  }

  body += '<hr>'
  body += rowHtml(t('pos.receipt.order_number'), r.order_number ?? '-')
  if (r.queue_number_display) body += rowHtml(t('pos.receipt.queue_number'), r.queue_number_display)
  if (r.table_number) body += rowHtml(t('pos.receipt.table'), r.table_number)
  if (r.customer_name) body += rowHtml(t('pos.receipt.customer'), r.customer_name)
  if (r.cashier) body += rowHtml(t('pos.receipt.cashier'), r.cashier)
  body += rowHtml(t('pos.receipt.date'), r.date)
  body += '<hr>'

  for (const item of r.items ?? []) {
    body += '<div class="item">'
    body += rowHtml(`${item.qty} × ${item.name}`, money(item.total_price))
    if (item.note) body += `<div class="item-note muted">${escapeHtml(item.note)}</div>`
    for (const c of item.customizations ?? []) {
      body += `<div class="item-note muted">${escapeHtml(c.label ? `${c.label}: ${c.value}` : c.value)}</div>`
    }
    body += '</div>'
  }

  body += '<hr>'
  body += rowHtml(t('pos.receipt.subtotal'), money(r.subtotal))
  if (r.discount) body += rowHtml(t('pos.receipt.discount'), `-${money(r.discount)}`)
  if (r.tax) body += rowHtml(t('pos.receipt.tax'), money(r.tax))
  if (r.service_charge) body += rowHtml(t('pos.receipt.service_charge'), money(r.service_charge))
  body += rowHtml(t('pos.receipt.total'), money(r.total), 'total')
  body += rowHtml('', `${otherCurrency(r.total)} · Rate: 1 USD = ${rateDisplay} ៛`, 'muted')

  if (r.payment_method === 'pay_later') {
    body += `<hr><div class="muted">${escapeHtml(t('pos.receipt.unpaid'))}</div>`
  } else if (r.payment_method === 'cash') {
    body += '<hr>'
    body += rowHtml('Cash / សាច់ប្រាក់', money(r.total))
    body += rowHtml(t('pos.receipt.cash_tendered'), money(r.cash_tendered))
    body += rowHtml(t('pos.receipt.change_given'), money(r.change_given))
  } else if (r.payment_method === 'qr_code' || r.payment_method === 'qr') {
    body += '<hr>'
    body += rowHtml('Paid by QR / បង់តាម QR', money(r.total))
    if (r.reference) body += rowHtml('Ref / លេខយោង', r.reference)
  } else if (r.payment_method === 'card') {
    body += '<hr>'
    body += rowHtml('Card / កាត', money(r.total))
    if (r.reference) body += rowHtml('Approval / លេខអនុម័ត', r.reference)
  } else if (r.payment_method) {
    body += '<hr>'
    body += rowHtml(t('pos.receipt.payment_method'), String(r.payment_method).toUpperCase())
  }

  return `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hanuman:wght@100..900&family=Noto+Sans+Khmer:wght@400;500;700&display=swap">
<style>${PRINT_IFRAME_STYLE}</style>
</head><body>${body}</body></html>`
}

// Builds the receipt HTML, injects it into a hidden iframe, waits for
// fonts/images, prints, then removes the iframe. `mode` is 'bill' or
// 'receipt' — same meaning as PosReceiptContent.vue's prop.
export async function printReceiptBrowser(receipt, mode) {
  if (!receipt) return false

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  document.body.appendChild(iframe)

  const doc = iframe.contentDocument
  doc.open()
  doc.write(buildReceiptHtml(receipt, mode))
  doc.close()

  await new Promise(resolve => {
    if (doc.readyState === 'complete') resolve()
    else iframe.addEventListener('load', resolve, { once: true })
  })

  try {
    if (doc.fonts?.ready) await doc.fonts.ready
  } catch (_) {
    // Font-loading failure shouldn't block printing — worst case the
    // fallback Arial/sans-serif in PRINT_IFRAME_STYLE renders instead.
  }

  const images = [...doc.images]
  if (images.length) {
    await Promise.all(images.map(img => (img.complete
      ? Promise.resolve()
      : new Promise(resolve => {
        img.addEventListener('load', resolve, { once: true })
        img.addEventListener('error', resolve, { once: true })
      }))))
  }

  iframe.contentWindow.focus()
  iframe.contentWindow.print()

  // Removed on a delay rather than immediately — some browsers tear down
  // an iframe's print job if it's detached from the document too soon
  // after calling print().
  setTimeout(() => iframe.remove(), 1000)
  return true
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
  // See buildReceiptPng() for why the whole receipt goes through as one
  // image instead of QZ's generic raw/base64 byte stream (which the
  // Windows print spooler mangles, not just the Khmer portions of it).
  async function printQz(receipt, printerName) {
    const qz = await getQz()
    if (!qz) throw new Error('QZ Tray not loaded. Make sure qz-tray is installed.')
    if (!qz.websocket.isActive()) await qz.websocket.connect()
    const printer = await qz.printers.find(printerName)
    const config = qz.configs.create(printer)
    const png = buildReceiptPng(receipt, money)
    await qz.print(config, [
      { type: 'raw', format: 'command', data: '\x1B\x40' }, // ESC @ — reset
      {
        type: 'raw',
        format: 'image',
        flavor: 'base64',
        data: png,
        options: { language: 'ESCPOS', dotDensity: 'double' }
      },
      { type: 'raw', format: 'command', data: '\x1B\x64\x05\x1D\x56\x00' } // feed 5 + full cut
    ])
    return true
  }

  // ── Diagnostic: isolate raw-command text vs. image rendering through QZ ──
  // Prints: reset, plain ASCII "Hello" as raw command text, one Khmer line
  // as an image, cut. If "Hello" still comes out garbled, the problem is in
  // QZ/printer/driver configuration, not in how this app builds the job —
  // useful to know before assuming buildReceiptPng() needs more work.
  async function testPrintQz(printerName) {
    const qz = await getQz()
    if (!qz) throw new Error('QZ Tray not loaded. Make sure qz-tray is installed.')
    if (!qz.websocket.isActive()) await qz.websocket.connect()
    const printer = await qz.printers.find(printerName)
    const config = qz.configs.create(printer)
    const khmerPng = buildTestKhmerPng('សួស្ដី! តេស្តបោះពុម្ព')
    await qz.print(config, [
      { type: 'raw', format: 'command', data: '\x1B\x40' },
      { type: 'raw', format: 'command', data: 'Hello\n\n' },
      {
        type: 'raw',
        format: 'image',
        flavor: 'base64',
        data: khmerPng,
        options: { language: 'ESCPOS', dotDensity: 'double' }
      },
      { type: 'raw', format: 'command', data: '\x1B\x64\x05\x1D\x56\x00' }
    ])
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
    disconnectUsb,
    testPrintQz
  }
}
