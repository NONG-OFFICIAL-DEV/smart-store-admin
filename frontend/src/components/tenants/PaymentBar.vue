<template>
  <v-sheet rounded="lg" border class="pa-4">
    <div class="d-flex align-center flex-wrap ga-3">
      <!-- Amount due -->
      <div class="amount-block">
        <div
          class="text-caption text-medium-emphasis font-weight-medium amount-label"
        >
          {{$t('subscription.amount_due')}}
        </div>
        <div
          class="text-h6 font-weight-bold text-indigo-darken-2 mt-1 amount-value"
        >
          {{ formattedAmount }}
        </div>
      </div>

      <v-spacer />

      <!-- Payment + upgrade actions -->
      <div class="d-flex align-center flex-wrap ga-2">
        <PaymentMethodBtn
          v-for="m in PAYMENT_METHODS"
          :key="m"
          :method="m"
          :loading="loadingMethod === m"
          @click="$emit('pay', m)"
        />
      </div>
    </div>
  </v-sheet>
</template>

<script setup>
  import { computed } from 'vue'
  import PaymentMethodBtn, { PAYMENT_METHODS } from './PaymentMethodBtn.vue'
  import { formatCurrency } from '@nong-official-dev/core'

  const props = defineProps({
    /** Numeric amount to display */
    amount: {
      type: [Number, String],
      default: 0
    },
    /** ISO currency code, e.g. 'USD', 'KHR' */
    currency: { type: String, default: 'USD' },
    /** Which method button is currently in loading state */
    loadingMethod: { type: String, default: null }
  })

  defineEmits([
    /** @emit pay — payload: method string ('aba' | 'bakong') */
    'pay'
  ])

  const formattedAmount = computed(() => formatCurrency(props.amount))
</script>

<style scoped>
  .amount-label {
    letter-spacing: 0.05em;
    text-transform: uppercase;
    font-size: 10px;
  }
  .amount-value {
    line-height: 1.2;
  }
</style>
