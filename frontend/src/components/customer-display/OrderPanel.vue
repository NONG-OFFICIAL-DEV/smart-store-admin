<template>
  <div class="cd-panel">
    <header class="cd-panel__header">
      <h2 class="cd-panel__branch">{{ branchName || '&nbsp;' }}</h2>
      <div v-if="tableName" class="cd-panel__table">{{ tableName }}</div>
    </header>

    <transition name="cd-panel-fade" mode="out-in">
      <!-- QR view ---------------------------------------------------- -->
      <div v-if="state === 'pay_qr'" key="qr" class="cd-panel__content cd-qr">
        <div class="cd-qr__label">
          Amount to pay <span class="cd-label-km">&middot; ចំនួនត្រូវបង់</span>
        </div>
        <div class="cd-qr__amount">${{ totalUsd.toFixed(2) }}</div>
        <div class="cd-qr__amount-khr">{{ Math.round(totalKhr).toLocaleString() }} &#6107;</div>

        <div class="cd-qr__box">
          <img v-if="qrSrc" :src="qrSrc" class="cd-qr__image" alt="QR code" />
          <div v-else class="cd-qr__placeholder">QR not configured</div>
        </div>

        <ol class="cd-qr__steps">
          <li>Open your bank app</li>
          <li>Scan the KHQR code</li>
          <li>Show the confirmation to the cashier</li>
        </ol>
      </div>

      <!-- Thanks view ------------------------------------------------ -->
      <div v-else-if="state === 'thanks'" key="thanks" class="cd-panel__content cd-thanks">
        <div class="cd-thanks__check">
          <svg viewBox="0 0 24 24" fill="none">
            <path
              d="M5 13l4 4L19 7"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
        <div class="cd-thanks__title">Thank you!</div>
        <div class="cd-thanks__title cd-thanks__title--km">អរគុណច្រើន</div>
        <div class="cd-thanks__pill">{{ pillText }}</div>
        <div v-if="orderNumber" class="cd-thanks__footer">
          Your order #{{ orderNumber }} is being prepared.
        </div>
      </div>

      <!-- Default order view ------------------------------------------ -->
      <div v-else key="order" class="cd-panel__content cd-items">
        <div v-if="!items.length" class="cd-items__empty">
          <div class="cd-items__empty-title">Welcome</div>
          <div class="cd-items__empty-title cd-items__empty-title--km">សូមស្វាគមន៍</div>
          <div class="cd-items__empty-hint">Your order will appear here.</div>
        </div>

        <template v-else>
          <div class="cd-table-head">
            <span class="cd-table-head__product">Product</span>
            <span class="cd-table-head__price">Price</span>
            <span class="cd-table-head__qty">Qty</span>
            <span class="cd-table-head__amount">Amount</span>
          </div>

          <div class="cd-table-body">
            <div
              v-for="row in keyedItems"
              :key="row._key"
              :ref="el => setRowEl(row._key, el)"
              class="cd-row"
              :class="{ 'cd-row--new': row._key === highlightKey }"
            >
              <div class="cd-row__product">
                <div class="cd-row__name">{{ row.name }}</div>
                <div v-if="row.options" class="cd-row__options">{{ row.options }}</div>
              </div>
              <div class="cd-row__price">{{ formatMoney(row.unitPrice) }}</div>
              <div class="cd-row__qty">{{ row.qty }}</div>
              <div class="cd-row__amount">{{ formatMoney(row.lineTotal) }}</div>
            </div>
          </div>
        </template>

        <footer class="cd-summary">
          <div class="cd-summary__col cd-summary__col--payment">
            <div class="cd-summary__row">
              <span>Payment</span><span>{{ paymentStatusLabel }}</span>
            </div>
            <div class="cd-summary__row">
              <span>Received</span><span class="cd-mono">{{ formatMoney(receivedAmount) }}</span>
            </div>
            <div class="cd-summary__row cd-summary__row--remain">
              <span>Remain</span><span class="cd-mono">{{ formatMoney(remainAmount) }}</span>
            </div>
          </div>
          <div class="cd-summary__col cd-summary__col--totals">
            <div class="cd-summary__row">
              <span>Subtotal</span><span class="cd-mono">{{ formatMoney(subtotal) }}</span>
            </div>
            <div v-if="discount > 0" class="cd-summary__row cd-summary__row--discount">
              <span>Total Discount</span><span class="cd-mono">-{{ formatMoney(discount) }}</span>
            </div>
            <div class="cd-summary__total">
              <span class="cd-summary__total-label">Total</span>
              <span class="cd-summary__total-amount">${{ totalUsd.toFixed(2) }}</span>
            </div>
            <div class="cd-summary__total-khr">{{ Math.round(totalKhr).toLocaleString() }} &#6107;</div>
          </div>
        </footer>

        <img :src="appLogo" class="cd-app-logo" alt="" />
      </div>
    </transition>

    <footer v-if="wifiText" class="cd-panel__footer">
      <span class="cd-panel__wifi-icon">&#128246;</span>{{ wifiText }}
    </footer>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { formatDisplayMoney } from '@/composables/useCustomerDisplay'
