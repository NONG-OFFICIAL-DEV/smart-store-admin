<template>
  <AppDialog
    v-model="model"
    :max-width="900"
    :persistent="confirming"
    :title="t('subscription.upgrade_dialog.choose_plan')"
    icon="mdi-crown-outline"
    color="warning"
    :loading="confirming"
    body-class="pa-0"
  >
    <template #header-extra>
      <div class="px-6 pb-4">
        <div class="text-caption text-medium-emphasis mb-4 ms-10">
          {{ t('subscription.upgrade_dialog.currently_on') }}
          <v-chip
            size="x-small"
            :color="currentPlanMeta?.color ?? 'grey'"
            variant="tonal"
            class="mx-1 font-weight-medium"
          >
            <v-icon start size="11">{{ currentPlanMeta?.icon ?? 'mdi-circle' }}</v-icon>
            {{ currentPlan?.name ?? t('subscription.upgrade_dialog.no_plan') }}
          </v-chip>
          <span v-if="props.activeBillingCycle">· {{ props.activeBillingCycle.label }}</span>
        </div>

        <!-- Tabs -->
        <v-tabs v-model="activeTab" density="compact" color="primary">
          <v-tab value="plans">{{ t('subscription.plan.title') }}</v-tab>
          <v-tab value="billing">{{ t('subscription.upgrade_dialog.tab_billing') }}</v-tab>
        </v-tabs>
      </div>
    </template>

        <v-tabs-window v-model="activeTab">

          <!-- Plans tab -->
          <v-tabs-window-item value="plans">
            <div class="pa-5">

              <!-- Billing cycle toggle -->
              <div v-if="availableCycles.length" class="d-flex justify-center mb-5">
                <v-btn-toggle
                  v-model="selectedCycleLabel"
                  mandatory
                  rounded="xl"
                  density="compact"
                  color="primary"
                  border
                >
                  <v-btn
                    v-for="c in availableCycles"
                    :key="c.label"
                    :value="c.label"
                    size="small"
                    class="px-4"
                  >
                    {{ c.label }}
                    <v-chip
                      v-if="Number(c.discount_percent) > 0"
                      class="ms-2"
                      color="success"
                      size="x-small"
                      variant="flat"
                    >
                      -{{ Number(c.discount_percent).toFixed(0) }}%
                    </v-chip>
                  </v-btn>
                </v-btn-toggle>
              </div>

              <!-- Plan cards -->
              <v-row dense>
                <v-col
                  v-for="plan in enrichedPlans"
                  :key="plan.id"
                  cols="12"
                  sm="6"
                  md="3"
                >
                  <v-card
                    rounded="xl"
                    class="plan-card h-100"
                    :class="{
                      'plan-card--selected': isSelected(plan),
                      'plan-card--current':  isCurrentPlan(plan),
                      'plan-card--popular':  plan.popular && !isSelected(plan) && !isCurrentPlan(plan),
                      'plan-card--blocked':  isFreeDowngradeBlocked(plan),
                    }"
                    variant="outlined"
                    @click="selectPlan(plan)"
                  >
                    <!-- Popular badge -->
                    <div v-if="plan.popular" class="popular-badge">
                      <v-icon size="10" class="me-1">mdi-star</v-icon>{{ t('tenant_create.popular') }}
                    </div>

                    <v-card-text class="pa-4 d-flex flex-column" style="height:100%">

                      <!-- Icon row + check indicator -->
                      <div class="d-flex align-center justify-space-between mb-3">
                        <v-avatar :color="plan.color" variant="tonal" size="34" rounded="lg">
                          <v-icon :icon="plan.icon" size="17" />
                        </v-avatar>

                        <!-- Check icon: filled when selected, outline ring when current -->
                        <transition name="check-fade">
                          <v-icon
                            v-if="isSelected(plan)"
                            :color="plan.color"
                            size="20"
                            class="check-icon"
                          >
                            mdi-check-circle
                          </v-icon>
                          <v-icon
                            v-else-if="isCurrentPlan(plan)"
                            color="grey"
                            size="20"
                          >
                            mdi-circle-outline
                          </v-icon>
                          <v-icon
                            v-else
                            color="grey-lighten-1"
                            size="20"
                            class="select-ring"
                          >
                            mdi-circle-outline
                          </v-icon>
                        </transition>
                      </div>

                      <!-- Name -->
                      <div class="text-caption font-weight-bold mb-2">{{ plan.name }}</div>

                      <!-- Price -->
                      <template v-if="isFree(plan)">
                        <div class="text-h6 font-weight-black text-success mb-0">$0</div>
                        <div class="text-caption text-medium-emphasis mb-3" style="opacity:.8">{{ t('subscription.upgrade_dialog.free_trial') }}</div>
                      </template>
                      <template v-else>
                        <div class="text-h6 font-weight-black mb-0" :class="`text-${plan.color}`">
                          ${{ getPlanPrice(plan) }}
                        </div>
                        <div class="text-caption text-medium-emphasis mb-0">
                          / {{ planCycle(plan)?.months ?? 1 }} {{ t('tenant_create.month_unit', planCycle(plan)?.months ?? 1) }}
                        </div>
                        <div class="text-caption text-medium-emphasis mb-3" style="opacity:.65">
                          ${{ getPlanPerMonth(plan) }}{{ t('tenant_create.per_month_suffix') }}
                        </div>
                      </template>

                      <v-divider class="mb-3" />

                      <!-- Limits -->
                      <!-- <div class="d-flex flex-column ga-1 mb-2">
                        <div class="d-flex align-center ga-1 text-caption text-medium-emphasis">
                          <v-icon size="12" color="primary">mdi-account-group-outline</v-icon>
                          {{ t('subscription.plan.seats_count', plan.seats) }}
                        </div>
                        <div class="d-flex align-center ga-1 text-caption text-medium-emphasis">
                          <v-icon size="12" color="primary">mdi-database-outline</v-icon>
                          {{ plan.storage_gb }} {{ t('tenant_create.storage_suffix') }}
                        </div>
                        <div class="d-flex align-center ga-1 text-caption text-medium-emphasis">
                          <v-icon size="12" color="primary">mdi-api</v-icon>
                          {{ plan.api_limit > 0 ? plan.api_limit.toLocaleString() + ' ' + t('tenant_create.api_calls_suffix') : t('tenant_create.unlimited_api') }}
                        </div>
                      </div> -->

                      <!-- Features -->
                      <div v-if="planFeatureLines(plan).length" class="flex-grow-1">
                        <div
                          v-for="line in planFeatureLines(plan)"
                          :key="line.key"
                          class="d-flex align-start ga-1 mb-1"
                        >
                          <v-icon color="success" size="11" class="mt-1 flex-shrink-0">
                            mdi-check-circle-outline
                          </v-icon>
                          <span class="text-caption">{{ line.text }}</span>
                        </div>
                      </div>

                      <!-- Current plan label at bottom (no button) -->
                      <div
                        v-if="isCurrentPlan(plan)"
                        class="text-center text-caption text-medium-emphasis mt-3"
                        style="opacity:.6"
                      >
                        {{ t('subscription.upgrade_dialog.current_plan_label') }}
                      </div>

                      <!-- Blocked free-downgrade explanation -->
                      <div
                        v-if="isFreeDowngradeBlocked(plan)"
                        class="text-center text-caption text-medium-emphasis mt-3"
                        style="opacity:.7"
                      >
                        <v-icon size="12" class="me-1">mdi-lock-outline</v-icon>{{ t('subscription.upgrade_dialog.free_downgrade_blocked') }}
                      </div>

                    </v-card-text>
                  </v-card>
                </v-col>
              </v-row>

              <!-- Summary bar -->
              <v-expand-transition>
                <v-sheet
                  v-if="selected && !isCurrentPlan(selected)"
                  rounded="xl"
                  class="summary-bar pa-4 mt-4"
                >
                  <div class="d-flex align-center justify-space-between flex-wrap ga-2">
                    <div class="d-flex align-center ga-3">
                      <v-avatar size="34" rounded="lg" :color="selected.color" variant="tonal">
                        <v-icon :icon="selected.icon" size="17" />
                      </v-avatar>
                      <div>
                        <div class="text-body-2 font-weight-medium d-flex align-center ga-1 flex-wrap">
                          <span class="text-medium-emphasis text-caption">{{ currentPlan?.name ?? '—' }}</span>
                          <v-icon size="12" color="medium-emphasis">mdi-arrow-right</v-icon>
                          <span :class="`text-${selected.color}`" class="text-caption font-weight-bold">{{ selected.name }}</span>
                          <v-chip size="x-small" :color="selected.color" variant="tonal" class="ms-1">
                            {{ selectedPlanCycle?.label }}
                          </v-chip>
                        </div>
                        <div class="text-caption text-medium-emphasis" style="opacity:.7">
                          {{ t('subscription.upgrade_dialog.effective_immediately') }}
                        </div>
                      </div>
                    </div>
                    <div class="text-right">
                      <div class="text-subtitle-1 font-weight-bold" :class="`text-${selected.color}`">
                        ${{ getPlanPrice(selected) }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        / {{ selectedPlanCycle?.months ?? 1 }} {{ t('tenant_create.month_unit', selectedPlanCycle?.months ?? 1) }}
                      </div>
                    </div>
                  </div>
                </v-sheet>
              </v-expand-transition>
            </div>
          </v-tabs-window-item>

          <!-- Billing tab -->
          <v-tabs-window-item value="billing">
            <div class="pa-5">
              <div class="text-caption text-medium-emphasis font-weight-medium mb-2 text-uppercase" style="letter-spacing:.05em">
                {{ t('subscription.upgrade_dialog.active_subscription') }}
              </div>
              <v-sheet rounded="xl" class="summary-bar pa-4 mb-4">
                <div class="d-flex align-center justify-space-between">
                  <div class="d-flex align-center ga-3">
                    <v-avatar size="34" rounded="lg" :color="currentPlanMeta?.color ?? 'grey'" variant="tonal">
                      <v-icon :icon="currentPlanMeta?.icon ?? 'mdi-circle'" size="17" />
                    </v-avatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">{{ currentPlan?.name ?? t('subscription.upgrade_dialog.no_plan') }}</div>
                      <div class="text-caption text-medium-emphasis">{{ props.activeBillingCycle?.label ?? '—' }}</div>
                    </div>
                  </div>
                  <v-chip v-if="props.subscription?.status" size="x-small" :color="statusColor" variant="tonal">
                    {{ props.subscription.status.toUpperCase() }}
                  </v-chip>
                </div>
              </v-sheet>

              <div class="text-caption text-medium-emphasis font-weight-medium mb-2 text-uppercase" style="letter-spacing:.05em">
                {{ t('subscription.upgrade_dialog.billing_details') }}
              </div>
              <v-list density="compact" rounded="xl" class="billing-list">
                <v-list-item
                  prepend-icon="mdi-calendar-outline"
                  :title="t('subscription.upgrade_dialog.next_charge')"
                  :subtitle="nextChargeDate ? `${nextChargeDate} · ${nextChargeAmount}` : '—'"
                />
                <v-divider inset />
                <v-list-item prepend-icon="mdi-credit-card-outline" :title="t('form.payment_method')" :subtitle="t('subscription.upgrade_dialog.not_set')" />
                <v-divider inset />
                <v-list-item
                  prepend-icon="mdi-receipt-outline"
                  :title="t('subscription.upgrade_dialog.billing_history')"
                  :subtitle="latestInvoice
                    ? `${latestInvoice.invoice_number} · ${formatCurrency(latestInvoice.amount_usd)} · ${latestInvoice.status}`
                    : t('subscription.upgrade_dialog.no_invoices_yet')"
                />
              </v-list>

              <div class="d-flex align-center justify-center ga-1 mt-4 text-caption text-medium-emphasis" style="opacity:.6">
                <v-icon size="13">mdi-lock-outline</v-icon>
                {{ t('subscription.upgrade_dialog.payments_secured') }}
              </div>
            </div>
          </v-tabs-window-item>

        </v-tabs-window>

    <template #actions>
      <v-btn variant="text" rounded="lg" size="small" :disabled="confirming" @click="model = false">
        {{ t('btn.cancel') }}
      </v-btn>
      <v-spacer />
      <v-btn
        v-if="activeTab === 'billing'"
        color="primary"
        variant="tonal"
        size="small"
        rounded="lg"
        prepend-icon="mdi-swap-horizontal"
        @click="activeTab = 'plans'"
      >
        {{ t('subscription.upgrade_dialog.change_plan') }}
      </v-btn>
      <v-btn
        v-else
        :color="selected ? selected.color : 'primary'"
        variant="flat"
        rounded="lg"
        class="px-6 font-weight-bold"
        :loading="confirming"
        :disabled="!selected || isCurrentPlan(selected)"
        prepend-icon="mdi-crown-outline"
        @click="confirm"
      >
        {{ confirmLabel }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePlanStore }   from '@/stores/planStore'
import { useAuthStore }   from '@/stores/authStore'
import { useAppUtils }    from '@/composables/useAppUtils'
import { formatCurrency, formatDateText } from '@nong-official-dev/core'
import AppDialog from '@/components/common/AppDialog.vue'

const { locale, t } = useI18n()

const props = defineProps({
  modelValue:         { type: Boolean, required: true },
  currentPlan:        { type: Object,  default: () => ({}) },
  activeBillingCycle: { type: Object,  default: null },
  subscription:       { type: Object,  default: () => ({}) },
  invoices:           { type: Array,   default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'upgraded'])

const planStore   = usePlanStore()
const authStore   = useAuthStore()
const { notif }   = useAppUtils()

const model = computed({
  get: () => props.modelValue,
  set: v  => emit('update:modelValue', v),
})

const activeTab          = ref('plans')
const selected           = ref(null)
// Stores the LABEL (e.g. "Monthly"), never a raw id — each plan has its own
// billing_cycle row per label, so the actual id to use is always resolved
// per-plan via cycleForPlan()/planCycle() below. Keying on id directly (the
// previous approach) meant switching plans could keep an id that belonged
// to a completely different plan.
const selectedCycleLabel = ref(null)
const confirming         = ref(false)

const PLAN_UI_MAP = {
  free:       { icon: 'mdi-gift-outline',   color: 'grey'    },
  starter:    { icon: 'mdi-star-half-full', color: 'blue'    },
  pro:        { icon: 'mdi-star',           color: 'primary' },
  enterprise: { icon: 'mdi-crown',          color: 'warning' },
}

const enrichedPlans = computed(() =>
  (planStore.plans || []).map(p => ({
    ...p,
    icon:    PLAN_UI_MAP[p.code]?.icon  ?? 'mdi-help-circle-outline',
    color:   PLAN_UI_MAP[p.code]?.color ?? 'grey',
    popular: p.code === 'pro',
  }))
)

const currentPlanMeta = computed(() => PLAN_UI_MAP[props.currentPlan?.code] ?? null)

// ── "Billing" tab — reflects the REAL active subscription, never the
// picker's in-progress selection (that's selectedCycleLabel/selectedPlanCycle,
// used only for the "Plans" tab). ──────────────────────────────────────────
const statusColor = computed(() => {
  switch (props.subscription?.status) {
    case 'active':    return 'success'
    case 'trial':     return 'warning'
    case 'suspended': return 'error'
    case 'cancelled': return 'grey'
    default:          return 'grey'
  }
})

const nextChargeDate = computed(() => {
  const sub = props.subscription ?? {}
  const date = sub.status === 'trial' ? sub.trial_ends_at : sub.current_period_end
  return date ? formatDateText(date) : null
})

const nextChargeAmount = computed(() => {
  const base     = Number(props.currentPlan?.price_usd || 0)
  const months   = props.activeBillingCycle?.months ?? 1
  const discount = Number(props.activeBillingCycle?.discount_percent || 0) / 100
  return formatCurrency((base * months * (1 - discount)).toFixed(2))
})

const latestInvoice = computed(() => props.invoices?.[0] ?? null)

// Distinct cycle LABELS across all plans, purely to populate the toggle UI
// (each button just needs a label + a badge). Which plan's actual row backs
// a given label is resolved separately, per-plan, below — never assumed
// from this list.
const availableCycles = computed(() => {
  const seen = new Set()
  return enrichedPlans.value
    .flatMap(p => p.billing_cycles?.filter(c => c.is_active) ?? [])
    .filter(c => {
      if (seen.has(c.label)) return false
      seen.add(c.label)
      return true
    })
})

// The billing-cycle row that ACTUALLY belongs to `plan` for the currently
// selected cadence label — falls back to the plan's first active cycle if
// it doesn't offer that label at all. This is the single source of truth
// for both pricing display and the id submitted to the backend, so a plan
// switch can never end up carrying another plan's billing_cycle_id.
function cycleForPlan(plan, label) {
  const cycles = (plan?.billing_cycles || []).filter(c => c.is_active)
  return cycles.find(c => c.label === label) ?? cycles[0] ?? null
}

function planCycle(plan) {
  return cycleForPlan(plan, selectedCycleLabel.value)
}

// The cycle actually being purchased — i.e. the TARGET (selected) plan's
// own matching row, not the currently active plan's.
const selectedPlanCycle = computed(() => planCycle(selected.value))

// Reset state when dialog opens
watch(() => props.modelValue, open => {
  if (!open) return
  selected.value  = null
  activeTab.value = 'plans'
  selectedCycleLabel.value = props.activeBillingCycle?.label ?? availableCycles.value[0]?.label ?? null
})

// Initialise cycle on mount
onMounted(async () => {
  await planStore.fetchAvailablePlans()
  selectedCycleLabel.value = props.activeBillingCycle?.label ?? availableCycles.value[0]?.label ?? null
})

const isFree = plan => parseFloat(plan.price_usd || 0) === 0

const getPlanPrice = plan => {
  const base     = Number(plan.price_usd || 0)
  const cycle    = planCycle(plan)
  const months   = cycle?.months ?? 1
  const discount = Number(cycle?.discount_percent || 0) / 100
  return (base * months * (1 - discount)).toFixed(2)
}

const getPlanPerMonth = plan => {
  const base     = Number(plan.price_usd || 0)
  const discount = Number(planCycle(plan)?.discount_percent || 0) / 100
  return (base * (1 - discount)).toFixed(2)
}

// Catalog-joined feature list — boolean-type entries show their label
// only when true; text-type entries show the plan's own value text and
// are skipped if no value has been set yet.
const planFeatureLines = plan =>
  (plan.feature_list ?? [])
    .filter(f => (f.value_type === 'boolean' ? f.value : f.value?.en))
    .map(f => ({
      key: f.key,
      text: f.value_type === 'boolean' ? f.label?.[locale.value] || f.label?.en : f.value?.[locale.value] || f.value?.en
    }))

const isCurrentPlan = plan => plan.id === props.currentPlan?.id
const isSelected    = plan => selected.value?.id === plan.id

// Mirrors the backend guard in TenantSubscriptionService::changePlan() —
// self-service can't select Free once the tenant currently holds a paid
// plan. The backend is the source of truth (it also blocks this after a
// cancel/suspend, which this simple currentPlan check can't see), this is
// just so the dialog doesn't let the user pick it and then show a
// server error.
const isFreeDowngradeBlocked = plan =>
  isFree(plan) && !isCurrentPlan(plan) && Number(props.currentPlan?.price_usd ?? 0) > 0

const selectPlan = plan => {
  if (isCurrentPlan(plan) || isFreeDowngradeBlocked(plan)) return
  selected.value = plan
}

const confirmLabel = computed(() => {
  if (!selected.value) return t('subscription.upgrade_dialog.select_a_plan')

  const currentPrice = Number(props.currentPlan?.price_usd ?? 0)
  const selectedPrice = Number(selected.value.price_usd ?? 0)

  if (selectedPrice > currentPrice) {
    return t('subscription.upgrade_dialog.upgrade_to', { name: selected.value.name })
  }
  if (selectedPrice < currentPrice) {
    return t('subscription.upgrade_dialog.downgrade_to', { name: selected.value.name })
  }
  return t('subscription.upgrade_dialog.switch_to', { name: selected.value.name })
})

async function confirm() {
  if (!selected.value || isCurrentPlan(selected.value)) return
  confirming.value = true
  try {
    await planStore.changePlan({
      plan_id:           selected.value.id,
      billing_cycle_id:  selectedPlanCycle.value?.id,
    })
    // Plan/subscription/invoices shown by this dialog and the Billing page
    // both come from planStore.plan (fetchPlansByTenant) — NOT tenantStore —
    // so that's what must be refreshed for the change to actually show up.
    await planStore.fetchPlansByTenant(authStore.tenant_id)
    emit('upgraded', { plan: selected.value, cycle: selectedPlanCycle.value })
    model.value = false
  } catch (err) {
    notif(err?.response?.data?.message || t('subscription.upgrade_dialog.change_failed'), { type: 'error' })
  } finally {
    confirming.value = false
  }
}
</script>

<style scoped>
.upgrade-dialog {
  overflow: hidden;
}

.upgrade-header {
  background: rgb(var(--v-theme-surface));
}

/* ── Plan cards ── */
.plan-card {
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
  position: relative;
  overflow: visible !important;
  border-color: rgba(var(--v-border-color), 0.16) !important;
}

.plan-card:hover:not(.plan-card--current):not(.plan-card--blocked) {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07) !important;
}

.plan-card--selected {
  border-width: 2px !important;
  border-color: rgb(var(--v-theme-primary)) !important;
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.08) !important;
}

