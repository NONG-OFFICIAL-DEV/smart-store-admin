<template>
  <div v-if="receipt">
    <div class="text-center mb-3">
      <div class="text-body-1 font-weight-bold">{{ receipt.branch_name }}</div>
      <div v-if="receipt.branch_address" class="text-caption text-medium-emphasis">
        {{ receipt.branch_address }}
      </div>
      <div v-if="receipt.branch_phone" class="text-caption text-medium-emphasis">
        {{ receipt.branch_phone }}
      </div>
    </div>

    <!-- Bill (unpaid) vs. Receipt (paid) — only set by the Tables flow
         (BillDrawer.vue passes mode="bill", PaymentDialog.vue passes
         mode="receipt"); other consumers (POS checkout, historical order
         lookup) leave mode unset and get no banner, unchanged. -->
    <div v-if="mode === 'bill'" class="text-center mb-3">
      <div class="text-body-1 font-weight-bold">BILL / វិក្កយបត្រ</div>
      <div class="text-caption font-weight-bold">NOT PAID / មិនទាន់បង់</div>
    </div>
    <div v-else-if="mode === 'receipt'" class="text-center mb-3">
      <div class="text-body-1 font-weight-bold">RECEIPT / បង្កាន់ដៃ</div>
      <div class="text-caption font-weight-bold">PAID / បានបង់</div>
    </div>

    <v-divider class="mb-2" />

    <div class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.order_number') }}</span>
      <span class="font-weight-medium">{{ receipt.order_number }}</span>
    </div>
    <div v-if="receipt.queue_number_display" class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.queue_number') }}</span>
      <span class="font-weight-medium">{{ receipt.queue_number_display }}</span>
    </div>
    <div v-if="receipt.table_number" class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.table') }}</span>
      <span class="font-weight-medium">{{ receipt.table_number }}</span>
    </div>
    <div v-if="receipt.customer_name" class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.customer') }}</span>
      <span class="font-weight-medium">{{ receipt.customer_name }}</span>
    </div>
    <div v-if="receipt.cashier" class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.cashier') }}</span>
      <span class="font-weight-medium">{{ receipt.cashier }}</span>
    </div>
    <div class="d-flex justify-space-between text-caption mb-2">
      <span>{{ $t('pos.receipt.date') }}</span>
      <span class="font-weight-medium">{{ receipt.date }}</span>
    </div>

    <v-divider class="mb-2" />

    <div v-for="(item, idx) in receipt.items" :key="idx" class="mb-2">
      <div class="d-flex justify-space-between text-body-2">
        <span>{{ item.qty }} × {{ item.name }}</span>
        <span class="font-weight-medium">{{ formatBase(item.total_price) }}</span>
      </div>
      <div v-if="item.note" class="text-caption text-medium-emphasis">{{ item.note }}</div>
      <div
        v-for="(c, cIdx) in item.customizations"
        :key="cIdx"
        class="text-caption text-medium-emphasis"
      >
        {{ c.label ? `${c.label}: ${c.value}` : c.value }}
      </div>
    </div>

    <v-divider class="mb-2" />

    <div class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.subtotal') }}</span>
      <span>{{ formatBase(receipt.subtotal) }}</span>
    </div>
    <div v-if="receipt.discount" class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.discount') }}</span>
      <span>-{{ formatBase(receipt.discount) }}</span>
    </div>
    <div v-if="receipt.tax" class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.tax') }}</span>
      <span>{{ formatBase(receipt.tax) }}</span>
    </div>
    <div v-if="receipt.service_charge" class="d-flex justify-space-between text-caption mb-1">
      <span>{{ $t('pos.receipt.service_charge') }}</span>
      <span>{{ formatBase(receipt.service_charge) }}</span>
    </div>
    <div class="d-flex justify-space-between text-body-1 font-weight-bold mt-2">
      <span>{{ $t('pos.receipt.total') }}</span>
      <span>{{ formatBase(receipt.total) }}</span>
    </div>
    <!-- Dual-currency line — print-only (the on-screen Payment
         dialog/Bill drawer stay single-currency; see useCurrency_v2.js).
         Bill mode has no saved rate yet, so it uses today's live tenant
         setting; receipt mode uses the rate actually stamped on the
         payment, so a reprint stays correct even after the rate changes. -->
    <div class="d-flex justify-space-between text-caption text-medium-emphasis">
      <span></span>
      <span>{{ formatOtherCurrency(receipt.total) }} · Rate: 1 USD = {{ rateDisplay }} ៛</span>
    </div>

    <template v-if="receipt.payment_method === 'pay_later'">
      <v-divider class="my-2" />
      <v-alert type="warning" variant="tonal" density="compact" rounded="lg">
        {{ $t('pos.receipt.unpaid') }}
      </v-alert>
    </template>
    <template v-else-if="receipt.payment_method === 'cash'">
      <v-divider class="my-2" />
      <div class="d-flex justify-space-between text-caption mb-1">
        <span>Cash / សាច់ប្រាក់</span>
        <span>{{ formatBase(receipt.total) }}</span>
      </div>
      <div class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.cash_tendered') }}</span>
        <span>{{ formatBase(receipt.cash_tendered) }}</span>
      </div>
      <div class="d-flex justify-space-between text-caption">
        <span>{{ $t('pos.receipt.change_given') }}</span>
        <span>{{ formatBase(receipt.change_given) }}</span>
      </div>
    </template>
    <template v-else-if="receipt.payment_method === 'qr_code' || receipt.payment_method === 'qr'">
      <v-divider class="my-2" />
      <div class="d-flex justify-space-between text-caption mb-1">
        <span>Paid by QR / បង់តាម QR</span>
        <span>{{ formatBase(receipt.total) }}</span>
      </div>
      <div v-if="receipt.reference" class="d-flex justify-space-between text-caption">
        <span>Ref / លេខយោង</span>
        <span>{{ receipt.reference }}</span>
      </div>
    </template>
    <template v-else-if="receipt.payment_method === 'card'">
      <v-divider class="my-2" />
      <div class="d-flex justify-space-between text-caption mb-1">
        <span>Card / កាត</span>
        <span>{{ formatBase(receipt.total) }}</span>
      </div>
      <div v-if="receipt.reference" class="d-flex justify-space-between text-caption">
        <span>Approval / លេខអនុម័ត</span>
        <span>{{ receipt.reference }}</span>
      </div>
    </template>
    <template v-else-if="receipt.payment_method">
      <v-divider class="my-2" />
      <div class="d-flex justify-space-between text-caption">
        <span>{{ $t('pos.receipt.payment_method') }}</span>
        <span class="text-uppercase">{{ receipt.payment_method }}</span>
      </div>
    </template>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { formatCurrency, formatKHR } from '@nong-official-dev/core'
  import { useAuthStore } from '@/stores/authStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const props = defineProps({
    receipt: { type: Object, default: null },
    // 'bill' | 'receipt' | null — drives the BILL/NOT PAID vs RECEIPT/PAID
    // banner. Left unset by consumers outside the Tables flow.
    mode: { type: String, default: null }
  })

  const authStore = useAuthStore()
  const { formatBase, currency } = useCurrency()

  // Print-only currency conversion — deliberately NOT part of
  // useCurrency_v2.js (which was simplified to base-currency-only for the
  // on-screen dialogs after a display bug). A paper receipt showing both
  // USD and KHR is a standard Cambodia POS convention regardless of which
  // one is the tenant's bookkeeping currency, so it's kept local to this
  // print-only component instead of reintroduced app-wide.
  const effectiveRate = computed(() => {
    const rate = Number(props.receipt?.exchange_rate)
    return rate > 0 ? rate : Number(authStore.posSettings?.khr_exchange_rate) || 4100
  })

  const rateDisplay = computed(() => new Intl.NumberFormat('en-US').format(effectiveRate.value))

  function formatOtherCurrency(amountBase) {
    const rate = effectiveRate.value
    if (currency.value === 'KHR') {
      return formatCurrency(Number(amountBase || 0) / rate)
    }
    const rounding = Number(authStore.posSettings?.khr_rounding) || 100
    const raw = Number(amountBase || 0) * rate
    return formatKHR(Math.round(raw / rounding) * rounding)
  }
</script>