import { useQrCodeImage } from '@/composables/useQrCode'

import { LOGOS } from '@/config/branding'

const appLogo = LOGOS.light

const props = defineProps({
  branchName: { type: String, default: null },
  wifiText: { type: String, default: null },
  state: { type: String, default: 'order' },
  tableName: { type: String, default: null },
  orderNumber: { type: [String, Number], default: null },
  items: { type: Array, default: () => [] },
  subtotal: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  totalUsd: { type: Number, default: 0 },
  totalKhr: { type: Number, default: 0 },
  currency: { type: String, default: 'USD' },
  payment: { type: Object, default: null }
})

function formatMoney(amount) {
  return formatDisplayMoney(amount, props.currency)
}

// ── Item table — items carry no stable id over the wire, a synthetic key
// from name+options+total is unique enough to diff "what's new" between
// two pushes for a cart display (same approach the old OrderScreen used).
function keyOf(item) {
  return `${item.name}|${item.options ?? ''}|${item.lineTotal}`
}

const keyedItems = computed(() =>
  props.items.map(i => ({
    ...i,
    unitPrice: i.qty ? i.lineTotal / i.qty : i.lineTotal,
    _key: keyOf(i)
  }))
)

const highlightKey = ref(null)
const rowEls = {}
let previousKeys = new Set()
let highlightTimer = null

function setRowEl(key, el) {
  if (el) rowEls[key] = el
}

watch(
  keyedItems,
  rows => {
    const currentKeys = new Set(rows.map(r => r._key))
    const freshlyAdded = rows.filter(r => !previousKeys.has(r._key))
    previousKeys = currentKeys

    if (freshlyAdded.length) {
      const newest = freshlyAdded[freshlyAdded.length - 1]
      highlightKey.value = newest._key

      nextTick(() => {
        rowEls[newest._key]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
      })

      clearTimeout(highlightTimer)
      highlightTimer = setTimeout(() => {
        highlightKey.value = null
      }, 3000)
    }
  },
  { immediate: true }
)

// ── Financial summary's "Payment/Received/Remain" sub-column — the
// publisher never sends a `payment` object while just browsing the cart
// (the order doesn't exist yet), so this is always the literal truth —
// nothing has been received yet — never fabricated data.
const paymentStatusLabel = computed(() => 'Pending')
const receivedAmount = computed(() => 0)
const remainAmount = computed(() => props.totalUsd)

// ── QR view ------------------------------------------------------------
const payloadRef = computed(() => props.payment?.qrPayload ?? null)
const { dataUrl } = useQrCodeImage(payloadRef)
const qrSrc = computed(() => dataUrl.value ?? props.payment?.qrImageUrl ?? null)

// ── Thanks view ----------------------------------------------------------
// Backend stores 'qr_code'/'online', never the raw 'qr'/'transfer' the
// cashier picks — see MartPosController/CoffeePOSorderController's
// $paymentMethodMap.
const METHOD_LABELS = { qr_code: 'QR', card: 'Card', online: 'bank transfer', pay_later: 'later' }

const pillText = computed(() => {
  const method = props.payment?.method
  if (method === 'cash') {
    const paid = props.payment?.paidUsd
    const change = props.payment?.changeKhr
    let text = paid != null ? `Paid $${Number(paid).toFixed(2)}` : 'Paid'
    if (change != null) text += ` · Change ${Math.round(change).toLocaleString()} ៛`
    return text
  }
  return `Paid by ${METHOD_LABELS[method] ?? 'cash'}`
})
</script>

