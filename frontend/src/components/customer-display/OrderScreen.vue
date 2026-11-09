<template>
  <div class="cd-order">
    <div class="cd-order__left">
      <div class="cd-order__header">
        <div class="cd-order__title-group">
          <img v-if="logoUrl" :src="logoUrl" class="cd-order__logo" alt="" />
          <div class="cd-order__title">
            Your order <span class="cd-order__title-km">&middot; ការកម្មង់របស់អ្នក</span>
          </div>
        </div>
        <div v-if="tableName" class="cd-order__table">{{ tableName }}</div>
      </div>

      <div class="cd-order__list">
        <div v-if="overflowCount > 0" class="cd-order__more">
          +{{ overflowCount }} more {{ overflowCount === 1 ? 'item' : 'items' }}
        </div>
        <div
          v-for="row in visibleItems"
          :key="row._key"
          :ref="el => setRowEl(row._key, el)"
          class="cd-order__item"
          :class="{ 'cd-order__item--new': row._key === highlightKey }"
        >
          <img v-if="row.imageUrl" :src="row.imageUrl" class="cd-order__item-image" alt="" />
          <div v-else class="cd-order__item-image cd-order__item-image--placeholder" />
          <div class="cd-order__item-info">
            <div class="cd-order__item-name">{{ row.qty }} &times; {{ row.name }}</div>
            <div v-if="row.options" class="cd-order__item-options">{{ row.options }}</div>
          </div>
          <div class="cd-order__item-total">{{ formatMoney(row.lineTotal) }}</div>
        </div>
      </div>
    </div>

    <div class="cd-order__right">
      <div class="cd-order__summary-row">
        <span>Items</span><span>{{ itemCount }}</span>
      </div>
      <div class="cd-order__summary-row">
        <span>Subtotal</span><span>{{ formatMoney(subtotal) }}</span>
      </div>
      <div v-if="discount > 0" class="cd-order__summary-row">
        <span>Discount</span><span>-{{ formatMoney(discount) }}</span>
      </div>

      <div class="cd-order__divider" />

      <div class="cd-order__total-label">
        Total <span class="cd-order__total-label-km">&middot; សរុប</span>
      </div>
      <div class="cd-order__total-usd">${{ totalUsd.toFixed(2) }}</div>
      <div class="cd-order__total-khr">{{ Math.round(totalKhr).toLocaleString() }} &#6107;</div>

      <div class="cd-order__footer">Please check your order with the cashier.</div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { formatDisplayMoney } from '@/composables/useCustomerDisplay'

const props = defineProps({
  logoUrl: { type: String, default: null },
  tableName: { type: String, default: null },
  items: { type: Array, default: () => [] },
  itemCount: { type: Number, default: 0 },
  subtotal: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  totalUsd: { type: Number, default: 0 },
  totalKhr: { type: Number, default: 0 },
  currency: { type: String, default: 'USD' }
})

const MAX_VISIBLE = 6

function formatMoney(amount) {
  return formatDisplayMoney(amount, props.currency)
}

// Items carry no stable id over the wire (see buildCustomerDisplayState's
// docblock) — a synthetic key from name+options+total is unique enough to
// diff "what's new" between two pushes for a cart display.
function keyOf(item) {
  return `${item.name}|${item.options ?? ''}|${item.lineTotal}`
}

const keyedItems = computed(() => props.items.map(i => ({ ...i, _key: keyOf(i) })))
const overflowCount = computed(() => Math.max(0, keyedItems.value.length - MAX_VISIBLE))
const visibleItems = computed(() => keyedItems.value.slice(-MAX_VISIBLE))

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
</script>

<style scoped>
.cd-order {
  display: flex;
  width: 100%;
  height: 100%;
  background: #f7f5f0;
}

.cd-order__left {
  flex: 0 0 60%;
  display: flex;
  flex-direction: column;
  padding: clamp(20px, 3vw, 40px);
  box-sizing: border-box;
  min-width: 0;
}

.cd-order__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: 0 0 auto;
  margin-bottom: clamp(12px, 2vw, 20px);
}

.cd-order__title-group {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.cd-order__logo {
  max-height: clamp(28px, 3vw, 40px);
  object-fit: contain;
  flex-shrink: 0;
}

.cd-order__title {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: clamp(18px, 2vw, 26px);
  color: #1a1a1a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cd-order__title-km {
  font-family: 'Noto Sans Khmer', sans-serif;
  font-weight: 500;
  font-size: 0.75em;
  color: #666;
}

.cd-order__table {
  font-weight: 700;
  font-size: clamp(14px, 1.4vw, 18px);
  color: var(--cd-teal);
  background: rgba(15, 110, 117, 0.1);
  padding: 6px 14px;
  border-radius: 999px;
  flex-shrink: 0;
}

.cd-order__list {
  flex: 1 1 auto;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-right: 4px;
}

.cd-order__more {
  text-align: center;
  font-size: clamp(13px, 1.2vw, 15px);
  color: #888;
  font-weight: 600;
  padding: 6px 0 2px;
}

.cd-order__item {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #fff;
  border-radius: 18px;
  padding: clamp(8px, 1vw, 14px);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 2px solid transparent;
  transition: border-color 0.2s ease;
}

.cd-order__item--new {
  border-color: var(--cd-teal);
}

.cd-order__item-image {
  width: clamp(48px, 5vw, 64px);
  height: clamp(48px, 5vw, 64px);
  border-radius: 12px;
  object-fit: cover;
  flex-shrink: 0;
  background: #f0f0f0;
}

.cd-order__item-image--placeholder {
  background: #eee;
}

.cd-order__item-info {
  flex: 1 1 auto;
  min-width: 0;
}

.cd-order__item-name {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: clamp(16px, 1.8vw, 24px);
  color: #1a1a1a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cd-order__item-options {
  font-size: clamp(12px, 1.1vw, 17px);
  color: #888;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cd-order__item-total {
  font-family: 'Inter', sans-serif;
  font-weight: 700;
  font-size: clamp(16px, 1.6vw, 26px);
  color: #1a1a1a;
  flex-shrink: 0;
}

.cd-order__right {
  flex: 0 0 40%;
  background: var(--cd-teal);
  color: #fff;
  display: flex;
  flex-direction: column;
  padding: clamp(24px, 3vw, 48px);
  box-sizing: border-box;
}

.cd-order__summary-row {
  display: flex;
  justify-content: space-between;
  font-size: clamp(14px, 1.3vw, 19px);
  opacity: 0.9;
  padding: 6px 0;
}

.cd-order__divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.25);
  margin: clamp(12px, 1.5vw, 20px) 0;
}

.cd-order__total-label {
  font-size: clamp(14px, 1.3vw, 18px);
  opacity: 0.85;
  font-weight: 600;
}

.cd-order__total-label-km {
  font-family: 'Noto Sans Khmer', sans-serif;
}

.cd-order__total-usd {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: clamp(44px, 6vw, 88px);
  line-height: 1.1;
  margin-top: 4px;
}

.cd-order__total-khr {
  font-size: clamp(18px, 2vw, 34px);
  opacity: 0.9;
  margin-top: 4px;
}

.cd-order__footer {
  margin-top: auto;
  font-size: clamp(12px, 1.1vw, 16px);
  opacity: 0.8;
  padding-top: clamp(16px, 2vw, 24px);
}
</style>
