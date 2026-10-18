<template>
  <AppDialog
    v-model="model"
    :max-width="640"
    :persistent="false"
    :title="order?.order_number ?? '...'"
  >
    <template v-if="order">
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
            v-for="item in order.items"
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
                  {{ format(item.unit_price) }} × {{ item.quantity }}
                  <span v-if="item.unit_name">({{ item.unit_name }})</span>
                </div>
                <div v-if="item.note" class="text-caption text-info">
                  {{ t('order.note_prefix') }} {{ item.note }}
                </div>
              </div>
            </div>
            <div class="text-body-2 font-weight-bold">
              {{ format(item.unit_price * item.quantity) }}
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
              {{ format(order.subtotal) }}
            </span>
          </div>
          <div
            v-if="order.discount_amount > 0"
            class="d-flex justify-space-between mb-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('order.discount') }}
            </span>
            <span class="text-body-2 text-error">
              -{{ format(order.discount_amount) }}
            </span>
          </div>
          <div
            v-if="order.tax_amount > 0"
            class="d-flex justify-space-between mb-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('order.tax') }}
            </span>
            <span class="text-body-2">
              {{ format(order.tax_amount) }}
            </span>
          </div>
          <div
            v-if="order.service_charge > 0"
            class="d-flex justify-space-between mb-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('branches.detail.service_charge') }}
            </span>
            <span class="text-body-2">
              {{ format(order.service_charge) }}
            </span>
          </div>
          <v-divider class="my-3" />
          <div class="d-flex justify-space-between">
            <span class="text-body-1 font-weight-bold">
              {{ t('order.total') }}
            </span>
            <span class="text-body-1 font-weight-bold text-primary">
              {{ format(order.total_amount) }}
            </span>
          </div>
          <div
            v-if="order.amount_tendered"
            class="d-flex justify-space-between mt-2"
          >
            <span class="text-body-2 text-medium-emphasis">
              {{ t('pos.cash_tendered') }}
            </span>
            <span class="text-body-2">
              {{ format(order.amount_tendered) }}
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
              {{ format(order.change_amount) }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ t('btn.close') }}
      </v-btn>
      <v-spacer />
      <v-btn
        v-if="order && !order.payment"
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-cash-check"
        @click="settleDialog = true"
      >
        {{ t('orders_admin.settle.action') }}
      </v-btn>
      <v-btn
        variant="outlined"
        rounded="lg"
        prepend-icon="mdi-printer-outline"
        @click="print"
      >
        {{ t('order.print_receipt') }}
      </v-btn>
    </template>
  </AppDialog>

  <SettlePaymentDialog
    v-model="settleDialog"
    :order="order"
    @settled="onSettled"
  />

  <!-- Print-only copy, teleported to body — same reasoning as
       PosReceiptDialog.vue: the dialog's scrollable v-card-text clips an
       absolutely-positioned descendant at its own overflow boundary, so
       printing from inside it cut the content off. Rendered as an actual
       receipt layout (not the rich on-screen card view) since that's what
       belongs on a till printout. -->
  <Teleport to="body">
    <div v-if="modelValue && printReceiptData" class="order-print-receipt">
      <PosReceiptContent :receipt="printReceiptData" />
    </div>
  </Teleport>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useOrderStore } from '@/stores/orderStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'
  import { useReceipt } from '@/utils/printReceipt'
  import PosReceiptContent from '@/components/pos/PosReceiptContent.vue'
  import SettlePaymentDialog from '@/components/orders/SettlePaymentDialog.vue'

  const { t } = useI18n()
  const store = useOrderStore()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { format } = useCurrency()
  const { formatDateTime } = useDate()
  const { print: printThermal } = useReceipt()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    orderId: { type: String, default: null }
  })
  const emit = defineEmits(['update:modelValue'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const order = ref(null)
  const settleDialog = ref(false)

  function onSettled(updated) {
    order.value = updated
  }

  const fetchOrder = async () => {
    if (!props.orderId) return
    const res = await store.fetchOrderById(props.orderId)
    order.value = res.data.data
  }

  // Replace both watches with this single one
  watch(
    () => [props.modelValue, props.orderId],
    ([isOpen, id]) => {
      if (isOpen && id) fetchOrder()
      if (!isOpen) order.value = null
    }
  )

  const close = () => {
    model.value = false
  }

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
    return {
      order_number: o.order_number,
      order_type: o.order_type,
      table_number: o.table?.number,
      branch_name: activeBranch.value?.name,
      branch_address: activeBranch.value?.address_line1,
      branch_phone: activeBranch.value?.phone,
      cashier: o.cashier,
      date: formatDateTime(o.created_at),
      items: o.items.map(i => ({
        name: i.product_name,
        unit: i.unit_name,
        qty: i.quantity,
        unit_price: i.unit_price,
        total_price: i.total_price,
        note: i.notes
      })),
      subtotal: o.subtotal,
      discount: o.discount_amount,
      tax: o.tax_amount,
      service_charge: o.service_charge_amount,
      total: o.total_amount,
      payment_method: o.payment?.method,
      cash_tendered: o.payment?.cash_tendered,
      change_given: o.payment?.change_given
    }
  })

  // Thermal print when the branch has a printer configured; otherwise (or
  // if it fails) fall back to the browser's print dialog.
  async function print() {
    const printerName = activeBranch.value?.printer_name
    if (printerName && printReceiptData.value) {
      const ok = await printThermal(printReceiptData.value, printerName)
      if (ok) return
    }
    window.print()
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
  .order-print-receipt {
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
    .order-print-receipt,
    .order-print-receipt * {
      visibility: visible;
    }
    .order-print-receipt {
      position: absolute;
      top: 0;
      left: 0;
      width: 80mm;
      padding: 4mm;
      font-size: 11px;
    }
  }
</style>