<style scoped>
.cd-panel {
  --cd-primary: #4f46e5;
  --cd-success: #10b981;
  --cd-ink: #0f172a;
  --cd-ink-soft: #64748b;
  --cd-border: rgba(226, 232, 240, 0.8);
  --cd-canvas: #f8fafc;

  width: 100%;
  height: 100%;
  background: var(--cd-canvas);
  color: var(--cd-ink);
  font-family: 'Manrope', sans-serif;
  display: flex;
  flex-direction: column;
  padding: clamp(16px, 2.2vw, 32px);
  box-sizing: border-box;
  overflow: hidden;
}

.cd-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: 0 0 auto;
  padding-bottom: clamp(10px, 1.4vw, 18px);
  margin-bottom: clamp(10px, 1.4vw, 18px);
  border-bottom: 1px solid var(--cd-border);
}

.cd-panel__branch {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 700;
  font-size: clamp(18px, 1.9vw, 26px);
  letter-spacing: -0.01em;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cd-panel__table {
  font-weight: 700;
  font-size: clamp(13px, 1.2vw, 16px);
  color: var(--cd-primary);
  background: rgba(79, 70, 229, 0.1);
  padding: 5px 14px;
  border-radius: 999px;
  flex-shrink: 0;
}

.cd-panel__content {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.cd-panel__footer {
  flex: 0 0 auto;
  margin-top: clamp(8px, 1.2vw, 14px);
  font-size: clamp(11px, 1vw, 14px);
  color: var(--cd-ink-soft);
}

.cd-panel__wifi-icon {
  margin-right: 6px;
}

.cd-mono {
  font-family: 'JetBrains Mono', monospace;
  font-variant-numeric: tabular-nums;
}

.cd-label-km {
  font-family: 'Noto Sans Khmer', sans-serif;
  font-weight: 500;
  color: var(--cd-ink-soft);
}

/* ── Order / item table view ─────────────────────────────────────────── */
.cd-items {
  min-height: 0;
}

.cd-items__empty {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--cd-ink-soft);
}

.cd-items__empty-title {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 700;
  font-size: clamp(22px, 2.4vw, 32px);
  color: var(--cd-ink);
}

.cd-items__empty-title--km {
  font-family: 'Noto Sans Khmer', sans-serif;
  font-size: clamp(16px, 1.8vw, 22px);
  color: var(--cd-ink-soft);
  margin-top: 2px;
}

.cd-items__empty-hint {
  margin-top: 10px;
  font-size: clamp(13px, 1.2vw, 16px);
}

.cd-table-head {
  display: grid;
  grid-template-columns: 1fr clamp(60px, 7vw, 90px) clamp(40px, 5vw, 56px) clamp(70px, 8vw, 100px);
  gap: 8px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--cd-border);
  font-size: clamp(10px, 0.9vw, 12px);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--cd-ink-soft);
  flex: 0 0 auto;
}

.cd-table-head__price,
.cd-table-head__amount {
  text-align: right;
}

.cd-table-head__qty {
  text-align: center;
}

.cd-table-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
}

.cd-row {
  display: grid;
  grid-template-columns: 1fr clamp(60px, 7vw, 90px) clamp(40px, 5vw, 56px) clamp(70px, 8vw, 100px);
  gap: 8px;
  padding: clamp(10px, 1.3vw, 16px) 0;
  border-bottom: 1px solid rgba(226, 232, 240, 0.5);
  border-radius: 8px;
  transition: background-color 0.3s ease;
}

.cd-row--new {
  background: rgba(79, 70, 229, 0.06);
}

