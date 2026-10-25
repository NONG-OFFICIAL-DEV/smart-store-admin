<template>
  <AppDialog
    v-model="model"
    :title="$t('pos.checkout.cash_dialog_title')"
    :max-width="400"
  >
    <div class="text-center mb-5">
      <div class="text-caption text-medium-emphasis mb-1">{{ $t('order.total') }}</div>
      <div class="text-h4 font-weight-bold">{{ formatMoney(total) }}</div>
    </div>

    <v-text-field
      v-model.number="received"
      type="number"
      min="0"
      :step="currency === 'KHR' ? 100 : 0.01"
      :label="$t('tables.payment.received_label', { symbol: currencySymbol() })"
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

    <v-card flat rounded="lg" class="pa-3 mb-2 pos-cash-dialog__summary">
      <div class="d-flex justify-space-between text-body-2 mb-2">
        <span class="text-medium-emphasis">{{ $t('tables.payment.received') }}</span>
        <span class="font-weight-medium">{{ formatMoney(received ?? 0) }}</span>
      </div>
      <v-divider class="mb-2" />
      <div class="d-flex justify-space-between align-center">
        <span
          class="text-body-2 font-weight-bold"
          :class="diff >= 0 ? 'text-success' : 'text-warning'"
        >
          {{ diff >= 0 ? $t('pos.checkout.change') : $t('tables.payment.remaining') }}
        </span>
        <div
          class="text-h6 font-weight-bold"
          :class="diff >= 0 ? 'text-success' : 'text-warning'"
        >
          {{ formatMoney(Math.abs(diff)) }}
        </div>
      </div>
    </v-card>

    <template #actions>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        height="44"
        block
        :disabled="!canConfirm"
        @click="onConfirm"
      >
        {{ $t('pos.checkout.cash_dialog_confirm') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const { t } = useI18n()
  const { format: formatMoney, currency, currencySymbol } = useCurrency()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    total: { type: Number, default: 0 }
  })
  const emit = defineEmits(['update:modelValue', 'confirm'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const received = ref(null)

  // Defaults to the exact amount due every time the dialog opens — same
  // reasoning as the table-session Payment dialog: staff pays exact far
  // more often than not, so this saves a tap on the Exact chip every time.
  watch(
    () => props.modelValue,
    isOpen => {
      if (isOpen) received.value = props.total || null
    }
  )

  // Same quick-button set as the table-session Payment dialog (tables/PaymentDialog.vue)
  // — denominations match the tenant's own base currency, never assume USD.
  const quickButtons = computed(() => {
    const denominations = currency.value === 'KHR' ? [5000, 10000, 20000, 50000] : [5, 10, 20, 50]
    return [
      { key: 'exact', label: t('tables.payment.quick_exact'), type: 'exact' },
      ...denominations.map(amount => ({ key: `d${amount}`, label: formatMoney(amount), type: 'add', amount })),
      { key: 'clear', label: t('tables.payment.quick_clear'), type: 'clear' }
    ]
  })

  function applyQuickButton(btn) {
    if (btn.type === 'exact') {
      received.value = props.total
    } else if (btn.type === 'add') {
      received.value = Number(received.value ?? 0) + btn.amount
    } else if (btn.type === 'clear') {
      received.value = null
    }
  }

  const diff = computed(() => Number(received.value ?? 0) - props.total)

  const canConfirm = computed(() => Number(received.value ?? 0) >= props.total)

  function onConfirm() {
    emit('confirm', Number(received.value ?? 0))
    model.value = false
  }
</script>

<style scoped>
  .pos-cash-dialog__summary {
    background: rgba(var(--v-theme-surface-variant), 0.3);
  }
</style>
