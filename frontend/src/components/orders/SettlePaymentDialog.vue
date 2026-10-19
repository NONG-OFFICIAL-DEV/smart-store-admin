<template>
  <AppDialog
    v-model="model"
    :title="t('orders_admin.settle.title')"
    :max-width="420"
    :persistent="false"
  >
    <div v-if="order" class="d-flex justify-space-between text-body-1 font-weight-bold mb-4">
      <span>{{ t('orders_admin.settle.amount_due') }}</span>
      <span>{{ format(order.total_amount) }}</span>
    </div>

    <div class="text-caption text-medium-emphasis mb-1">
      {{ t('pos.checkout.payment_method') }}
    </div>
    <v-btn-toggle
      v-model="paymentMethod"
      color="primary"
      variant="tonal"
      rounded="lg"
      mandatory
      class="w-100 mb-3 flex-wrap"
      :divided="false"
    >
      <v-btn
        v-for="opt in paymentOptions"
        :key="opt.value"
        :value="opt.value"
        class="flex-grow-1 text-none"
      >
        <v-icon start :icon="opt.icon" />
        {{ opt.label }}
      </v-btn>
    </v-btn-toggle>

    <template v-if="paymentMethod === 'cash'">
      <v-text-field
        :model-value="cashTendered ?? order?.total_amount"
        type="number"
        min="0"
        step="0.01"
        :label="t('pos.checkout.cash_tendered')"
        variant="outlined"
        density="compact"
        rounded="lg"
        class="mb-2"
        @update:model-value="cashTendered = $event === '' ? null : Number($event)"
      />
      <div class="d-flex justify-space-between text-body-2 mb-2">
        <span class="text-medium-emphasis">{{ t('pos.checkout.change') }}</span>
        <span class="font-weight-bold" :class="changeDue < 0 ? 'text-error' : 'text-success'">
          {{ format(changeDue) }}
        </span>
      </div>
    </template>

    <template #actions="{ loading: dialogLoading }">
      <v-btn variant="tonal" rounded="lg" :disabled="dialogLoading" @click="model = false">
        {{ t('btn.cancel') }}
      </v-btn>
      <v-spacer />
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        :loading="submitting"
        :disabled="!canSubmit"
        @click="submit"
      >
        {{ t('orders_admin.settle.submit') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'
  import { useOrderStore } from '@/stores/orderStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useAppUtils } from '@/composables/useAppUtils'

  const { t } = useI18n()
  const { format } = useCurrency()
  const { notif } = useAppUtils()
  const orderStore = useOrderStore()
  const authStore = useAuthStore()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    order: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'settled'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  // Same tenant-configured list POS checkout uses (Settings > POS), minus
  // Pay Later — settling a pay-later order *as* pay-later again makes no
  // sense, the whole point here is collecting the money it deferred.
  const ALL_PAYMENT_METHODS = [
    { value: 'cash', icon: 'mdi-cash', label: t('pos.checkout.methods.cash') },
    { value: 'card', icon: 'mdi-credit-card-outline', label: t('pos.checkout.methods.card') },
    { value: 'qr', icon: 'mdi-qrcode', label: t('pos.checkout.methods.qr') }
  ]
  const paymentOptions = computed(() => {
    const enabled = authStore.posSettings?.payment_methods ?? ['cash', 'card', 'qr']
    return ALL_PAYMENT_METHODS.filter(opt => enabled.includes(opt.value))
  })

  const paymentMethod = ref('cash')
  const cashTendered = ref(null)
  const submitting = ref(false)

  watch(
    () => props.modelValue,
    open => {
      if (open) {
        const defaultMethod = authStore.posSettings?.default_payment_method
        const options = paymentOptions.value
        paymentMethod.value = options.some(o => o.value === defaultMethod)
          ? defaultMethod
          : (options[0]?.value ?? 'cash')
        cashTendered.value = null
      }
    }
  )

  const changeDue = computed(
    () => (cashTendered.value ?? props.order?.total_amount ?? 0) - (props.order?.total_amount ?? 0)
  )

  const canSubmit = computed(() => {
    if (paymentMethod.value !== 'cash') return true
    const val = cashTendered.value ?? props.order?.total_amount
    return val !== null && val !== '' && val >= 0
  })

  async function submit() {
    if (!props.order) return
    submitting.value = true
    try {
      const res = await orderStore.settlePayment(props.order.id, {
        payment_method: paymentMethod.value,
        cash_tendered: paymentMethod.value === 'cash' ? (cashTendered.value ?? props.order.total_amount) : undefined
      })
      notif(t('orders_admin.settle.success'), { type: 'success' })
      emit('settled', res.data.data)
      model.value = false
    } catch (err) {
      notif(err.response?.data?.message ?? t('orders_admin.settle.failed'), { type: 'error' })
    } finally {
      submitting.value = false
    }
  }
</script>
