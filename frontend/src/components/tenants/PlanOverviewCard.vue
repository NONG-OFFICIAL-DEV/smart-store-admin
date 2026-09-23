<template>
  <v-card rounded="lg" class="mb-4" elevation="0" border>
    <v-card-text class="pa-5 pb-4">
      <!-- Header: plan identity + actions -->
      <div class="d-flex flex-wrap justify-space-between align-start ga-4">
        <div class="d-flex align-center ga-3">
          <v-avatar color="indigo" variant="tonal" size="48" rounded="lg">
            <v-icon icon="mdi-crown-outline" size="24" />
          </v-avatar>
          <div>
            <div class="d-flex align-center flex-wrap ga-2">
              <span class="text-h5 font-weight-bold">
                {{ plan.name ?? t('subscription.no_active_plan') }}
              </span>
              <AppStatusChip
                v-if="subscription.status"
                :status="subscription.status"
                size="small"
              />
            </div>
            <div class="text-body-2 text-medium-emphasis mt-1">
              {{ t('subscription.billed') }}
              {{ activeBillingCycle?.label ?? '—' }} • ${{
                activeBillingCycle ? cyclePrice : (plan.price_usd ?? '—')
              }}
              /
              {{
                activeBillingCycle?.months > 1
                  ? activeBillingCycle.months + ' months'
                  : t('subscription.month')
              }}
            </div>
          </div>
        </div>

        <div class="d-flex ga-2">
          <v-btn
            v-if="subscription.status === 'active'"
            variant="outlined"
            color="success"
            rounded="lg"
            prepend-icon="mdi-refresh"
            class="text-none font-weight-bold"
            @click="$emit('renew')"
          >
            {{ t('subscription.renew_plan') }}
          </v-btn>
          <v-btn
            variant="flat"
            color="indigo-darken-1"
            rounded="lg"
            prepend-icon="mdi-crown-outline"
            class="text-none font-weight-bold"
            @click="$emit('upgrade')"
          >
            {{ t('subscription.upgrade_plan') }}
          </v-btn>
        </div>
      </div>

      <!-- Stats -->
      <v-row dense class="mt-5">
        <v-col v-for="stat in planStats" :key="stat.label" cols="6" sm="3">
          <v-sheet rounded="lg" border class="pa-3 h-100">
            <div class="d-flex align-center ga-2 mb-1">
              <v-icon :icon="stat.icon" size="16" color="indigo" />
              <span class="text-caption text-medium-emphasis">{{ stat.label }}</span>
            </div>
            <div class="text-body-1 font-weight-bold">{{ stat.value }}</div>
          </v-sheet>
        </v-col>
      </v-row>

      <!-- Features -->
      <v-divider class="my-6" />
      <div class="text-subtitle-2 text-medium-emphasis mb-3">
        {{ t('subscription.whats_included') }}
      </div>
      <div class="d-flex flex-wrap ga-2">
        <v-chip
          v-for="feature in translatedFeatures"
          :key="feature"
          size="small"
          variant="tonal"
          color="success"
          prepend-icon="mdi-check"
        >
          {{ feature }}
        </v-chip>
      </div>

      <!-- Payment -->
      <v-divider class="my-6" />
      <div class="text-subtitle-2 text-medium-emphasis mb-3">
        {{ t('subscription.make_payment') }}
      </div>

      <PaymentBar
        :amount="String(cyclePrice)"
        :currency="currency"
        :loading-method="loadingMethod"
        @pay="method => $emit('pay', method)"
      />
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import PaymentBar from './PaymentBar.vue'
  import { formatCurrency, formatDateText, AppStatusChip } from '@nong-official-dev/core'

  const { t, locale } = useI18n()

  const props = defineProps({
    plan: { type: Object, default: () => ({}) },
    subscription: { type: Object, default: () => ({}) },
    activeBillingCycle: { type: Object, default: null }, // ← add
    billing: { type: Object, default: () => ({}) },
    currency: { type: String, default: 'USD' },
    loadingMethod: { type: String, default: null }
  })

  defineEmits(['upgrade', 'pay', 'renew'])

  // Translated Features — catalog-joined list (label + this plan's value).
  // Boolean-type entries only show when true (the label itself is the
  // feature name, e.g. "Inventory management"); text-type entries show
  // the plan's own value text (e.g. "Up to 20 products") and are skipped
  // if no value has been set yet.
  const translatedFeatures = computed(() => {
    const featureList = props.plan.feature_list ?? []
    return featureList
      .filter(f => (f.value_type === 'boolean' ? f.value : f.value?.en))
      .map(f =>
        f.value_type === 'boolean'
          ? f.label?.[locale.value] || f.label?.en
          : f.value?.[locale.value] || f.value?.en
      )
  })

  const cyclePrice = computed(() => {
    const base = Number(props.plan.price_usd || 0)
    const months = props.activeBillingCycle?.months ?? 1
    const discount =
      Number(props.activeBillingCycle?.discount_percent || 0) / 100
    return (base * months * (1 - discount)).toFixed(2)
  })
  // Smart Stats based on your real data
  const planStats = computed(() => {
    const sub = props.subscription
    const endDate =
      sub.status === 'trial' ? sub.trial_ends_at : sub.current_period_end

    const stats = []

    if (props.billing?.last_payment_date) {
      stats.push({
        icon: 'mdi-cash-check',
        label: t('billing.overview.lastPayment'),
        value: formatDateText(props.billing.last_payment_date)
      })
    }

    stats.push(
      {
        icon: 'mdi-calendar-clock',
        label:
          sub.status === 'trial'
            ? t('billing.overview.trialEnds')
            : t('billing.overview.nextBilling'),
        value: endDate ? formatDateText(endDate) : '—'
      },
      {
        icon: 'mdi-currency-usd',
        label: t('billing.overview.nextCharge'),
        value: formatCurrency(cyclePrice.value)
      },
      {
        icon: 'mdi-account-group',
        label: t('billing.overview.userSeats'),
        value: props.plan.seats ?? '—'
      },
      {
        icon: 'mdi-clock-outline',
        label: t('billing.overview.daysLeft'),
        value: endDate ? daysLeft(endDate) : '—'
      }
    )

    return stats
  })

  function daysLeft(dateStr) {
    if (!dateStr) return '—'
    const diff = new Date(dateStr) - new Date()
    const days = Math.ceil(diff / 86400000)
    return days > 0 ? `${days}d` : 'Expired'
  }
</script>