.cd-row__name {
  font-weight: 700;
  font-size: clamp(13px, 1.3vw, 17px);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cd-row__options {
  margin-top: 2px;
  font-size: clamp(11px, 1vw, 13px);
  color: var(--cd-ink-soft);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cd-row__price,
.cd-row__qty,
.cd-row__amount {
  font-family: 'JetBrains Mono', monospace;
  font-variant-numeric: tabular-nums;
  font-size: clamp(12px, 1.2vw, 15px);
  align-self: start;
}

.cd-row__price {
  text-align: right;
  color: var(--cd-ink-soft);
}

.cd-row__qty {
  text-align: center;
  color: var(--cd-ink-soft);
}

.cd-row__amount {
  text-align: right;
  font-weight: 700;
}

.cd-summary {
  flex: 0 0 auto;
  margin-top: clamp(10px, 1.4vw, 18px);
  padding-top: clamp(10px, 1.4vw, 18px);
  border-top: 2px solid var(--cd-border);
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: clamp(16px, 2vw, 28px);
}

.cd-summary__col--payment {
  padding-right: clamp(12px, 1.6vw, 20px);
  border-right: 1px solid rgba(226, 232, 240, 0.7);
}

.cd-summary__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: clamp(12px, 1.1vw, 15px);
  color: var(--cd-ink-soft);
  padding: 3px 0;
}

.cd-summary__row--remain span:last-child {
  font-weight: 700;
  color: var(--cd-ink);
}

.cd-summary__row--discount span:last-child {
  color: var(--cd-success);
  font-weight: 600;
}

.cd-summary__total {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-top: 6px;
  padding-top: 8px;
  border-top: 1px solid var(--cd-border);
}

.cd-summary__total-label {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 700;
  font-size: clamp(12px, 1.1vw, 15px);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.cd-summary__total-amount {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 700;
  font-size: clamp(22px, 2.6vw, 34px);
  color: var(--cd-success);
}

.cd-summary__total-khr {
  text-align: right;
  font-size: clamp(11px, 1vw, 14px);
  color: var(--cd-ink-soft);
  margin-top: 2px;
}

.cd-app-logo {
  display: block;
  margin: clamp(14px, 2vw, 22px) auto 0;
  max-width: clamp(90px, 11vw, 140px);
  max-height: clamp(28px, 3.5vw, 44px);
  object-fit: contain;
  flex: 0 0 auto;
}

/* ── QR view ─────────────────────────────────────────────────────────── */
.cd-qr {
  align-items: center;
  justify-content: center;
  text-align: center;
}

.cd-qr__label {
  font-size: clamp(14px, 1.3vw, 18px);
  font-weight: 700;
}

.cd-qr__amount {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 700;
  font-size: clamp(36px, 4.5vw, 56px);
  color: var(--cd-success);
  margin-top: 6px;
}

.cd-qr__amount-khr {
  font-size: clamp(14px, 1.3vw, 18px);
  color: var(--cd-ink-soft);
}

.cd-qr__box {
  width: min(240px, 60%);
  aspect-ratio: 1 / 1;
  background: #fff;
  border: 1px solid var(--cd-border);
  border-radius: 8px;
  box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  margin: clamp(16px, 2.2vw, 28px) 0;
}

.cd-qr__image {
  width: 90%;
  height: 90%;
  object-fit: contain;
}

.cd-qr__placeholder {
  color: var(--cd-ink-soft);
  font-size: 13px;
  padding: 16px;
}

.cd-qr__steps {
  text-align: left;
  padding-left: 1.2em;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: clamp(12px, 1.1vw, 15px);
  color: var(--cd-ink-soft);
}

/* ── Thanks view ─────────────────────────────────────────────────────── */
.cd-thanks {
  align-items: center;
  justify-content: center;
  text-align: center;
}

.cd-thanks__check {
  width: clamp(56px, 6vw, 80px);
  height: clamp(56px, 6vw, 80px);
  border-radius: 50%;
  background: var(--cd-success);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: clamp(14px, 2vw, 22px);
}

.cd-thanks__check svg {
  width: 55%;
  height: 55%;
}

.cd-thanks__title {
  font-family: 'Space Grotesk', sans-serif;
  font-weight: 700;
  font-size: clamp(24px, 3vw, 36px);
  line-height: 1.15;
}

.cd-thanks__title--km {
  font-family: 'Noto Sans Khmer', sans-serif;
  font-weight: 600;
  font-size: clamp(16px, 2vw, 22px);
  color: var(--cd-ink-soft);
  margin-top: 2px;
}

.cd-thanks__pill {
  margin-top: clamp(16px, 2.2vw, 26px);
  background: rgba(79, 70, 229, 0.08);
  color: var(--cd-primary);
  border-radius: 999px;
  padding: clamp(8px, 1.1vw, 12px) clamp(20px, 2.4vw, 28px);
  font-weight: 700;
  font-size: clamp(13px, 1.4vw, 18px);
}

.cd-thanks__footer {
  margin-top: clamp(14px, 2vw, 22px);
  font-size: clamp(12px, 1.2vw, 15px);
  color: var(--cd-ink-soft);
}

.cd-panel-fade-enter-active,
.cd-panel-fade-leave-active {
  transition: opacity 0.2s ease;
}

.cd-panel-fade-enter-from,
.cd-panel-fade-leave-to {
  opacity: 0;
}
</style>