.plan-card--popular:not(.plan-card--selected) {
  border-color: rgba(var(--v-theme-primary), 0.28) !important;
}

.plan-card--current {
  cursor: default;
  opacity: 0.52;
}

.plan-card--blocked {
  cursor: not-allowed;
  opacity: 0.52;
}

.plan-card--blocked:hover {
  transform: none;
  box-shadow: none !important;
}

/* ── Check icon animation ── */
.check-icon {
  animation: pop-in 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes pop-in {
  from { transform: scale(0.5); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}

.check-fade-enter-active { transition: opacity 0.15s, transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1); }
.check-fade-leave-active { transition: opacity 0.1s; }
.check-fade-enter-from   { opacity: 0; transform: scale(0.5); }
.check-fade-leave-to     { opacity: 0; }

/* Hover ring hint on unselected cards */
.plan-card:not(.plan-card--current):not(.plan-card--selected):hover .select-ring {
  color: rgba(var(--v-theme-primary), 0.5) !important;
}

/* ── Popular badge ── */
.popular-badge {
  position: absolute;
  top: -11px;
  left: 50%;
  transform: translateX(-50%);
  background: rgb(var(--v-theme-primary));
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.05em;
  padding: 2px 10px;
  border-radius: 20px;
  white-space: nowrap;
  display: flex;
  align-items: center;
  z-index: 2;
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.35);
}

/* ── Summary bar ── */
.summary-bar {
  background: rgba(var(--v-theme-surface-variant), 0.05);
  border: 1px solid rgba(var(--v-border-color), 0.14);
}

/* ── Billing list ── */
.billing-list {
  border: 1px solid rgba(var(--v-border-color), 0.14) !important;
  background: rgba(var(--v-theme-surface-variant), 0.04) !important;
}

.h-100 { height: 100%; }
</style>