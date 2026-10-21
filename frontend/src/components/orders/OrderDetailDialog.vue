<template>
  <AppDialog
    v-model="model"
    :max-width="640"
    :persistent="false"
    :title="dialogTitle"
  >
    <template v-if="orders.length">
      <div>
        <!-- ── Info Row ──────────────────────────────────────────────────── -->
        <div class="pa-5 pb-4">
          <v-row dense>
            <v-col cols="6" sm="3">
              <div class="text-caption text-medium-emphasis">
                {{ t('form.type') }}
              </div>
              <div class="text-body-2 font-weight-medium capitalize">
                {{ order.order_type?.replace('_', ' ') ?? '—' }}
              </div>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="text-caption text-medium-emphasis">
                {{ t('customers.table.customer') }}
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ order.customer?.name ?? t('customers.source.walk_in') }}
              </div>
              <div
                v-if="order.customer?.phone"
                class="text-caption text-medium-emphasis"
              >
                {{ order.customer.phone }}
              </div>
            </v-col>
            <v-col>
              <v-chip
                size="small"
                rounded="lg"
                :color="statusColor(order.status)"
                variant="tonal"
              >
                {{ statusLabel(order.status) }}
              </v-chip>
            </v-col>
          </v-row>
        </div>

        <v-divider />

        <!-- ── Order Items ───────────────────────────────────────────────── -->
        <div class="pa-5 pb-3">
          <div
            class="text-caption text-medium-emphasis font-weight-bold mb-3 text-uppercase"
          >
            {{ t('order.items') }}
          </div>
          <div
            v-for="item in combinedItems"
            :key="item.id"
            class="d-flex align-center justify-space-between mb-3"
          >
            <div class="d-flex align-center gap-3">
              <v-avatar color="grey-lighten-3" size="40" rounded="md">
                <v-img v-if="item.image_url" :src="item.image_url" cover />
                <v-icon v-else icon="mdi-food" size="18" />
              </v-avatar>
              <div>
                <div class="text-body-2 font-weight-medium">
                  {{ item.product_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ formatBase(item.unit_price) }} × {{ item.quantity }}
                  <span v-if="item.unit_name">({{ item.unit_name }})</span>
                </div>
                <div v-if="item.modifiers?.length" class="text-caption text-medium-emphasis">
                  {{ item.modifiers.map(m => m.name).join(', ') }}
                </div>
                <div v-if="item.notes" class="text-caption text-info">
                  {{ t('order.note_prefix') }} {{ item.notes }}
                </div>
              </div>
            </div>
            <div class="text-body-2 font-weight-bold">
              {{ formatBase(item.total_price) }}
            </div>
          </div>
        </div>

        <v-divider />

        <!-- ── Totals ────────────────────────────────────────────────────── -->
        <div class="pa-5 pt-4">
          <div class="d-flex justify-space-between mb-2">
            <span class="text-body-2 text-medium-emphasis">
              {{ t('order.subtotal') }}
            </span>
            <span class="text-body-2">
              {{ formatBase(combinedTotals.subtotal) }}
            </span>
          </div>
          <div
            v-if="combinedTotals.discount > 0"
            class="d-flex justify-space-between mb-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('order.discount') }}
            </span>
            <span class="text-body-2 text-error">
              -{{ formatBase(combinedTotals.discount) }}
            </span>
          </div>
          <div
            v-if="combinedTotals.tax > 0"
            class="d-flex justify-space-between mb-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('order.tax') }}
            </span>
            <span class="text-body-2">
              {{ formatBase(combinedTotals.tax) }}
            </span>
          </div>
          <div
            v-if="combinedTotals.serviceCharge > 0"
            class="d-flex justify-space-between mb-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('branches.detail.service_charge') }}
            </span>
            <span class="text-body-2">
              {{ formatBase(combinedTotals.serviceCharge) }}
            </span>
          </div>
          <v-divider class="my-3" />
          <div class="d-flex justify-space-between">
            <span class="text-body-1 font-weight-bold">
              {{ t('order.total') }}
            </span>
            <div class="text-body-1 font-weight-bold text-primary">
              {{ formatBase(combinedTotals.total) }}
            </div>
          </div>
          <div
            v-if="order.amount_tendered"
            class="d-flex justify-space-between mt-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('pos.cash_tendered') }}
            </span>
            <span class="text-body-2">
              {{ formatBase(order.amount_tendered) }}
            </span>
          </div>
          <div
            v-if="order.change_amount"
            class="d-flex justify-space-between mt-1"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('pos.change') }}
            </span>
            <span class="text-body-2 text-success">
              {{ formatBase(order.change_amount) }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="pa-8 text-center text-medium-emphasis">
        {{ t('tables.order.no_open_bill') }}
      </div>
    </template>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ t('btn.close') }}
      </v-btn>
      <v-spacer />
      <template v-if="orders.length">
        <v-btn
          v-if="unpaidOrders.length"
          color="success"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-cash-check"
          :loading="paying"
          :disabled="paying"
          @click="payNow"
        >
          {{ t('btn.pay') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-printer-outline"
          :disabled="paying"
          @click="print"
        >
          {{ t('order.print_bill') }}
        </v-btn>
      </template>
    </template>
  </AppDialog>

  <!-- Print-only copy, teleported to body — same reasoning as
       PosReceiptDialog.vue: the dialog's scrollable v-card-text clips an
       absolutely-positioned descendant at its own overflow boundary, so
       printing from inside it cut the content off. Rendered as an actual
       receipt layout (not the rich on-screen card view) since that's what
       belongs on a till printout. -->
  <Teleport to="body">
    <div v-if="modelValue && (paidReceiptData || printReceiptData)" id="receipt-print-root" class="order-print-receipt">
      <PosReceiptContent :receipt="paidReceiptData ?? printReceiptData" />
    </div>
  </Teleport>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useOrderStore } from '@/stores/orderStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useTableStore } from '@/stores/tableStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'
  import { useI18n } from 'vue-i18n'
  import { AppDialog, useAppUtils } from '@nong-official-dev/core'
  import { useReceipt } from '@/utils/printReceipt'
  import PosReceiptContent from '@/components/pos/PosReceiptContent.vue'
  import { paySessionApi } from '@/api/tableService'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const store = useOrderStore()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const tableStore = useTableStore()
  const { formatBase } = useCurrency()
  const { formatDateTime } = useDate()
  const { print: printThermal } = useReceipt()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    orderId: { type: String, default: null },
    // A table can carry more than one separate open order per sitting —
    // pass every id here and this dialog combines them into one view.
    // Takes priority over orderId when both are set.
    orderIds: { type: Array, default: null },
    // Already-fetched order objects (same shape OrderController::formatOrder
    // returns) — skips fetching entirely. Table Management already has the
    // full orders from fetchOrdersForTable(); passing only their ids here
    // would mean re-fetching every one of them again one by one for no
    // reason. Takes priority over orderIds/orderId when set.
    orders: { type: Array, default: null },
    // Table Management opts into this: printing the bill or paying is how
    // staff closes out a table, so the table frees up right after either
    // one. Other consumers (e.g. SaleReport's historical order lookup)
    // must NOT get this — acting on an old order there has nothing to do
    // with whichever table happens to be occupied today.
    freeTableOnComplete: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'paid'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const orders = ref([])
  const isCombined = computed(() => orders.value.length > 1)
  // Used throughout for the common single-order case — orders.value[0] is
  // only ever "the" order when there's exactly one.
  const order = computed(() => orders.value[0] ?? null)

  const unpaidOrders = computed(() => orders.value.filter(o => !o.payment))

  const paying = ref(false)
  // Set only right after a successful pay — the receipt for what was just
  // paid, built straight from the pay response (see printReceiptData for
  // the "still unpaid, Print Bill" shape used the rest of the time).
  const paidReceiptData = ref(null)

  // One click pays EVERY unpaid order in the table's session at once — not
  // just one order at a time. The backend does this in a single
  // transaction (TableSessionService::paySession()); this always sends the
  // session id, never a single order id, which is exactly what used to be
  // wrong here (clicking PAY only closed one order, leaving the table
  // Occupied until a second click). Falls back to paying a single order by
  // id only if it somehow has no session at all (shouldn't happen for a
  // dine-in table order post table-sessions, kept as a safety net).
  async function payNow() {
    if (!orders.value.length) return
    const sessionId = orders.value[0]?.table_session_id
    const table = orders.value[0]?.table

    paying.value = true
    try {
      if (sessionId) {
        const res = await paySessionApi(sessionId, {
          payment_method: 'cash',
          cash_tendered: combinedTotals.value.total
        })
        paidReceiptData.value = buildSessionReceiptData(res.data.data)
        await printReceiptNow(paidReceiptData.value)

        notif(t('tables.messages.table_paid', { number: table?.number }), { type: 'success' })
        orders.value = []
        close()
        await freeTable(table)
        emit('paid')
      } else {
        await payOneOrder(unpaidOrders.value[0], table)
      }
    } catch (err) {
      notif(err?.response?.data?.message || t('orders_admin.settle.failed'), { type: 'error' })
    } finally {
      paying.value = false
    }
  }

  async function payOneOrder(target, table) {
    const res = await store.settlePayment(target.id, {
      payment_method: 'cash',
      cash_tendered: target.total_amount
    })
    const paidOrder = res.data.data

    // Swap in the fresh, now-completed order (payment method/cash/change
    // attached) so the receipt reflects what was actually just paid.
    orders.value = orders.value.map(o => (o.id === target.id ? paidOrder : o))
    await printReceiptNow()
    orders.value = orders.value.filter(o => o.id !== target.id)

    if (!orders.value.length) {
      notif(t('tables.messages.table_paid', { number: table?.number }), { type: 'success' })
      close()
      await freeTable(table)
      emit('paid')
    } else {
      notif(t('orders_admin.settle.success'), { type: 'success' })
    }
  }

  // Builds the same shape printReceiptData does (see below), from
  // paySession()'s response — items/totals already merged across every
  // order in the session server-side.
  function buildSessionReceiptData(receipt) {
    return {
      order_number: dialogTitle.value,
      order_type: order.value?.order_type,
      table_number: order.value?.table?.number,
      branch_name: activeBranch.value?.name,
      branch_address: activeBranch.value?.address_line1,
      branch_phone: activeBranch.value?.phone,
      cashier: order.value?.cashier,
      date: formatDateTime(new Date()),
      items: receipt.items.map(i => ({
        name: i.product_name,
        unit: null,
        qty: i.quantity,
        unit_price: i.unit_price,
        total_price: i.total_price,
        note: null
      })),
      subtotal: receipt.subtotal,
      discount: receipt.discount,
      tax: receipt.tax,
      service_charge: receipt.service_charge,
      total: receipt.total,
      payment_method: receipt.payment?.method,
      cash_tendered: receipt.payment?.cash_tendered,
      change_given: receipt.payment?.change_given,
      // The rate actually used for this payment — PosReceiptContent prints
      // the KHR total converted at this rate, not today's live setting, so
      // the receipt stays correct even if the rate changes afterward.
      exchange_rate: receipt.payment?.exchange_rate
    }
  }

  const fetchOrders = async () => {
    if (props.orders?.length) {
      orders.value = props.orders
      return
    }
    const ids = props.orderIds?.length ? props.orderIds : props.orderId ? [props.orderId] : []
    if (!ids.length) return
    const results = await Promise.all(ids.map(id => store.fetchOrderById(id)))
    orders.value = results.map(res => res.data.data)
  }

  watch(
    () => [props.modelValue, props.orderId, props.orderIds, props.orders],
    ([isOpen]) => {
      if (isOpen) fetchOrders()
      else {
        orders.value = []
        paidReceiptData.value = null
      }
    }
  )

  const close = () => {
    model.value = false
  }

  // Every combined order shares the same table (that's why they're
  // combined) — leading with the table number reads better here than a
  // list of order numbers, which is what this dialog used to show.
  const dialogTitle = computed(() => {
    const tableNumber = order.value?.table?.number
    return tableNumber
      ? t('order.table_order_title', { number: tableNumber })
      : (order.value?.order_number ?? '...')
  })

  // Same product (with the same modifiers/notes) ordered across separate
  // tickets merges into one line with a summed quantity — e.g. 1 Coca on
  // the first order + 2 Coca on the second shows as a single "3× Coca"
  // line, not two rows the staff has to add up themselves.
  const combinedItems = computed(() => {
    const groups = new Map()
    for (const o of orders.value) {
      for (const item of o.items ?? []) {
        const modsKey = (item.modifiers ?? [])
          .map(m => m.name)
          .sort()
          .join(',')
        const key = `${item.product_name}|${modsKey}|${item.notes ?? ''}`
        const existing = groups.get(key)
        if (existing) {
          existing.quantity += item.quantity
          existing.total_price += Number(item.total_price ?? 0)
        } else {
          groups.set(key, {
            id: key,
            product_name: item.product_name,
            image_url: item.image_url,
            unit_price: item.unit_price,
            unit_name: item.unit_name,
            quantity: item.quantity,
            total_price: Number(item.total_price ?? 0),
            modifiers: item.modifiers,
            notes: item.notes
          })
        }
      }
    }
    return Array.from(groups.values())
  })

  const combinedTotals = computed(() => {
    const sum = key => orders.value.reduce((total, o) => total + Number(o[key] ?? 0), 0)
    return {
      subtotal: sum('subtotal'),
      discount: sum('discount_amount'),
      tax: sum('tax_amount'),
      serviceCharge: sum('service_charge_amount'),
      total: sum('total_amount')
    }
  })

  // This dialog is opened from Table Management, which is always scoped to
  // the globally active branch — reuse that for the printer name + the
  // receipt header, since the order itself carries neither.
  const activeBranch = computed(
    () => branchStore.branches.find(b => b.id === authStore.activeBranchId) ?? null
  )

  // Reused for both the thermal print (buildBytes reads this exact shape)
  // and the browser-print fallback (PosReceiptContent renders this exact
  // shape) — one mapping, two printers.
  const printReceiptData = computed(() => {
    const o = order.value
    if (!o) return null
    // Payment info is a single order's own record — ambiguous once
    // several orders are combined, so the combined receipt just omits
    // those lines rather than showing one order's payment as if it
    // covered the whole table.
    return {
      order_number: dialogTitle.value,
      order_type: o.order_type,
      table_number: o.table?.number,
      branch_name: activeBranch.value?.name,
      branch_address: activeBranch.value?.address_line1,
      branch_phone: activeBranch.value?.phone,
      cashier: o.cashier,
      date: formatDateTime(o.created_at),
      items: combinedItems.value.map(i => ({
        name: i.product_name,
        unit: i.unit_name,
        qty: i.quantity,
        unit_price: i.unit_price,
        total_price: i.total_price,
        note: i.notes
      })),
      subtotal: combinedTotals.value.subtotal,
      discount: combinedTotals.value.discount,
      tax: combinedTotals.value.tax,
      service_charge: combinedTotals.value.serviceCharge,
      total: combinedTotals.value.total,
      payment_method: isCombined.value ? null : o.payment?.method,
      cash_tendered: isCombined.value ? null : o.payment?.cash_tendered,
      change_given: isCombined.value ? null : o.payment?.change_given,
      // Unpaid (Print Bill) has no rate recorded yet — PosReceiptContent
      // falls back to today's live setting in that case.
      exchange_rate: isCombined.value ? null : o.payment?.exchange_rate
    }
  })

  // Thermal print when the branch has a printer configured; otherwise (or
  // if it fails) fall back to the browser's print dialog.
  async function printReceiptNow(data = printReceiptData.value) {
    const printerName = activeBranch.value?.printer_name
    if (printerName && data) {
      await printThermal(data, printerName)
    } else {
      window.print()
    }
  }

  async function print() {
    await printReceiptNow()
    await freeTable()
  }

  // Printing the bill or paying is how staff closes out a table here —
  // not gated on the print job actually completing (there's no reliable
  // signal for that from either the thermal or browser-print path), same
  // as every other action in this app completing immediately rather than
  // waiting on the printer. Accepts an explicit table since payNow() calls
  // this after already removing the just-paid order from orders.value —
  // by then order.value (orders.value[0]) may be null or a different order.
  async function freeTable(table = order.value?.table) {
    const tableId = table?.id
    if (!props.freeTableOnComplete || !tableId) return
    try {
      await tableStore.updateTableStatus(tableId, 'available')
      notif(t('tables.messages.status_changed', {
        number: table.number,
        status: t('tables.status.available')
      }), { type: 'success' })
    } catch {
      notif(t('tables.messages.status_update_failed'), { type: 'error' })
    }
  }

  // Same labels as the Orders admin list (orders_admin.status.*) — one
  // source of truth for how each Order status reads to staff.
  const statusLabel = s => t(`orders_admin.status.${s}`)

  const statusColor = s =>
    ({
      draft: 'grey',
      pending: 'warning',
      confirmed: 'info',
      preparing: 'info',
      ready: 'success',
      served: 'success',
      completed: 'success',
      cancelled: 'error',
      refunded: 'error'
    })[s] ?? 'grey'
</script>

<style scoped>
  .capitalize {
    text-transform: capitalize;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>

<style>
  /* Off-screen outside of print — the actual print layout for
     #receipt-print-root is shared across every receipt-printing dialog,
     see src/style.css. */
  .order-print-receipt {
    position: fixed;
    top: 0;
    left: -9999px;
  }
</style>
