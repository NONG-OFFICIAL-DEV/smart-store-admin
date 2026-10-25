<template>
  <v-navigation-drawer
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    location="end"
    width="420"
    temporary
    class="pa-0"
  >
    <div class="d-flex flex-column fill-height">
      <!-- ── Header ─────────────────────────────────────────────────────── -->
      <div class="pa-4 border-b flex-grow-0 flex-shrink-0">
        <div class="d-flex align-center justify-space-between">
          <div>
            <div class="d-flex align-center gap-2">
              <span class="text-h6 font-weight-bold">
                {{ t('tables.order.bill_title', { number: table?.table_number }) }}
              </span>
              <span class="table-tile__dot table-tile__dot--occupied" />
              <span class="text-caption text-warning font-weight-medium">
                {{ t('tables.order.occupied_label') }}
              </span>
            </div>
            <div v-if="orders.length" class="text-caption text-medium-emphasis mt-1">
              {{ t('tables.order.pax_orders', { pax: table?.capacity ?? 0, orders: orders.length }) }}
            </div>
          </div>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="large"
            @click="close"
          />
        </div>
      </div>

      <!-- ── Body ───────────────────────────────────────────────────────── -->
      <div class="flex-grow-1 overflow-y-auto">
        <template v-if="orders.length">
          <div
            v-for="o in orders"
            :key="o.id"
            class="pa-4 border-b"
          >
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2 font-weight-bold">
                {{ t('tables.order.order_time', { number: shortOrderNumber(o), time: formatTime(o.created_at) }) }}
              </span>
              <v-chip size="x-small" rounded="lg" :color="statusColor(o.status)" variant="tonal">
                {{ statusLabel(o.status) }}
              </v-chip>
            </div>
            <div
              v-for="item in o.items"
              :key="item.id"
              class="bill-item-row"
            >
              <span class="text-body-2 text-truncate">{{ item.product_name }}</span>
              <span class="text-body-2 text-medium-emphasis text-right">×{{ item.quantity }}</span>
              <span class="text-body-2 font-weight-medium text-right">{{ formatBase(item.total_price) }}</span>
            </div>
          </div>
        </template>
        <div v-else class="pa-8 text-center text-medium-emphasis">
          {{ t('tables.order.no_open_bill') }}
        </div>
      </div>

      <!-- ── Footer ─────────────────────────────────────────────────────── -->
      <div v-if="orders.length" class="pa-4 border-t flex-grow-0 flex-shrink-0">
        <div class="d-flex justify-space-between mb-1">
          <span class="text-body-2 text-medium-emphasis">{{ t('order.subtotal') }}</span>
          <span class="text-body-2">{{ formatBase(totals.subtotal) }}</span>
        </div>
        <div v-if="totals.discount > 0" class="d-flex justify-space-between mb-1">
          <span class="text-body-2 text-medium-emphasis">{{ t('order.discount') }}</span>
          <span class="text-body-2 text-error">-{{ formatBase(totals.discount) }}</span>
        </div>
        <div v-if="totals.tax > 0" class="d-flex justify-space-between mb-1">
          <span class="text-body-2 text-medium-emphasis">{{ t('order.tax') }}</span>
          <span class="text-body-2">{{ formatBase(totals.tax) }}</span>
        </div>
        <div v-if="totals.serviceCharge > 0" class="d-flex justify-space-between mb-1">
          <span class="text-body-2 text-medium-emphasis">{{ t('branches.detail.service_charge') }}</span>
          <span class="text-body-2">{{ formatBase(totals.serviceCharge) }}</span>
        </div>
        <v-divider class="my-2" />
        <div class="d-flex justify-space-between align-center mb-3">
          <span class="text-body-1 font-weight-bold">{{ t('order.total') }}</span>
          <div class="text-h6 font-weight-bold text-primary">{{ formatBase(totals.total) }}</div>
        </div>
        <div class="d-flex gap-2">
          <v-btn
            variant="outlined"
            rounded="lg"
            height="44"
            style="flex: 1 1 0; min-width: 0"
            @click="printBill"
          >
            {{ t('order.print_bill') }}
          </v-btn>
          <v-btn
            color="warning"
            variant="flat"
            rounded="lg"
            height="44"
            style="flex: 1 1 0; min-width: 0"
            @click="emit('pay')"
          >
            {{ t('tables.order.pay_button', { amount: formatBase(totals.total) }) }}
          </v-btn>
        </div>
      </div>
    </div>
  </v-navigation-drawer>
</template>

