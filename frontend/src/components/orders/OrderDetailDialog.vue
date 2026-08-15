<template>
  <AppDialog
    v-model="model"
    :max-width="640"
    :persistent="false"
    icon="mdi-receipt-text-outline"
    color="primary"
    :title="order?.order_number ?? '...'"
    :subtitle="formatDate(order?.created_at)"
    body-class="pa-0"
    @close="close"
  >
    <template #header-extra>
      <div v-if="order" class="px-5 pb-3 d-flex justify-end">
        <v-chip
          size="small"
          rounded="lg"
          :color="statusColor(order.status)"
          variant="tonal"
        >
          {{ order.status }}
        </v-chip>
      </div>
    </template>

    <template v-if="order">
          <!-- ── Info Row ──────────────────────────────────────────────────── -->
          <div class="pa-5 pb-4">
            <v-row dense>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">{{ t('form.type') }}</div>
                <div class="text-body-2 font-weight-medium capitalize">
                  {{ order.order_type?.replace('_', ' ') ?? '—' }}
                </div>
              </v-col>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">{{ t('customers.table.customer') }}</div>
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
              <span class="text-body-2 text-medium-emphasis">{{ t('order.subtotal') }}</span>
              <span class="text-body-2">
                {{ format(order.subtotal) }}
              </span>
            </div>
            <div
              v-if="order.discount_amount > 0"
              class="d-flex justify-space-between mb-2"
            >
              <span class="text-body-2 text-medium-emphasis">{{ t('order.discount') }}</span>
              <span class="text-body-2 text-error">
                -{{ format(order.discount_amount) }}
              </span>
            </div>
            <div
              v-if="order.tax_amount > 0"
              class="d-flex justify-space-between mb-2"
            >
              <span class="text-body-2 text-medium-emphasis">{{ t('order.tax') }}</span>
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
              <span class="text-body-1 font-weight-bold">{{ t('order.total') }}</span>
              <span class="text-body-1 font-weight-bold text-primary">
                {{ format(order.total_amount) }}
              </span>
            </div>
            <div
              v-if="order.amount_tendered"
              class="d-flex justify-space-between mt-2"
            >
              <span class="text-body-2 text-medium-emphasis">{{ t('pos.cash_tendered') }}</span>
              <span class="text-body-2">
                {{ format(order.amount_tendered) }}
              </span>
            </div>
            <div
              v-if="order.change_amount"
              class="d-flex justify-space-between mt-1"
            >
              <span class="text-body-2 text-medium-emphasis">{{ t('pos.change') }}</span>
              <span class="text-body-2 text-success">
                {{ format(order.change_amount) }}
              </span>
            </div>
          </div>
        </template>

    <template #actions>
      <v-btn variant="tonal" rounded="lg" @click="close">
        {{ t('btn.close') }}
      </v-btn>
      <v-spacer />
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
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useOrderStore } from '@/stores/orderStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'
  import { useDate } from '@/composables/useDate'

  const { t } = useI18n()
  const store = useOrderStore()
  const { format } = useCurrency()
  const { formatShortDateTime: formatDate } = useDate()

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
  const print = () => window.print()

  const statusColor = s =>
    ({
      pending: 'warning',
      confirmed: 'info',
      preparing: 'info',
      ready: 'success',
      completed: 'success',
      cancelled: 'error'
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
