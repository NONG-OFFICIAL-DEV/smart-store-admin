<template>
  <div class="cd-thanks">
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
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  orderNumber: { type: [String, Number], default: null },
  payment: { type: Object, default: () => ({}) }
})

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
.cd-thanks {
  width: 100%;
  height: 100%;
  background: var(--cd-teal);
  color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: clamp(24px, 4vw, 64px);
  box-sizing: border-box;
}

.cd-thanks__check {
  width: clamp(80px, 10vw, 140px);
  height: clamp(80px, 10vw, 140px);
  border-radius: 50%;
  background: #fff;
  color: var(--cd-teal);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: clamp(20px, 3vw, 36px);
}

.cd-thanks__check svg {
  width: 55%;
  height: 55%;
}

.cd-thanks__title {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: clamp(32px, 4.5vw, 56px);
  line-height: 1.15;
}

.cd-thanks__title--km {
  font-family: 'Noto Sans Khmer', sans-serif;
  font-weight: 700;
  font-size: clamp(22px, 3vw, 36px);
  margin-top: 2px;
}

.cd-thanks__pill {
  margin-top: clamp(24px, 3.5vw, 40px);
  background: rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  padding: clamp(10px, 1.4vw, 16px) clamp(24px, 3vw, 36px);
  font-weight: 700;
  font-size: clamp(16px, 2vw, 26px);
}

.cd-thanks__footer {
  margin-top: clamp(20px, 3vw, 32px);
  font-size: clamp(14px, 1.5vw, 19px);
  opacity: 0.85;
}
</style>