<script setup>
  import { computed, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'
  import { useReceipt, printReceiptBrowser } from '@/utils/printReceipt'
  import { getTableBillApi } from '@/api/tableService'

  const { t } = useI18n()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { formatBase } = useCurrency()
  const { formatDateTime, formatTime } = useDate()
  const { print: printThermal } = useReceipt()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    // Already-fetched orders for this table's open session (same shape
    // OrderController::formatOrder returns) — TableManagement always has
    // these from fetchOrdersForTable() before opening this drawer.
    orders: { type: Array, default: () => [] },
    // The Table resource itself — source of table_number/capacity/
    // open_session, so the header doesn't need to re-derive pax/order
    // counts from the orders array.
    table: { type: Object, default: null }
  })
  // 'pay' — staff tapped the Pay button; TableManagement owns opening the
  // actual Payment dialog since it already holds orders/table in scope for
  // both this drawer and that dialog.
  const emit = defineEmits(['update:modelValue', 'pay'])

  const close = () => emit('update:modelValue', false)

  // Local aliases so the template can read `table`/`orders` directly
  // instead of `props.table`/`props.orders` everywhere.
  const table = computed(() => props.table)
  const orders = computed(() => props.orders)

  const totals = computed(() => {
    const sum = key => props.orders.reduce((total, o) => total + Number(o[key] ?? 0), 0)
    return {
      subtotal: sum('subtotal'),
      discount: sum('discount_amount'),
      tax: sum('tax_amount'),
      serviceCharge: sum('service_charge_amount'),
      total: sum('total_amount')
    }
  })

  // The random suffix of order_number (e.g. "ORD-FOO-261020-AB3D" → "AB3D")
  // — compact enough for a per-order block header, still traceable back to
  // the full order number if staff need it.
  const shortOrderNumber = o => (o.order_number ?? '').split('-').pop() || o.id?.slice(0, 4)

  // order_number resets per branch per day (see Order::generateOrderNumber()),
  // so a table with several separate orders can mix short new-format numbers
  // with longer legacy ones — listing every one of them on the printed bill
  // got long and inconsistent. The Table row already identifies which bill
  // this is, so a combined bill's Order # row just needs a count.
  const combinedOrderNumber = orders =>
    orders.length > 1
      ? t('tables.bill.order_count', { count: orders.length })
      : '#' + shortOrderNumber(orders[0] ?? {})

  // The session-merged bill (items merged by product+variant+price — see
  // TableSessionService::getSessionBill()), fetched once whenever the
  // drawer opens so the printed bill never shows the same product as two
  // separate duplicate lines the way summing raw per-order items would.
  // The on-screen footer keeps using `totals` above (synchronous, from
  // props.orders already in memory) so it never waits on this fetch — the
  // numbers always agree since both sum the exact same orders.
  const sessionBill = ref(null)
  watch(
    () => props.modelValue,
    async isOpen => {
      sessionBill.value = null
      if (!isOpen || !props.table?.id) return
      try {
        const res = await getTableBillApi(props.table.id)
        sessionBill.value = res.data.data
      } catch {
        sessionBill.value = null
      }
    }
  )

  // Same labels/colors as the Orders admin list and Kitchen Display
  // (orders_admin.status.*) — one source of truth for how an Order's
  // kitchen-facing status reads to staff.
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

  const activeBranch = computed(
    () => branchStore.branches.find(b => b.id === authStore.activeBranchId) ?? null
  )

  // "Print bill" is a pre-bill preview only — it must NEVER mark anything
  // paid or free the table, unlike the final receipt the Payment dialog
  // prints after a successful Confirm.
  const printReceiptData = computed(() => {
    if (!props.orders.length) return null
    const merged = sessionBill.value
    return {
      order_number: combinedOrderNumber(props.orders),
      order_type: props.orders[0]?.order_type,
      table_number: props.table?.table_number,
      branch_name: activeBranch.value?.name,
      branch_address: activeBranch.value?.address_line1,
      branch_phone: activeBranch.value?.phone,
      cashier: props.orders[0]?.cashier,
      date: formatDateTime(new Date()),
      // Prefer the session-merged items (no duplicate lines) — falls back
      // to the raw per-order flat-map only for the brief window before the
      // bill fetch above resolves, so printing is never blank.
      items: merged
        ? merged.items.map(i => ({
            name: i.product_name,
            qty: i.quantity,
            unit_price: i.unit_price,
            total_price: i.total_price
          }))
        : props.orders.flatMap(o => (o.items ?? []).map(i => ({
            name: i.product_name,
            qty: i.quantity,
            unit_price: i.unit_price,
            total_price: i.total_price,
            note: i.notes
          }))),
      subtotal: merged?.subtotal ?? totals.value.subtotal,
      discount: merged?.discount ?? totals.value.discount,
      tax: merged?.tax ?? totals.value.tax,
      service_charge: merged?.service_charge ?? totals.value.serviceCharge,
      total: merged?.total ?? totals.value.total,
      // Unpaid — no rate recorded yet, PosReceiptContent falls back to
      // today's live setting.
      payment_method: null,
      exchange_rate: null
    }
  })

  async function printBill() {
    const printerName = activeBranch.value?.printer_name
    if (printerName && printReceiptData.value) {
      await printThermal(printReceiptData.value, printerName)
    } else {
      await printReceiptBrowser(printReceiptData.value, 'bill')
    }
  }
</script>

<style scoped>
  .border-b {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .border-t {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .fill-height {
    height: 100%;
  }
  .bill-item-row {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 8px;
    padding: 4px 0;
  }
  .table-tile__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex: none;
  }
  .table-tile__dot--occupied {
    background: rgb(var(--v-theme-warning));
  }
</style>
