<template>
  <v-row class="mb-4">
    <v-col v-for="bill in billingCards" :key="bill.label" cols="12" sm="4">
      <v-card rounded="lg" height="100%" variant="outlined">
        <v-card-text class="pa-5">
          <v-sheet
            :color="bill.bgColor"
            rounded="lg"
            width="42"
            height="42"
            class="d-flex align-center justify-center mb-4"
          >
            <v-icon :color="bill.iconColor" size="22">
              {{ bill.icon }}
            </v-icon>
          </v-sheet>

          <div class="text-caption text-medium-emphasis">
            {{ bill.label }}
          </div>
          <div class="text-h6 font-weight-semibold mt-1">
            {{ bill.value }}
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>

<script setup>
  import { computed } from 'vue'
  import { formatCurrency, formatDate } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  
  const props = defineProps({
    billing: { type: Object, default: () => ({}) },
    subscription: { type: Object, default: () => ({}) },
    activeBillingCycle: { type: Object, default: null },
    plan: { type: Object, default: () => ({}) } // ← add
  })
  const cyclePrice = computed(() => {
    const base = Number(props.plan.price_usd || 0)
    const months = props.activeBillingCycle?.months ?? 1
    const discount =
      Number(props.activeBillingCycle?.discount_percent || 0) / 100
    return (base * months * (1 - discount)).toFixed(2)
  })

  const nextBillingDate = computed(() => {
    const sub = props.subscription
    if (sub.status === 'trial') return sub.trial_ends_at
    return sub.current_period_end
  })

  const billingCards = computed(() => {
    const sub = props.subscription // ← define it here

    return [
      {
        label: t('billing.billingCards.lastPayment'),
        value: props.billing?.last_payment_date
          ? formatDate(props.billing.last_payment_date)
          : '—',
        icon: 'mdi-calendar-check',
        bgColor: 'green-lighten-5',
        iconColor: 'success'
      },
      {
        label:
          sub.status === 'trial'
            ? t('billing.billingCards.trialEnds')
            : t('billing.billingCards.nextBilling'),
        value: nextBillingDate.value ? formatDate(nextBillingDate.value) : '—',
        icon: 'mdi-calendar-clock',
        bgColor: 'indigo-lighten-5',
        iconColor: 'indigo'
      },
      {
        label: t('billing.billingCards.nextCharge'),
        value: formatCurrency(cyclePrice.value) ?? '—',
        icon: 'mdi-cash-multiple',
        bgColor: 'teal-lighten-5',
        iconColor: 'teal'
      }
    ]
  })
</script>
