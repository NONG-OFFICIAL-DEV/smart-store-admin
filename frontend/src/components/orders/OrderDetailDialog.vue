<template>
  <v-dialog v-model="model" max-width="640" scrollable>
    <v-card rounded="xl" elevation="0" border>
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="primary" variant="tonal" size="40" rounded="lg">
            <v-icon icon="mdi-receipt-text-outline" size="20" />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">
              {{ order?.order_number ?? '...' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ formatDate(order?.created_at) }}
            </div>
          </div>
        </div>
        <div class="d-flex align-center gap-2">
          <v-chip
            v-if="order"
            size="small"
            rounded="lg"
            :color="statusColor(order.status)"
            variant="tonal"
          >
            {{ order.status }}
          </v-chip>
          <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-0">
        <!-- Loading -->

        <template v-if="order">
          <!-- ── Info Row ──────────────────────────────────────────────────── -->
          <div class="pa-5 pb-4">
            <v-row dense>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">Type</div>
                <div class="text-body-2 font-weight-medium capitalize">
                  {{ order.order_type?.replace('_', ' ') ?? '—' }}
                </div>
              </v-col>
              <v-col cols="6" sm="3">
                <div class="text-caption text-medium-emphasis">Customer</div>
                <div class="text-body-2 font-weight-medium">
                  {{ order.customer?.name ?? 'Walk-in' }}
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
              Items
            </div>
            <div
              v-for="item in order.items"
              :key="item.id"
              class="d-flex align-center justify-space-between mb-3"
            >
              <div class="d-flex align-center gap-3">
                <v-avatar
                  :image="item.product?.image_url"
                  color="grey-lighten-3"
                  size="40"
                  rounded="md"
                >
                  <v-icon
                    v-if="!item.product?.image_url"
                    icon="mdi-food"
                    size="18"
                  />
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-medium">
                    {{ item.product_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ format(item.unit_price) }} × {{ item.quantity }}
                  </div>
                  <div v-if="item.note" class="text-caption text-info">
                    Note: {{ item.note }}
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
              <span class="text-body-2 text-medium-emphasis">Subtotal</span>
              <span class="text-body-2">
                {{ format(order.subtotal) }}
              </span>
            </div>
            <div
              v-if="order.discount_amount > 0"
              class="d-flex justify-space-between mb-2"
            >
              <span class="text-body-2 text-medium-emphasis">Discount</span>
              <span class="text-body-2 text-error">
                -{{ format(order.discount_amount) }}
              </span>
            </div>
            <div
              v-if="order.tax_amount > 0"
              class="d-flex justify-space-between mb-2"
            >
              <span class="text-body-2 text-medium-emphasis">Tax</span>
              <span class="text-body-2">
                {{ format(order.tax_amount) }}
              </span>
            </div>
            <div
              v-if="order.service_charge > 0"
              class="d-flex justify-space-between mb-2"
            >
              <span class="text-body-2 text-medium-emphasis">
                Service Charge
              </span>
              <span class="text-body-2">
                {{ format(order.service_charge) }}
              </span>
            </div>
            <v-divider class="my-3" />
            <div class="d-flex justify-space-between">
              <span class="text-body-1 font-weight-bold">Total</span>
              <span class="text-body-1 font-weight-bold text-primary">
                {{ format(order.total_amount) }}
              </span>
            </div>
            <div
              v-if="order.amount_tendered"
              class="d-flex justify-space-between mt-2"
            >
              <span class="text-body-2 text-medium-emphasis">Tendered</span>
              <span class="text-body-2">
                {{ format(order.amount_tendered) }}
              </span>
            </div>
            <div
              v-if="order.change_amount"
              class="d-flex justify-space-between mt-1"
            >
              <span class="text-body-2 text-medium-emphasis">Change</span>
              <span class="text-body-2 text-success">
                {{ format(order.change_amount) }}
              </span>
            </div>
          </div>
        </template>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-5 pt-4 gap-3">
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
          Print Receipt
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useOrderStore } from '@/stores/orderStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()
  const store = useOrderStore()
  const { format } = useCurrency()

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

  watch(
    () => props.orderId,
    val => {
      if (val) fetchOrder()
    }
  )
  watch(
    () => props.modelValue,
    val => {
      if (!val) order.value = null
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

  const formatDate = v =>
    v
      ? new Date(v).toLocaleString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        })
      : '—'
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
