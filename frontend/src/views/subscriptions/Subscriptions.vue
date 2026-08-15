<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-credit-card-outline"
      :title="$t('menu.subscriptions')"
      :subtitle="$t('subscription.list.subtitle')"
    />

    <!-- ── Filters ── -->
    <v-row dense class="mb-2">
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.status"
          :items="statusOptions"
          :label="$t('form.status')"
          clearable
          density="compact"
        />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable ref="tableRef" :headers="headers" :fetch-fn="fetchSubscriptions" :filters="filters" item-label="subscriptions">
        <!-- Tenant -->
        <template #[`item.tenant`]="{ item }">
          <div class="text-body-2 font-weight-medium">
            {{ item.tenant?.name ?? '—' }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.tenant?.slug }}
          </div>
        </template>

        <!-- Plan -->
        <template #[`item.plan`]="{ item }">
          <v-chip size="x-small" variant="tonal" :color="planColor(item.plan?.code)">
            {{ item.plan?.name ?? '—' }}
          </v-chip>
        </template>

        <!-- Status — an overdue 'active' row (past current_period_end) is
             flagged as Expired here even though the DB status is still
             'active' (there's no separate expired status in the schema);
             it can still be renewed, same as any other active row. -->
        <template #[`item.status`]="{ item }">
          <v-chip size="x-small" variant="flat" :color="isExpired(item) ? 'error' : statusColor(item.status)">
            {{ isExpired(item) ? $t('subscription.status.expired') : statusLabel(item.status) }}
          </v-chip>
        </template>

        <!-- Period -->
        <template #[`item.current_period_end`]="{ item }">
          <div class="text-body-2">
            {{ item.current_period_end ? formatDate(item.current_period_end) : '—' }}
          </div>
          <div v-if="item.trial_ends_at" class="text-caption text-medium-emphasis">
            {{ $t('subscription.status.trial') }}: {{ formatDate(item.trial_ends_at) }}
          </div>
          <div
            v-else-if="daysUntil(item.current_period_end) !== null && daysUntil(item.current_period_end) >= 0 && daysUntil(item.current_period_end) <= 7"
            class="text-caption text-warning"
          >
            {{ $t('subscription.list.expires_in_days', daysUntil(item.current_period_end)) }}
          </div>
        </template>

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex justify-end ga-1">
            <v-tooltip :text="$t('subscription.list.action.view_history')">
              <template #activator="{ props: tp }">
                <v-btn
                  v-bind="tp"
                  icon="mdi-history"
                  size="small"
                  variant="text"
                  rounded="lg"
                  @click="viewHistory(item)"
                />
              </template>
            </v-tooltip>
            <v-tooltip :text="$t('subscription.list.action.change_plan')">
              <template #activator="{ props: tp }">
                <v-btn
                  v-bind="tp"
                  icon="mdi-swap-horizontal"
                  size="small"
                  variant="text"
                  rounded="lg"
                  @click="openAssign(item)"
                />
              </template>
            </v-tooltip>
            <v-tooltip v-if="item.status === 'active'" :text="$t('subscription.list.action.renew')">
              <template #activator="{ props: tp }">
                <v-btn
                  v-bind="tp"
                  icon="mdi-autorenew"
                  size="small"
                  variant="text"
                  rounded="lg"
                  color="info"
                  :loading="renewingId === item.id"
                  @click="renewSub(item)"
                />
              </template>
            </v-tooltip>
            <v-tooltip
              v-if="item.status !== 'cancelled'"
              :text="item.status === 'active' ? $t('subscription.list.action.pause') : $t('subscription.list.action.resume')"
            >
              <template #activator="{ props: tp }">
                <v-btn
                  v-bind="tp"
                  :icon="item.status === 'active' ? 'mdi-pause-circle-outline' : 'mdi-play-circle-outline'"
                  size="small"
                  variant="text"
                  rounded="lg"
                  :color="item.status === 'active' ? 'warning' : 'success'"
                  @click="toggleSub(item)"
                />
              </template>
            </v-tooltip>
            <v-tooltip
              :text="['active', 'trial'].includes(item.status) ? $t('subscription.list.action.cancel') : $t('subscription.list.action.delete')"
            >
              <template #activator="{ props: tp }">
                <v-btn
                  v-bind="tp"
                  icon="mdi-delete-outline"
                  size="small"
                  variant="text"
                  color="error"
                  @click="deleteSub(item)"
                />
              </template>
            </v-tooltip>
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- ── Change Plan Dialog — assigning a tenant's FIRST plan happens on
         the Tenants page; this only changes an existing subscription. ── -->
    <AssignPlanDialog
      v-model="assignDialog"
      :tenant-id="assigningTenant?.tenant_id"
      :tenant-name="assigningTenant?.tenant?.name"
      :current-plan="assigningTenant?.plan"
      @saved="tableRef?.refresh()"
    />
  </v-container>
</template>

<script setup>
  import { ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { AppTable, useAppUtils } from '@nong-official-dev/core'
  import { useSubscriptionStore } from '@/stores/subscriptionStore'
  import { getAllsubscriptionsApi } from '@/api/subscriptionService'
  import { useDate } from '@/composables/useDate'
  import AssignPlanDialog from '@/components/subscriptions/AssignPlanDialog.vue'

  const { t } = useI18n()
  const router = useRouter()
  const { confirm, notif } = useAppUtils()
  const subscriptionStore = useSubscriptionStore()
  const { formatShortDate: formatDate } = useDate()

  const tableRef = ref(null)
  const renewingId = ref(null)

  const filters = ref({ status: null })

  // ── Dialog
  const assignDialog = ref(false)
  const assigningTenant = ref(null)

  // ── Table headers
  const headers = [
    { title: t('subscription.list.table.tenant'), key: 'tenant', sortable: false },
    { title: t('subscription.plan.table.name'), key: 'plan', sortable: false },
    { title: t('form.status'), key: 'status', sortable: true },
    { title: t('subscription.list.table.ends'), key: 'current_period_end', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  const statusOptions = [
    { title: t('status.active'), value: 'active' },
    { title: t('subscription.status.trial'), value: 'trial' },
    { title: t('subscription.status.suspended'), value: 'suspended' },
    { title: t('status.cancelled'), value: 'cancelled' }
  ]

  // Server-driven — matches BaseRepository::paginateServer()'s contract
  // (search/sortBy/sortDesc/page/perPage + status/tenant_id/plan_id, see
  // TenantSubscriptionRepository).
  async function fetchSubscriptions(params) {
    const { data } = await getAllsubscriptionsApi(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── UI helpers
  const PLAN_UI = {
    free: { color: 'grey', icon: 'mdi-gift-outline' },
    starter: { color: 'blue', icon: 'mdi-star-half-full' },
    pro: { color: 'primary', icon: 'mdi-star' },
    enterprise: { color: 'warning', icon: 'mdi-crown' }
  }
  const planColor = code => PLAN_UI[code]?.color ?? 'grey'
  const statusColor = s =>
    ({
      active: 'success',
      trial: 'info',
      cancelled: 'error',
      suspended: 'warning'
    })[s] ?? 'default'
  const statusLabel = s =>
    ({
      active: t('status.active'),
      trial: t('subscription.status.trial'),
      cancelled: t('status.cancelled'),
      suspended: t('subscription.status.suspended')
    })[s] ?? s

  // No 'expired' status exists in the DB — an overdue subscription is just
  // 'active' with a past current_period_end. Detected here for display only.
  const isExpired = item =>
    item.status === 'active' &&
    !!item.current_period_end &&
    new Date(item.current_period_end) < new Date()

  const daysUntil = date => {
    if (!date) return null
    return Math.ceil((new Date(date) - new Date()) / 864e5)
  }

  // ── Open Change Plan dialog for an existing subscription
  const openAssign = item => {
    assigningTenant.value = item
    assignDialog.value = true
  }

  const viewHistory = item => {
    router.push({ name: 'subscription-history', params: { tenantId: item.tenant_id }, query: { tenantName: item.tenant?.name } })
  }

  // Active/trial subscriptions can't be hard-deleted (invoices/history are
  // linked to them) — cancel instead; only a cancelled row can be purged.
  const deleteSub = item => {
    const isLive = ['active', 'trial'].includes(item.status)

    confirm({
      title: isLive
        ? t('subscription.list.confirm_cancel.title')
        : t('subscription.list.confirm_delete.title'),
      message: isLive
        ? `${t('subscription.list.confirm_cancel.alert_before')} ${item.tenant?.name} ${t('subscription.list.confirm_cancel.alert_after')}`
        : t('subscription.list.confirm_delete.message', { name: item.tenant?.name }),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        try {
          if (isLive) {
            await subscriptionStore.cancelSubscription(item.id)
            notif(t('messages.cancelled_success'), { type: 'success' })
          } else {
            await subscriptionStore.deleteSubscription(item.id)
            notif(t('messages.deleted_success'), { type: 'success' })
          }
          tableRef.value?.refresh()
        } catch (err) {
          notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
        }
      }
    })
  }

  const toggleSub = async item => {
    try {
      await subscriptionStore.toggleActive(item.id)
      tableRef.value?.refresh()
    } catch (err) {
      notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
    }
  }

  const renewSub = async item => {
    renewingId.value = item.id
    try {
      await subscriptionStore.renewSubscription(item.id)
      notif(t('messages.updated_success'), { type: 'success' })
      tableRef.value?.refresh()
    } catch (err) {
      notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
    } finally {
      renewingId.value = null
    }
  }
</script>
