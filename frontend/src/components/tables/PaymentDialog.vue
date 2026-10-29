<template>
  <v-dialog
    v-model="model"
    :max-width="440"
    :fullscreen="smAndDown"
    :location="smAndDown ? undefined : 'top'"
    :persistent="saving"
    scrollable
  >
    <v-card rounded="lg" class="d-flex flex-column" :style="{ maxHeight: `${availableHeight - 16}px` }">
      <v-card-title class="d-flex align-center justify-space-between pa-4 flex-grow-0">
        <span class="text-h6">{{ t('tables.payment.title') }}</span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" :disabled="saving" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="py-4 flex-grow-1" style="overflow-y: auto">
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
      <!-- Touch: readonly + inputmode="none" so the system keyboard never
           opens — tapping the field opens the NumPad instead. -->
      <v-menu v-if="isTouch" v-model="numpadOpen" :close-on-content-click="false" location="bottom">
        <template #activator="{ props: menuProps }">
          <v-text-field
            v-bind="menuProps"
            :model-value="received"
            :label="t('tables.payment.received_label', { symbol: currencySymbol() })"
            variant="outlined"
            density="compact"
            rounded="lg"
            class="mb-3"
            hide-details
            readonly
            inputmode="none"
          />
        </template>
        <v-card rounded="lg" width="280" class="pa-3">
          <NumPad v-model="received" decimal :suffix="currencySymbol()" />
        </v-card>
      </v-menu>
      <v-text-field
        v-else
        v-model.number="received"
        type="number"
        min="0"
        :step="currency === 'KHR' ? 100 : 0.01"
        inputmode="decimal"
        :label="t('tables.payment.received_label', { symbol: currencySymbol() })"
        variant="outlined"
        density="compact"
        rounded="lg"
        class="mb-3"
        hide-details
        @focus="keepVisible($event.target)"
        @keydown.enter="$event.target.blur()"
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
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        enterkeyhint="done"
        @focus="keepVisible($event.target)"
        @keydown.enter="$event.target.blur()"
      />
    </template>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4 flex-grow-0 d-block">
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
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useDisplay } from 'vuetify'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import { useKeyboardSafeArea } from '@/composables/useKeyboardSafeArea.js'
  import { useReceipt, printReceiptBrowser } from '@/utils/printReceipt'
  import { paySessionApi } from '@/api/tableService'
  import NumPad from '@/components/shared/NumPad.vue'

  const { t } = useI18n()
  const { smAndDown } = useDisplay()
  const { notif } = useAppUtils()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { formatBase, currency, currencySymbol, roundMoney } = useCurrency()
  const { formatDateTime } = useDate()
  const { isTouch } = useTouchDevice()
  const { availableHeight, keepVisible } = useKeyboardSafeArea()
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
  const numpadOpen = ref(false)

  watch(
    () => props.modelValue,
    isOpen => {
      if (!isOpen) {
        numpadOpen.value = false
        return
      }
      const defaultMethod = authStore.posSettings?.default_table_payment_method
      const options = paymentOptions.value
      method.value = options.some(o => o.value === defaultMethod) ? defaultMethod : (options[0]?.value ?? 'cash')
      // Defaults to the exact amount due — staff pays exact change far more
      // often than not, so this saves a tap on the Exact chip every time;
      // they can still clear/edit it for a round-number cash payment.
      received.value = total.value || null
      reference.value = ''
      paidReceiptData.value = null
    }
  )

  // props.orders can now include already-paid orders (BillDrawer shows the
  // table's whole sitting, not just what's owed) — the amount actually
  // charged here must only ever sum what's still unpaid, or staff would
  // recollect money on an order that's already settled.
  const unpaidOrders = computed(() => props.orders.filter(o => !o.payment))

  // Summing several already-float money values in JS (e.g. 12.99 + 13.25)
  // can land on something like 26.240000000000002 — roundMoney() clamps it
  // to the tenant's actual currency precision (cents for USD, whole numbers
  // for KHR) so it never leaks into the "Received" number input below,
  // which (unlike formatBase()'d display text) shows whatever raw value is
  // assigned to it.
  const totals = computed(() => {
    const sum = key => roundMoney(unpaidOrders.value.reduce((sum2, o) => sum2 + Number(o[key] ?? 0), 0))
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
    const denominations = currency.value === 'KHR' ? [10000, 20000, 50000] : [10, 20, 50]
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

  // Same combined-bill handling as BillDrawer.vue's combinedOrderNumber.
  const combinedOrderNumber = orders =>
    orders.length > 1
      ? t('tables.bill.order_count', { count: orders.length })
      : '#' + shortOrderNumber(orders[0] ?? {})

  function buildSessionReceiptData(receipt) {
    return {
      order_number: combinedOrderNumber(unpaidOrders.value),
      order_type: unpaidOrders.value[0]?.order_type ?? props.orders[0]?.order_type,
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
      await printReceiptBrowser(data, 'receipt')
    }
  }


  // Order matters here (see the bug this fixed): printing used to happen
  // BEFORE the dialog closed, so window.print() captured the still-open
  // AppDialog overlay (and whatever loading spinner was on screen) sitting
  // on top of the receipt. Now: 1) pay, 2) close the dialog (and the
  // drawer behind it, via 'paid'), 3) build the receipt from the API
  // response, 4) print — only after the dialog is gone and only on a
  // successful payment, never from inside the loading/catch state.
  async function confirmPayment() {
    if (!props.orders.length || saving.value) return
    const sessionId = props.orders[0]?.table_session_id
    if (!sessionId) {
      notif(t('orders_admin.settle.failed'), { type: 'error' })
      return
    }

    saving.value = true
    let receipt
    try {
      const res = await paySessionApi(sessionId, {
        payment_method: method.value,
        cash_tendered: method.value === 'cash' ? Number(received.value ?? 0) : undefined,
        reference: method.value !== 'cash' ? (reference.value || undefined) : undefined
      })
      receipt = res.data.data
    } catch (err) {
      // Keep the dialog open and change nothing on failure — staff can fix
      // the input (e.g. add more cash) and retry without starting over.
      // Never builds/prints a receipt for a payment that didn't succeed.
      notif(err?.response?.data?.message || t('orders_admin.settle.failed'), { type: 'error' })
      saving.value = false
      return
    }

    notif(t('tables.messages.table_paid', { number: props.table?.table_number }), { type: 'success' })
    emit('paid')
    close()
    saving.value = false

    paidReceiptData.value = buildSessionReceiptData(receipt)
    await printReceiptNow(paidReceiptData.value)
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
