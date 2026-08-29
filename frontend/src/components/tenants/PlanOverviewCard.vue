<template>
  <v-card rounded="lg" class="mb-4" variant="outlined">
    <template #prepend>
      <v-icon color="indigo" size="28">mdi-crown-outline</v-icon>
    </template>
    <template #append>
      <div class="d-flex ga-2">
        <v-btn
          v-if="subscription.status === 'active'"
          variant="tonal"
          color="success"
          rounded="lg"
          prepend-icon="mdi-refresh"
          class="text-none font-weight-bold"
          @click="$emit('renew')"
        >
          {{ t('subscription.renew_plan') }}
        </v-btn>
        <v-btn
          variant="tonal"
          color="indigo-darken-1"
          rounded="lg"
          prepend-icon="mdi-crown-outline"
          class="text-none font-weight-bold"
          @click="$emit('upgrade')"
        >
          {{ t('subscription.upgrade_plan') }}
        </v-btn>
      </div>
    </template>

    <template #title>
      {{ t('subscription.subscription_plan') }}
    </template>

    <v-divider />

    <v-card-text>
      <v-row align="center">
        <!-- Plan Info -->
        <v-col cols="12" sm="6">
          <div class="text-h5 font-weight-bold text-indigo-darken-2">
            {{ plan.name ?? t('subscription.no_active_plan') }}
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

          <v-chip class="mt-3" color="indigo" size="small" variant="tonal">
            {{ activeBillingCycle?.label?.toUpperCase() ?? '—' }}
          </v-chip>

          <!-- Status Chip -->
          <v-chip
            v-if="subscription.status"
            class="mt-2 ml-2"
            :color="getStatusColor()"
            size="small"
            variant="tonal"
          >
            {{ subscription.status.toUpperCase() }}
          </v-chip>
        </v-col>

        <!-- Stats -->
        <v-col cols="12" sm="6">
          <v-row dense>
            <v-col v-for="stat in planStats" :key="stat.label" cols="6">
              <v-sheet
                rounded="lg"
                border
                class="pa-3 text-center"
              >
                <div class="text-body-1 font-weight-bold text-indigo-darken-3">
                  {{ stat.value }}
                </div>
                <div class="text-caption text-medium-emphasis mt-1">
                  {{ stat.label }}
                </div>
              </v-sheet>
            </v-col>
          </v-row>
        </v-col>
      </v-row>

      <!-- Features -->
      <v-divider class="my-6" />
      <div class="text-subtitle-2 text-medium-emphasis mb-3">
        {{ t('subscription.whats_included') }}
      </div>
      <v-row dense>
        <v-col
          v-for="feature in translatedFeatures"
          :key="feature.key"
          cols="12"
          sm="6"
        >
          <div class="d-flex align-center ga-2 text-body-2">
            <v-icon color="success" size="18">mdi-check-circle</v-icon>
            {{ feature }}
          </div>
        </v-col>
      </v-row>

      <!-- Payment -->
      <v-divider class="my-6" />
      <div class="text-subtitle-2 text-medium-emphasis mb-3">
        {{ t('subscription.make_payment') }}
      </div>

      <PaymentBar
        class="mt-6"
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
  import { formatCurrency, formatDateText } from '@nong-official-dev/core'

  const { t, locale } = useI18n()

  const props = defineProps({
    plan: { type: Object, default: () => ({}) },
    subscription: { type: Object, default: () => ({}) },
    activeBillingCycle: { type: Object, default: null }, // ← add
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

    return [
      {
        label:
          sub.status === 'trial'
            ? t('billing.overview.trialEnds')
            : t('billing.overview.nextBilling'),
        value: endDate ? formatDateText(endDate) : '—'
      },
      {
        label: t('billing.overview.nextCharge'),
        value: formatCurrency(cyclePrice.value)
      },
      {
        label: t('billing.overview.userSeats'),
        value: props.plan.seats ?? '—'
      },
      {
        label: t('billing.overview.daysLeft'),
        value: endDate ? daysLeft(endDate) : '—'
      }
    ]
  })

  function daysLeft(dateStr) {
    if (!dateStr) return '—'
    const diff = new Date(dateStr) - new Date()
    const days = Math.ceil(diff / 86400000)
    return days > 0 ? `${days}d` : 'Expired'
  }

  function getStatusColor() {
    const status = props.subscription.status?.toLowerCase()
    if (status === 'trial') return 'warning'
    if (status === 'active') return 'success'
    if (status === 'cancelled') return 'error'
    return 'grey'
  }
</script>
