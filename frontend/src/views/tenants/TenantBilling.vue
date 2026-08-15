<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-credit-card"
      :title="$t('billing.title')"
      :subtitle="$t('billing.subtitle')"
    ></custom-title>
    <!-- Loading -->
    <template v-if="tenantStore.loading">
      <v-skeleton-loader type="card" class="mb-4" />
      <v-skeleton-loader type="card" />
    </template>

    <!-- Error -->
    <v-alert
      v-else-if="tenantStore.error"
      type="error"
      variant="tonal"
      rounded="lg"
      class="mb-4"
    >
      {{ tenantStore.error }}
    </v-alert>

    <!-- Main Content -->
    <template v-else>
      <v-card rounded="lg">
        <v-tabs v-model="tab" color="indigo" align-tabs="start" class="px-4">
          <v-tab value="plan">{{ $t('billing.subscription') }}</v-tab>
          <v-tab value="billing">{{ $t('billing.billing_summary') }}</v-tab>
          <v-tab value="invoices">{{ $t('billing.invoice_history') }}</v-tab>
          <v-tab value="history">{{ $t('billing.plan_history') }}</v-tab>
        </v-tabs>

        <v-divider />

        <!-- TAB 1: Current Plan -->
        <v-window v-model="tab">
          <v-window-item value="plan">
            <v-card-text>
              <PlanOverviewCard
                :plan="plan"
                :subscription="subscription"
                :active-billing-cycle="activeBillingCycle"
                :billing-cycles="billingCycles"
                @upgrade="upgradeDialog = true"
                @pay="handlePay"
                @renew="handleRenew"
              />
            </v-card-text>
          </v-window-item>

          <!-- TAB 2: Billing Summary -->
          <v-window-item value="billing">
            <v-card-text>
              <BillingSummaryCards
                :billing="tenant.billing"
                :subscription="subscription"
                :plan="plan"
                :active-billing-cycle="activeBillingCycle"
              />
            </v-card-text>
          </v-window-item>

          <!-- TAB 3: Invoices -->
          <v-window-item value="invoices">
            <v-card-text class="pa-0">
              <InvoiceTable
                :invoices="invoices"
                :currency="authStore.currency ?? 'USD'"
                @pay="inv => generateQR(inv.payment_method ?? 'aba', inv)"
              />
            </v-card-text>
          </v-window-item>

          <!-- TAB 4: Plan History -->
          <v-window-item value="history">
            <v-card-text class="pa-0">
              <PlanHistoryTable :history="plan_history" />
            </v-card-text>
          </v-window-item>
        </v-window>
      </v-card>
    </template>

    <!-- Dialogs -->
    <QRPaymentDialog
      v-model="qrDialog"
      :method="qrMethod"
      :image-url="qrImageUrl"
      :loading="qrLoading"
      :amount="qrAmount"
      :currency="authStore.currency ?? 'USD'"
      :invoice-ref="qrRef"
      :plan-name="plan.name ?? $t('billing.default_plan_name')"
      @close="closeQR"
      @refresh="generateQR(qrMethod, currentInvoice)"
      @confirm="checkPaymentStatus"
    />

    <UpgradeDialog
      v-model="upgradeDialog"
      :current-plan="plan"
      :active-billing-cycle="activeBillingCycle"
      :subscription="subscription"
      :invoices="invoices"
      @upgraded="onUpgraded"
    />

  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTenantStore } from '../../stores/tenantStore'
  import { useAuthStore } from '../../stores/authStore'
  import { usePlanStore } from '../../stores/planStore'
  import { useAppUtils } from '@/composables/useAppUtils'

  import PlanOverviewCard from '@/components/tenants/PlanOverviewCard.vue'
  import BillingSummaryCards from '@/components/tenants/BillingSummaryCards.vue'
  import InvoiceTable from '@/components/tenants/InvoiceTable.vue'
  import PlanHistoryTable from '@/components/tenants/PlanHistoryTable.vue'
  import QRPaymentDialog from '@/components/tenants/QRPaymentDialog.vue'
  import UpgradeDialog from '@/components/tenants/UpgradeDialog.vue'

  const tenantStore = useTenantStore()
  const authStore = useAuthStore()
  const planStore = usePlanStore()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  const tab = ref('plan')

  onMounted(() => {
    planStore.fetchPlansByTenant(authStore.tenant_id)
  })

  // Computed Data
  const tenant = computed(() => tenantStore.tenant ?? {})
  const tenantPlanData = computed(() => planStore.plan ?? {})

  const plan = computed(() => tenantPlanData.value.plan ?? {})
  const invoices = computed(() => tenantPlanData.value.invoices ?? [])
  const plan_history = computed(() => tenantPlanData.value.plan_history ?? [])
  const subscription = computed(() => tenantPlanData.value.subscription ?? {})
  const activeBillingCycle = computed(
    () => tenantPlanData.value.active_billing_cycle ?? null
  )
  const billingCycles = computed(
    () => tenantPlanData.value.billing_cycles ?? []
  )
  // QR Payment State
  const qrDialog = ref(false)
  const qrLoading = ref(false)
  const qrImageUrl = ref(null)
  const qrMethod = ref('aba')
  const qrAmount = ref(0)
  const qrRef = ref(null)
  const generating = ref(null)
  const currentInvoice = ref(null)
  const upgradeDialog = ref(false)

  async function handlePay(method) {
    qrMethod.value = method
    await generateQR(method, currentInvoice.value)
    qrDialog.value = true
  }

  async function generateQR(method, invoice = null) {
    qrMethod.value = method
    qrAmount.value = invoice?.amount ?? plan.value.price_usd ?? 0
    qrRef.value = invoice?.id ?? null
    currentInvoice.value = invoice

    generating.value = method
    qrLoading.value = true
    qrImageUrl.value = null
    qrDialog.value = true

    try {
      // const { data } = await tenantStore.generatePaymentQR({
      //   method,
      //   amount: qrAmount.value,
      //   currency: authStore.currency ?? 'USD',
      //   invoice_id: invoice?.id ?? null,
      //   plan_id: plan.value.id ?? null,
      //   tenant_id: authStore.tenant_id
      // })
      // qrImageUrl.value = data.qr_image
    } finally {
      qrLoading.value = false
      generating.value = null
    }
  }

  async function checkPaymentStatus() {
    const paid = await tenantStore.verifyPayment({
      method: qrMethod.value,
      tenant_id: authStore.tenant_id,
      invoice_id: currentInvoice.value?.id ?? null
    })

    if (paid) {
      closeQR()
      notif(t('billing.messages.payment_confirmed'), { type: 'success' })
      tenantStore.fetchTenantById(authStore.tenant_id)
    }
  }

  function closeQR() {
    qrDialog.value = false
    qrImageUrl.value = null
  }

  function onUpgraded() {
    notif(t('subscription.upgrade_dialog.change_success'), { type: 'success' })
    // This view's plan/subscription/invoices all come from planStore.plan
    // (fetchPlansByTenant), not tenantStore — refresh the store that's
    // actually rendered, otherwise the page keeps showing the old plan.
    planStore.fetchPlansByTenant(authStore.tenant_id)
  }

  function handleRenew() {
    confirm({
      title: t('subscription.upgrade_dialog.renew_confirm_title'),
      message: t('subscription.upgrade_dialog.renew_confirm_message'),
      options: { type: 'warning', width: 500 },
      agree: async () => {
        try {
          await planStore.renewSubscription()
          await planStore.fetchPlansByTenant(authStore.tenant_id)
          notif(t('subscription.upgrade_dialog.renew_success'), { type: 'success' })
        } catch (err) {
          notif(err?.response?.data?.message || t('subscription.upgrade_dialog.renew_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }
</script>
