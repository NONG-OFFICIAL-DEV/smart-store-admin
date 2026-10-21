<template>
  <AppDialog
    v-model="model"
    :title="t('tables.payment.title')"
    :max-width="440"
    :persistent="saving"
  >
    <div class="text-center text-caption text-medium-emphasis mb-4">
      {{ t('tables.payment.subtitle', { number: table?.table_number, orders: orders.length }) }}
    </div>

    <!-- ── Total ──────────────────────────────────────────────────────── -->
    <div class="text-center mb-5">
      <div class="text-h4 font-weight-bold">{{ formatBase(total) }}</div>
    </div>

    <!-- ── Method picker ──────────────────────────────────────────────── -->
    <v-btn-toggle
      v-model="method"
      variant="outlined"
      rounded="lg"
      mandatory
      class="w-100 mb-4"
      :divided="false"
    >
      <v-btn
        v-for="opt in paymentOptions"
        :key="opt.value"
        :value="opt.value"
        height="44"
        class="flex-grow-1 text-none"
        :class="method === opt.value ? 'payment-method-btn--selected' : ''"
      >
        <v-icon start :icon="opt.icon" />
        {{ opt.label }}
      </v-btn>
    </v-btn-toggle>

    <!-- ── Cash ───────────────────────────────────────────────────────── -->
    <template v-if="method === 'cash'">
      <v-text-field
        v-model.number="received"
        type="number"
        min="0"
        :step="currency === 'KHR' ? 100 : 0.01"
        :label="t('tables.payment.received_label', { symbol: currencySymbol() })"
        variant="outlined"
        density="compact"
        rounded="lg"
        class="mb-3"
        hide-details
      />

      <div class="d-flex flex-wrap gap-2 mb-4">
        <v-chip
          v-for="btn in quickButtons"
          :key="btn.key"
          size="small"
          variant="tonal"
          rounded="lg"
          class="px-3"
          style="min-height: 32px"
          @click="applyQuickButton(btn)"
        >
          {{ btn.label }}
        </v-chip>
      </div>

      <v-card flat rounded="lg" class="pa-3 mb-2 payment-summary-card">
        <div class="d-flex justify-space-between text-body-2 mb-2">
          <span class="text-medium-emphasis">{{ t('tables.payment.received') }}</span>
          <span class="font-weight-medium">{{ formatBase(received ?? 0) }}</span>
        </div>
        <v-divider class="mb-2" />
        <div class="d-flex justify-space-between align-center">
          <span
            class="text-body-2 font-weight-bold"
            :class="diff >= 0 ? 'text-success' : 'text-warning'"
          >
            {{ diff >= 0 ? t('pos.change') : t('tables.payment.remaining') }}
          </span>
          <div
            class="text-h6 font-weight-bold"
            :class="diff >= 0 ? 'text-success' : 'text-warning'"
          >
            {{ formatBase(Math.abs(diff)) }}
          </div>
        </div>
      </v-card>
    </template>

    <!-- ── Card ───────────────────────────────────────────────────────── -->
    <template v-else-if="method === 'card'">
      <div class="text-center text-body-1 font-weight-medium mb-4">
        {{ t('tables.payment.charge_card', { amount: formatBase(total) }) }}
      </div>
      <v-text-field
        v-model="reference"
        :label="t('tables.payment.approval_code_optional')"
        variant="outlined"
        density="compact"
        rounded="lg"
        hide-details
      />
    </template>

    <template #actions>
      <div class="w-100">
        <v-btn
          color="warning"
          variant="flat"
          rounded="lg"
          height="44"
          block
          :loading="saving"
          :disabled="saving || !canConfirm"
          @click="confirmPayment"
        >
          {{ t('tables.payment.confirm') }}
        </v-btn>
        <div class="text-center text-caption text-medium-emphasis mt-2">
          {{ t('tables.payment.confirm_hint', { number: table?.table_number }) }}
        </div>
      </div>
    </template>
  </AppDialog>

  <!-- Print-only copy, teleported to body — same reasoning as
       BillDrawer.vue/OrderDetailDialog.vue: an absolutely-positioned
       descendant gets clipped by the dialog's own overflow boundary. -->
  <Teleport to="body">
    <div v-if="modelValue && paidReceiptData" class="payment-print-receipt">
      <PosReceiptContent :receipt="paidReceiptData" mode="receipt" />
    </div>
  </Teleport>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog, useAppUtils } from '@nong-official-dev/core'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useTableStore } from '@/stores/tableStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'
  import { useReceipt } from '@/utils/printReceipt'
  import { paySessionApi } from '@/api/tableService'
  import PosReceiptContent from '@/components/pos/PosReceiptContent.vue'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const tableStore = useTableStore()
  const { formatBase, currency, currencySymbol } = useCurrency()
  const { formatDateTime } = useDate()
  const { print: printThermal } = useReceipt()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    // Already-fetched orders for the table's open session — same array
    // BillDrawer renders, passed straight through from TableManagement.
    orders: { type: Array, default: () => [] },
    table: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'paid'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const close = () => emit('update:modelValue', false)

  // This dialog's own tenant-configured list (Settings > POS > Table Payment
  // Methods), separate from the POS checkout list — a tenant may want a
  // different set of methods available when settling a table's bill than
  // when ringing up a walk-in sale. No Pay Later here: collecting money on
  // an already-placed order, not deferring it again — and the session-pay
  // endpoint doesn't accept it anyway. QR is a placeholder (no real
  // KHQR/bank integration yet) but is offered since staff still need to
  // select it and confirm the amount manually.
  const ALL_PAYMENT_METHODS = [
    { value: 'cash', icon: 'mdi-cash', label: t('pos.checkout.methods.cash') },
    { value: 'card', icon: 'mdi-credit-card-outline', label: t('pos.checkout.methods.card') },
    { value: 'qr', icon: 'mdi-qrcode', label: t('pos.checkout.methods.qr') }
  ]
  const paymentOptions = computed(() => {
    const enabled = authStore.posSettings?.table_payment_methods ?? ['cash', 'card']
    return ALL_PAYMENT_METHODS.filter(opt => enabled.includes(opt.value))
  })

  const method = ref('cash')
  const received = ref(null)
  const reference = ref('')
  const saving = ref(false)
  const paidReceiptData = ref(null)

  watch(
    () => props.modelValue,
    isOpen => {
      if (!isOpen) return
      const defaultMethod = authStore.posSettings?.default_table_payment_method
      const options = paymentOptions.value
      method.value = options.some(o => o.value === defaultMethod) ? defaultMethod : (options[0]?.value ?? 'cash')
      received.value = null
      reference.value = ''
      paidReceiptData.value = null
    }
  )

  const totals = computed(() => {
    const sum = key => props.orders.reduce((sum2, o) => sum2 + Number(o[key] ?? 0), 0)
    return {
      subtotal: sum('subtotal'),
      discount: sum('discount_amount'),
      tax: sum('tax_amount'),
      serviceCharge: sum('service_charge_amount'),
      total: sum('total_amount')
    }
  })
  const total = computed(() => totals.value.total)

  // Quick buttons add to what's already keyed in — staff tap one chip per
  // bill/note the customer actually hands over, same as a real cash drawer.
  // Denominations match the tenant's own base currency (never assume USD).
  const quickButtons = computed(() => {
    const denominations = currency.value === 'KHR' ? [5000, 10000, 20000, 50000] : [5, 10, 20, 50]
    return [
      { key: 'exact', label: t('tables.payment.quick_exact'), type: 'exact' },
      ...denominations.map(amount => ({ key: `d${amount}`, label: formatBase(amount), type: 'add', amount })),
      { key: 'clear', label: t('tables.payment.quick_clear'), type: 'clear' }
    ]
  })

  function applyQuickButton(btn) {
    if (btn.type === 'exact') {
      received.value = total.value
    } else if (btn.type === 'add') {
      received.value = Number(received.value ?? 0) + btn.amount
    } else if (btn.type === 'clear') {
      received.value = null
    }
  }

  const diff = computed(() => Number(received.value ?? 0) - total.value)

  const canConfirm = computed(() => {
    if (method.value === 'cash') return Number(received.value ?? 0) >= total.value
    return true
  })

  const activeBranch = computed(
    () => branchStore.branches.find(b => b.id === authStore.activeBranchId) ?? null
  )

  // The random suffix of order_number (e.g. "ORD-FOO-261020-AB3D" → "AB3D")
  // — same short-code convention BillDrawer.vue uses, not the table name.
  const shortOrderNumber = o => (o.order_number ?? '').split('-').pop() || o.id?.slice(0, 4)

  function buildSessionReceiptData(receipt) {
    return {
      order_number: '#' + shortOrderNumber(props.orders[0] ?? {}),
      order_type: props.orders[0]?.order_type,
      table_number: props.table?.table_number,
      branch_name: activeBranch.value?.name,
      branch_address: activeBranch.value?.address_line1,
      branch_phone: activeBranch.value?.phone,
      cashier: props.orders[0]?.cashier,
      date: formatDateTime(receipt.payment?.paid_at ?? new Date()),
      items: receipt.items.map(i => ({
        name: i.product_name,
        qty: i.quantity,
        unit_price: i.unit_price,
        total_price: i.total_price
      })),
      subtotal: receipt.subtotal,
      discount: receipt.discount,
      tax: receipt.tax,
      service_charge: receipt.service_charge,
      total: receipt.total,
      payment_method: receipt.payment?.method,
      cash_tendered: receipt.payment?.cash_tendered,
      change_given: receipt.payment?.change_given,
      exchange_rate: receipt.payment?.exchange_rate,
      reference: receipt.payment?.reference
    }
  }

  async function printReceiptNow(data) {
    const printerName = activeBranch.value?.printer_name
    if (printerName && data) {
      await printThermal(data, printerName)
    } else {
      window.print()
    }
  }

  // The backend already frees the table as part of paySession() — this is
  // a defensive resync kept for parity with the previous pay flow, harmless
  // if the table is already marked available.
  async function freeTable() {
    const tableId = props.table?.id
    if (!tableId) return
    try {
      await tableStore.updateTableStatus(tableId, 'available')
    } catch {
      // Non-fatal — the backend's own resync already covers this.
    }
  }

  async function confirmPayment() {
    if (!props.orders.length || saving.value) return
    const sessionId = props.orders[0]?.table_session_id
    if (!sessionId) {
      notif(t('orders_admin.settle.failed'), { type: 'error' })
      return
    }

    saving.value = true
    try {
      const res = await paySessionApi(sessionId, {
        payment_method: method.value,
        cash_tendered: method.value === 'cash' ? Number(received.value ?? 0) : undefined,
        reference: method.value !== 'cash' ? (reference.value || undefined) : undefined
      })
      const receipt = res.data.data
      paidReceiptData.value = buildSessionReceiptData(receipt)
      await printReceiptNow(paidReceiptData.value)

      notif(t('tables.messages.table_paid', { number: props.table?.table_number }), { type: 'success' })
      await freeTable()
      emit('paid')
      close()
    } catch (err) {
      // Keep the dialog open and change nothing on failure — staff can fix
      // the input (e.g. add more cash) and retry without starting over.
      notif(err?.response?.data?.message || t('orders_admin.settle.failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }
</script>

<style scoped>
  .payment-method-btn--selected {
    border-color: rgb(var(--v-theme-warning)) !important;
    color: rgb(var(--v-theme-warning));
  }
  .payment-summary-card {
    background: rgba(var(--v-theme-surface-variant), 0.3);
  }
  .payment-qr-placeholder {
    width: 160px;
    height: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 1.5px dashed rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 12px;
  }
</style>

<style>
  .payment-print-receipt {
    position: fixed;
    top: 0;
    left: -9999px;
  }

  @media print {
    @page {
      size: 80mm auto;
      margin: 0;
    }
    body * {
      visibility: hidden;
    }
    .payment-print-receipt,
    .payment-print-receipt * {
      visibility: visible;
    }
    .payment-print-receipt {
      position: absolute;
      top: 0;
      left: 0;
      width: 80mm;
      padding: 4mm;
      font-size: 11px;
    }
  }
</style>
