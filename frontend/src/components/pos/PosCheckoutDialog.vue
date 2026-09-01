<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('pos.checkout.title')"
    :subtitle="$t('pos.checkout.subtitle')"
    icon="mdi-cash-register"
    color="primary"
    :max-width="440"
    :loading="loading"
    :submit-text="$t('pos.checkout.submit')"
    submit-icon="mdi-check-circle-outline"
    :disable-submit="!canSubmit"
    :error-message="errorMessage"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="$emit('close')"
    @submit="submit"
  >
    <div class="d-flex justify-space-between align-center mb-4">
      <span class="text-body-1">{{ $t('pos.checkout.total_estimate') }}</span>
      <span class="text-h5 font-weight-black text-primary">{{ formatMoney(subtotal) }}</span>
    </div>

    <div class="text-caption text-medium-emphasis mb-4">
      {{ $t('pos.checkout.total_note') }}
    </div>

    <v-select
      v-model="paymentMethod"
      :items="paymentMethods"
      item-title="label"
      item-value="value"
      :label="$t('pos.checkout.payment_method')"
      variant="outlined"
      rounded="lg"
      :disabled="loading"
    />

    <v-text-field
      v-if="paymentMethod === 'cash'"
      v-model.number="cashTendered"
      type="number"
      min="0"
      step="0.01"
      :label="$t('pos.checkout.cash_tendered')"
      variant="outlined"
      rounded="lg"
      :disabled="loading"
    />

    <div v-if="paymentMethod === 'cash'" class="d-flex justify-space-between text-body-2">
      <span class="text-medium-emphasis">{{ $t('pos.checkout.change') }}</span>
      <span class="font-weight-bold" :class="changeDue < 0 ? 'text-error' : 'text-success'">
        {{ formatMoney(changeDue) }}
      </span>
    </div>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    subtotal: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' }
  })

  const emit = defineEmits(['update:modelValue', 'close', 'submit'])

  const { t } = useI18n()

  const paymentMethod = ref('cash')
  const cashTendered = ref(0)

  const paymentMethods = computed(() => [
    { value: 'cash', label: t('pos.checkout.methods.cash') },
    { value: 'card', label: t('pos.checkout.methods.card') },
    { value: 'qr', label: t('pos.checkout.methods.qr') }
  ])

  watch(
    () => props.modelValue,
    val => {
      if (val) {
        paymentMethod.value = 'cash'
        cashTendered.value = Number(props.subtotal.toFixed(2))
      }
    }
  )

  const changeDue = computed(() => (cashTendered.value ?? 0) - props.subtotal)

  const canSubmit = computed(() => {
    if (paymentMethod.value !== 'cash') return true
    return cashTendered.value !== null && cashTendered.value !== '' && cashTendered.value >= 0
  })

  function submit() {
    emit('submit', {
      payment_method: paymentMethod.value,
      cash_tendered: paymentMethod.value === 'cash' ? cashTendered.value : undefined
    })
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value ?? 0)
  }
</script>
