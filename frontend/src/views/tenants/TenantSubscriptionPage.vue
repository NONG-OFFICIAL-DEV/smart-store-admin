<template>
  <v-container fluid class="pa-0">
    <AppPageHeader
      :title="$t('subscription.manage_dialog.title', { name: tenantName || '' })"
      show-back
      :breadcrumbs="[
        { title: $t('menu.tenant'), to: '/tenants' },
        { title: tenantName || $t('tenant_details.detail_fallback'), to: `/tenants/${tenantId}` },
        { title: $t('subscription.manage_subscription') }
      ]"
    />

    <div v-if="loading" class="d-flex justify-center py-12">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else>
      <!-- ── Current plan overview ── -->
      <v-card rounded="xl" border elevation="0" class="pa-5 mb-4">
        <div class="d-flex align-center justify-space-between">
          <div>
            <div class="text-h6">{{ plan?.name ?? $t('subscription.no_active_plan') }}</div>
            <div v-if="subscription" class="text-body-2 text-medium-emphasis">
              ${{ cyclePrice }} / {{ activeBillingCycle?.label ?? '—' }}
            </div>
          </div>
          <v-chip v-if="subscription" size="small" variant="flat" :color="statusColor(subscription.status)">
            {{ statusLabel(subscription.status) }}
          </v-chip>
        </div>

        <div v-if="subscription" class="d-flex flex-wrap ga-4 mt-3 text-caption text-medium-emphasis">
          <span>{{ $t('subscription.list.field.period_end') }}: {{ subscription.current_period_end ? formatDate(subscription.current_period_end) : '—' }}</span>
          <span v-if="subscription.trial_ends_at">{{ $t('subscription.status.trial') }}: {{ formatDate(subscription.trial_ends_at) }}</span>
        </div>

        <!-- ── Lifecycle actions ── -->
        <div v-if="subscription" class="d-flex flex-wrap ga-2 mt-4">
          <v-btn
            v-if="subscription.status === 'active'"
            size="small"
            variant="outlined"
            color="info"
            prepend-icon="mdi-autorenew"
            :loading="actionLoading === 'renew'"
            @click="renew"
          >
            {{ $t('subscription.list.action.renew') }}
          </v-btn>
          <v-btn
            v-if="subscription.status !== 'cancelled'"
            size="small"
            variant="outlined"
            :color="subscription.status === 'active' ? 'warning' : 'success'"
            :prepend-icon="subscription.status === 'active' ? 'mdi-pause-circle-outline' : 'mdi-play-circle-outline'"
            :loading="actionLoading === 'toggle'"
            @click="toggle"
          >
            {{ subscription.status === 'active' ? $t('subscription.list.action.pause') : $t('subscription.list.action.resume') }}
          </v-btn>
          <v-btn
            size="small"
            variant="outlined"
            color="error"
            prepend-icon="mdi-delete-outline"
            :loading="actionLoading === 'delete'"
            @click="deleteOrCancel"
          >
            {{ ['active', 'trial'].includes(subscription.status) ? $t('subscription.list.action.cancel') : $t('subscription.list.action.delete') }}
          </v-btn>
        </div>
      </v-card>

      <v-row dense>
        <!-- ── LEFT: actions ── -->
        <v-col cols="12" md="5">
          <!-- Change / assign plan -->
          <v-card rounded="xl" border elevation="0" class="pa-5 mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-3">
              {{ subscription ? $t('subscription.list.change_plan') : $t('subscription.list.assign_plan') }}
            </div>
            <v-select
              v-model="planForm.plan_id"
              :items="planStore.plans"
              item-title="name"
              item-value="id"
              :label="$t('subscription.list.field.plan')"
              variant="outlined"
              rounded="lg"
              class="mb-2"
            />
            <v-select
              v-model="planForm.billing_cycle_id"
              :items="availableCycles"
              item-title="label"
              item-value="id"
              :label="$t('subscription.list.field.billing_cycle')"
              variant="outlined"
              rounded="lg"
              :disabled="!planForm.plan_id"
            />
            <v-btn
              color="primary"
              variant="tonal"
              rounded="lg"
              :loading="actionLoading === 'change_plan'"
              :disabled="!planForm.plan_id || !planForm.billing_cycle_id"
              @click="changePlan"
            >
              {{ $t('btn.save_changes') }}
            </v-btn>
          </v-card>

          <!-- Record payment -->
          <v-card rounded="xl" border elevation="0" class="pa-5 mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-3">
              {{ $t('subscription.payment_dialog.title') }}
            </div>
            <v-row dense>
              <v-col cols="8">
                <v-text-field
                  v-model="paymentForm.amount_usd"
                  type="number"
                  min="0"
                  step="0.01"
                  :label="$t('subscription.payment_dialog.amount')"
                  variant="outlined"
                  rounded="lg"
                />
              </v-col>
              <v-col cols="4">
                <v-select
                  v-model="paymentForm.currency"
                  :items="['USD', 'KHR']"
                  :label="$t('subscription.payment_dialog.currency')"
                  variant="outlined"
                  rounded="lg"
                />
              </v-col>
            </v-row>
            <v-text-field
              v-model="paymentForm.paid_at"
              type="date"
              :label="$t('subscription.payment_dialog.paid_at')"
              variant="outlined"
              rounded="lg"
              class="mb-2"
            />
            <v-textarea
              v-model="paymentForm.note"
              :label="$t('subscription.payment_dialog.note')"
              rows="2"
              auto-grow
              variant="outlined"
              rounded="lg"
            />
            <v-btn
              color="success"
              variant="tonal"
              rounded="lg"
              :loading="actionLoading === 'record_payment'"
              :disabled="!paymentForm.amount_usd || !subscription"
              @click="recordPayment"
            >
              {{ $t('subscription.payment_dialog.record') }}
            </v-btn>
          </v-card>
        </v-col>

        <!-- ── RIGHT: history ── -->
        <v-col cols="12" md="7">
          <v-card rounded="xl" border elevation="0" class="pa-5 mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-3">
              {{ $t('subscription.history.tabs.plan_history') }}
            </div>
            <PlanHistoryTable :history="planHistory" />
          </v-card>

          <v-card rounded="xl" border elevation="0" class="pa-5">
            <div class="text-subtitle-1 font-weight-bold mb-3">
              {{ $t('subscription.history.tabs.payments') }}
            </div>
            <v-table density="compact">
              <thead>
                <tr>
                  <th>{{ $t('subscription.payments.table.invoice_number') }}</th>
                  <th>{{ $t('subscription.payments.table.amount') }}</th>
                  <th>{{ $t('subscription.payments.table.paid_at') }}</th>
                  <th>{{ $t('subscription.payments.table.note') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="invoice in invoices" :key="invoice.id">
                  <td>{{ invoice.invoice_number }}</td>
                  <td>{{ invoice.currency }} {{ invoice.amount_usd }}</td>
                  <td>{{ invoice.paid_at ? formatDate(invoice.paid_at) : '—' }}</td>
                  <td>{{ invoice.note ?? '—' }}</td>
                </tr>
                <tr v-if="!invoices.length">
                  <td colspan="4" class="text-center text-medium-emphasis py-3">
                    {{ $t('subscription.payments.empty') }}
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<script setup>
  // Consolidated "manage this tenant's subscription" page — plan status,
  // change-plan, record-payment, lifecycle actions (renew/pause/cancel),
  // and both history tables, all in one place instead of the old 3-page
  // split (Subscriptions.vue, SubscriptionHistory.vue, TenantView.vue's own
  // AssignPlanDialog). A dedicated page rather than a dialog — this is a
  // lot of content to read/act on comfortably in a modal.
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { useAppUtils } from '@nong-official-dev/core'
  import { usePlanStore } from '@/stores/planStore'
  import { useSubscriptionStore } from '@/stores/subscriptionStore'
  import { getPlanByTenantApi } from '@/api/planService'
  import { useDate } from '@/composables/useDate'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import PlanHistoryTable from '@/components/tenants/PlanHistoryTable.vue'

  const route = useRoute()
  const { t } = useI18n()
  const { notif, confirm } = useAppUtils()
  const planStore = usePlanStore()
  const subscriptionStore = useSubscriptionStore()
  const { formatShortDate: formatDate } = useDate()

  const tenantId = route.params.id
  const tenantName = route.query.tenantName

  const loading = ref(false)
  const actionLoading = ref(null)

  const subscription = ref(null)
  const plan = ref(null)
  const activeBillingCycle = ref(null)
  const invoices = ref([])
  const planHistory = ref([])

  const planForm = ref({ plan_id: null, billing_cycle_id: null })
  const defaultPaymentForm = () => ({
    amount_usd: null,
    currency: 'USD',
    paid_at: new Date().toISOString().slice(0, 10),
    note: ''
  })
  const paymentForm = ref(defaultPaymentForm())

  const availableCycles = computed(() => {
    const selected = planStore.plans.find(p => p.id === planForm.value.plan_id)
    return selected?.billing_cycles?.filter(c => c.is_active) ?? []
  })

  const cyclePrice = computed(() => {
    const base = Number(plan.value?.price_usd || 0)
    const months = activeBillingCycle.value?.months ?? 1
    const discount = Number(activeBillingCycle.value?.discount_percent || 0) / 100
    return (base * months * (1 - discount)).toFixed(2)
  })

  const statusColor = s =>
    ({ active: 'success', trial: 'info', cancelled: 'error', suspended: 'warning' })[s] ?? 'default'
  const statusLabel = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : '—'

  async function load() {
    loading.value = true
    try {
      if (!planStore.plans.length) await planStore.fetchPlans()

      const { data } = await getPlanByTenantApi(tenantId)
      subscription.value = data.data.subscription
      plan.value = data.data.plan
      activeBillingCycle.value = data.data.active_billing_cycle
      invoices.value = data.data.invoices ?? []
      planHistory.value = data.data.plan_history ?? []

      planForm.value = {
        plan_id: subscription.value?.plan?.id ?? null,
        billing_cycle_id: subscription.value?.billing_cycle_id ?? null
      }
      paymentForm.value = defaultPaymentForm()
    } finally {
      loading.value = false
    }
  }

  async function runAction(key, fn, successMessage) {
    actionLoading.value = key
    try {
      await fn()
      if (successMessage) notif(successMessage, { type: 'success' })
      await load()
    } catch (err) {
      notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
    } finally {
      actionLoading.value = null
    }
  }

  const changePlan = () => runAction('change_plan', () =>
    subscriptionStore.createSubscription({
      tenant_id: tenantId,
      plan_id: planForm.value.plan_id,
      billing_cycle_id: planForm.value.billing_cycle_id
    }), t('messages.saved_success'))

  const recordPayment = () => runAction('record_payment', () =>
    subscriptionStore.recordPayment(tenantId, paymentForm.value), t('subscription.payment_dialog.recorded_success'))

  const renew = () => runAction('renew', () => subscriptionStore.renewSubscription(subscription.value.id), t('messages.updated_success'))
  const toggle = () => runAction('toggle', () => subscriptionStore.toggleActive(subscription.value.id))

  // Active/trial subscriptions can't be hard-deleted (invoices/history are
  // linked to them) — cancel instead; only a cancelled row can be purged.
  function deleteOrCancel() {
    const isLive = ['active', 'trial'].includes(subscription.value.status)

    confirm({
      title: isLive
        ? t('subscription.list.confirm_cancel.title')
        : t('subscription.list.confirm_delete.title'),
      message: isLive
        ? `${t('subscription.list.confirm_cancel.alert_before')} ${tenantName} ${t('subscription.list.confirm_cancel.alert_after')}`
        : t('subscription.list.confirm_delete.message', { name: tenantName }),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: () => runAction(
        'delete',
        () => isLive
          ? subscriptionStore.cancelSubscription(subscription.value.id)
          : subscriptionStore.deleteSubscription(subscription.value.id),
        isLive ? t('messages.cancelled_success') : t('messages.deleted_success')
      ),
      cancel: () => {}
    })
  }

  onMounted(load)
</script>
