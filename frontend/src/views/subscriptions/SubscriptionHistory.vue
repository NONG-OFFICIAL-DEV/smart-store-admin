<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-history"
      :title="$t('subscription.history.title')"
      :subtitle="tenantName || $t('subscription.history.subtitle')"
    >
      <template #right>
        <v-btn variant="tonal" prepend-icon="mdi-arrow-left" rounded="lg" @click="$router.push('/subscriptions')">
          {{ $t('subscription.history.back') }}
        </v-btn>
      </template>
    </custom-title>

    <v-tabs v-model="tab" color="primary" density="comfortable" class="mb-3">
      <v-tab value="plan_history">{{ $t('subscription.history.tabs.plan_history') }}</v-tab>
      <v-tab value="payments">{{ $t('subscription.history.tabs.payments') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="plan_history">
        <v-card variant="flat" border rounded="lg" class="pa-4">
          <AppTable :headers="headers" :fetch-fn="fetchHistory" :filters="filters" :show-search="false" item-label="events">
            <template #[`item.change`]="{ item }">
              <div class="d-flex align-center ga-2">
                <v-chip v-if="item.from_plan" size="x-small" variant="tonal" color="grey">
                  {{ item.from_plan.name }}
                </v-chip>
                <span v-else class="text-caption text-medium-emphasis">{{ $t('subscription.history.none') }}</span>
                <v-icon icon="mdi-arrow-right" size="14" class="text-medium-emphasis" />
                <v-chip size="x-small" variant="tonal" color="primary">
                  {{ item.to_plan.name }}
                </v-chip>
              </div>
            </template>

            <template #[`item.reason`]="{ item }">
              <span class="text-body-2">{{ item.reason }}</span>
            </template>

            <template #[`item.changed_by`]="{ item }">
              <span class="text-body-2">{{ item.changed_by?.name ?? $t('subscription.history.system') }}</span>
            </template>

            <template #[`item.changed_at`]="{ item }">
              <span class="text-caption">{{ formatDate(item.changed_at) }}</span>
            </template>

            <template #no-data>
              <div class="text-center py-12">
                <v-icon icon="mdi-history" size="48" color="grey-lighten-1" class="mb-3" />
                <div class="text-body-2 text-medium-emphasis">
                  {{ $t('subscription.history.empty') }}
                </div>
              </div>
            </template>
          </AppTable>
        </v-card>
      </v-window-item>

      <v-window-item value="payments">
        <v-card variant="flat" border rounded="lg" class="pa-4">
          <AppTable :headers="paymentHeaders" :fetch-fn="fetchPayments" :show-search="false" item-label="payments">
            <template #[`item.invoice_number`]="{ item }">
              <span class="text-body-2 font-weight-medium">{{ item.invoice_number }}</span>
            </template>

            <template #[`item.amount`]="{ item }">
              <span class="text-body-2">{{ item.currency }} {{ item.amount_usd }}</span>
            </template>

            <template #[`item.paid_at`]="{ item }">
              <span class="text-caption">{{ formatDate(item.paid_at) }}</span>
            </template>

            <template #[`item.note`]="{ item }">
              <span class="text-body-2 text-medium-emphasis">{{ item.note ?? '—' }}</span>
            </template>

            <template #no-data>
              <div class="text-center py-12">
                <v-icon icon="mdi-cash-check" size="48" color="grey-lighten-1" class="mb-3" />
                <div class="text-body-2 text-medium-emphasis">
                  {{ $t('subscription.payments.empty') }}
                </div>
              </div>
            </template>
          </AppTable>
        </v-card>
      </v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { AppTable } from '@nong-official-dev/core'
  import { getSubscriptionPlanHistoryApi, getTenantPaymentsApi } from '@/api/subscriptionService'
  import { useDate } from '@/composables/useDate'

  const { t } = useI18n()
  const route = useRoute()
  const { formatDateTime: formatDate } = useDate()

  const tenantName = route.query.tenantName
  const tab = ref('plan_history')

  // tenant_id is required by the backend — this is a per-tenant drill-down,
  // not a global feed across every tenant.
  const filters = ref({ tenant_id: route.params.tenantId })

  const headers = [
    { title: t('subscription.history.change'), key: 'change', sortable: false },
    { title: t('subscription.history.reason'), key: 'reason', sortable: false },
    { title: t('subscription.history.changed_by'), key: 'changed_by', sortable: false },
    { title: t('subscription.history.changed_at'), key: 'changed_at' }
  ]

  const paymentHeaders = [
    { title: t('subscription.payments.table.invoice_number'), key: 'invoice_number', sortable: false },
    { title: t('subscription.payments.table.amount'), key: 'amount', sortable: false },
    { title: t('subscription.payments.table.paid_at'), key: 'paid_at', sortable: false },
    { title: t('subscription.payments.table.note'), key: 'note', sortable: false }
  ]

  async function fetchHistory(params) {
    const { data } = await getSubscriptionPlanHistoryApi(params)
    return { items: data.data, total: data.meta.total }
  }

  async function fetchPayments(params) {
    const { data } = await getTenantPaymentsApi(route.params.tenantId, params)
    return { items: data.data, total: data.meta.total }
  }
</script>
