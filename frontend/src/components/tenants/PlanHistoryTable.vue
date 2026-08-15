<template>
  <div>
    <v-data-table
      :headers="headers"
      :items="history"
      :loading="loading"
      :no-data-text="t('subscription.plan_history.empty')"
      class="elevation-0"
      hover
    >
      <!-- Plan -->
      <template #item.plan="{ item }">
        <div class="d-flex align-center ga-2">
          <v-icon color="indigo" size="20">mdi-crown-outline</v-icon>
          <div>
            <div class="font-weight-semibold">
              {{ item.to_plan?.name ?? t('common.unknown') }}
            </div>
            <div
              v-if="item.from_plan"
              class="text-caption text-medium-emphasis"
            >
              {{ t('subscription.plan_history.from_prefix') }} {{ item.from_plan.name }}
            </div>
            <div v-else class="text-caption text-success">{{ t('subscription.plan_history.initial_plan') }}</div>
          </div>
        </div>
      </template>

      <!-- Date -->
      <template #item.date="{ item }">
        <div class="font-weight-medium">
          {{ formatDate(item.changed_at || item.created_at) }}
        </div>
        <div v-if="item.reason" class="text-caption text-medium-emphasis mt-1">
          {{ item.reason }}
        </div>
      </template>

      <!-- Price -->
      <template #item.price="{ item }">
        <div class="font-weight-semibold">
          {{ formatCurrency(calcPrice(item)) }}
        </div>
        <div
          v-if="item.billing_cycle"
          class="text-caption text-medium-emphasis"
        >
          / {{ item.billing_cycle.months }} {{ t('tenant_create.month_unit', item.billing_cycle.months) }}
          <span v-if="Number(item.billing_cycle.discount_percent) > 0">
            • {{ Number(item.billing_cycle.discount_percent).toFixed(0) }}{{ t('tenant_create.percent_off') }}
          </span>
        </div>
      </template>

      <!-- Status -->
      <template #item.status="{ item }">
        <v-chip
          :color="item.from_plan_id ? 'primary' : 'success'"
          variant="tonal"
          size="small"
          :prepend-icon="
            item.from_plan_id ? 'mdi-swap-horizontal' : 'mdi-check-circle'
          "
        >
          {{ item.from_plan_id ? t('subscription.plan_history.changed') : t('subscription.plan_history.initial') }}
        </v-chip>
      </template>
    </v-data-table>
  </div>
</template>

<script setup>
  import { formatCurrency, formatDate } from '@nong-official-dev/core'
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  defineProps({
    history: {
      type: Array,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    }
  })

  function calcPrice(item) {
    const base = Number(item.to_plan?.price_usd || 0)
    const months = item.billing_cycle?.months ?? 1
    const discount = Number(item.billing_cycle?.discount_percent || 0) / 100
    return (base * months * (1 - discount)).toFixed(2)
  }
  const headers = computed(() => [
    { title: t('billing.historyTable.plan'), key: 'plan', sortable: true },
    {
      title: t('billing.historyTable.changedDate'),
      key: 'date',
      sortable: true
    },
    {
      title: t('billing.historyTable.price'),
      key: 'price',
      sortable: true,
      align: 'end'
    },
    {
      title: t('billing.historyTable.status'),
      key: 'status',
      sortable: false,
      align: 'center'
    }
  ])
</script>

<style scoped>
  .v-data-table :deep(.v-data-table__th) {
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    color: rgb(var(--v-theme-medium-emphasis));
  }
</style>
