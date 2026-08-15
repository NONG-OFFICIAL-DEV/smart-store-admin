<template>
  <v-container fluid class="pa-0" v-if="tenantData">
    <AppPageHeader
      :title="tenantData.tenant.name"
      show-back
      :breadcrumbs="[
        { title: $t('menu.tenant'), to: '/tenants' },
        { title: tenantData.tenant.name ?? $t('tenant_details.detail_fallback') }
      ]"
    >
      <template #title-after>
        <v-chip
          :color="tenantData.tenant.is_active ? 'success' : 'error'"
          size="x-small"
          variant="flat"
        >
          {{ tenantData.tenant.is_active ? $t('status.active') : $t('tenant_details.status.suspended') }}
        </v-chip>
      </template>

      <template #right>
        <v-btn
          prepend-icon="mdi-pencil"
          rounded="lg"
          variant="flat"
          color="primary"
          :to="{ name: 'tenant-edit', params: { id: tenantData.tenant.id } }"
        >
          {{ $t('tenant_details.edit_tenant') }}
        </v-btn>
      </template>
    </AppPageHeader>

    <!-- ── Identity banner ── -->
    <v-card rounded="xl" border elevation="0" class="mb-4 overflow-hidden">
      <div
        class="pa-5"
        :style="{
          background: `linear-gradient(135deg, ${tenant.primary_color ?? '#6366f1'}14 0%, ${tenant.primary_color ?? '#6366f1'}06 100%)`,
          borderBottom: `2px solid ${tenant.primary_color ?? '#6366f1'}22`
        }"
      >
        <div class="d-flex align-center">
          <v-avatar
            size="60"
            rounded="xl"
            :color="tenant.primary_color ?? 'primary'"
            class="me-4"
            style="box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12)"
          >
            <span v-if="!tenant.logo_url" class="text-h5">
              {{
                tenant.business_type?.icon ?? tenant.name?.[0]?.toUpperCase()
              }}
            </span>
            <v-img v-else :src="tenant.logo_url" cover />
          </v-avatar>

          <div class="flex-grow-1">
            <div class="text-h6 font-weight-bold">{{ tenant.name }}</div>
            <div class="text-body-2 text-medium-emphasis">
              {{ tenant.slug }}.app.com
            </div>
            <div class="d-flex align-center flex-wrap ga-1 mt-1">
              <v-chip size="x-small" variant="tonal" color="primary">
                {{ tenant.business_type?.name }}
              </v-chip>
              <v-chip size="x-small" variant="tonal" :color="planColor">
                {{ plan?.name ?? $t('tenant_details.no_plan') }}
              </v-chip>
              <v-chip
                size="x-small"
                :color="subscriptionStatusColor"
                variant="flat"
              >
                {{ subscriptionStatusLabel.toUpperCase() }}
              </v-chip>
            </div>
          </div>

          <!-- Quick stats inline -->
          <div class="d-none d-sm-flex ga-3">
            <div class="text-center">
              <div class="text-body-2 font-weight-bold">
                {{ tenant.branches?.length ?? 0 }}
              </div>
              <div class="text-caption text-medium-emphasis">{{ $t('tenant_profile.kpi.branches') }}</div>
            </div>
            <v-divider vertical class="mx-1" />
            <div class="text-center">
              <div class="text-body-2 font-weight-bold">
                {{ plan?.seats ?? '—' }}
              </div>
              <div class="text-caption text-medium-emphasis">{{ $t('tenant_details.seats_label') }}</div>
            </div>
            <v-divider vertical class="mx-1" />
            <div class="text-center">
              <div class="text-body-2 font-weight-bold">
                {{ plan?.storage_gb ?? '—' }}GB
              </div>
              <div class="text-caption text-medium-emphasis">{{ $t('tenant_profile.kpi.storage') }}</div>
            </div>
          </div>
        </div>
      </div>
    </v-card>

    <v-row>
      <!-- ── LEFT: tabs ── -->
      <v-col cols="12" md="8">
        <v-card rounded="xl" border elevation="0">
          <v-tabs v-model="tab" color="primary" density="comfortable">
            <v-tab value="overview">
              <v-icon size="15" class="mr-1">mdi-information-outline</v-icon>
              {{ $t('order_report.tabs.overview') }}
            </v-tab>
            <v-tab value="subscription">
              <v-icon size="15" class="mr-1">mdi-crown-outline</v-icon>
              {{ $t('tenant_details.tabs.subscription') }}
            </v-tab>
            <v-tab value="branches">
              <v-icon size="15" class="mr-1">mdi-store-outline</v-icon>
              {{ $t('tenant_profile.kpi.branches') }}
              <v-badge
                v-if="tenant.branches?.length"
                :content="tenant.branches.length"
                color="primary"
                inline
                class="ml-1"
              />
            </v-tab>
            <v-tab value="history">
              <v-icon size="15" class="mr-1">mdi-history</v-icon>
              {{ $t('tenant_details.tabs.history') }}
              <v-badge
                v-if="planHistory?.length"
                :content="planHistory.length"
                color="grey"
                inline
                class="ml-1"
              />
            </v-tab>
          </v-tabs>

          <v-divider />

          <v-window v-model="tab" class="pa-5">
            <!-- ══ OVERVIEW ══ -->
            <v-window-item value="overview">
              <div class="section-label mb-3">{{ $t('tenant_details.general') }}</div>
              <v-row dense class="mb-4">
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('tenant_profile.identity.tenantId') }}</div>
                    <div
                      class="info-tile-value text-truncate"
                      style="font-family: monospace; font-size: 12px"
                    >
                      {{ tenant.id }}
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('tenant_create.field.slug') }}</div>
                    <div class="info-tile-value">{{ tenant.slug }}.app.com</div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('tenant_profile.businessDetails.businessType') }}</div>
                    <div class="d-flex align-center ga-2 mt-1">
                      <v-avatar
                        size="28"
                        rounded="lg"
                        color="primary"
                        variant="tonal"
                      >
                        <v-icon size="16">
                          {{ tenant.business_type?.icon }}
                        </v-icon>
                      </v-avatar>
                      <div>
                        <div class="info-tile-value">
                          {{ tenant.business_type?.name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ tenant.business_type?.code }}
                        </div>
                      </div>
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="3">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('tenant_create.field.currency') }}</div>
                    <div class="info-tile-value">{{ tenant.currency }}</div>
                  </div>
                </v-col>
                <v-col cols="12" sm="3">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('tenant_create.field.locale') }}</div>
                    <div class="info-tile-value">{{ tenant.locale }}</div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('tenant_create.field.timezone') }}</div>
                    <div class="info-tile-value">{{ tenant.timezone }}</div>
                  </div>
                </v-col>
                <v-col cols="12" sm="3">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('tenant_create.field.brand_color') }}</div>
                    <div class="d-flex align-center ga-2 mt-1">
                      <span
                        style="
                          display: inline-block;
                          width: 18px;
                          height: 18px;
                          border-radius: 5px;
                          border: 1px solid rgba(0, 0, 0, 0.1);
                        "
                        :style="{
                          background: tenant.primary_color ?? '#6366f1'
                        }"
                      />
                      <div class="info-tile-value">
                        {{ tenant.primary_color ?? '—' }}
                      </div>
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="3">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">{{ $t('common.created_at') }}</div>
                    <div class="info-tile-value">
                      {{ formatDate(tenant.created_at) }}
                    </div>
                  </div>
                </v-col>
              </v-row>

              <v-divider class="mb-4" />
              <div class="section-label mb-3">{{ $t('tenant_create.review.owner') }}</div>

              <v-card rounded="lg" variant="outlined">
                <v-list-item class="pa-4">
                  <template #prepend>
                    <v-avatar
                      color="primary"
                      variant="tonal"
                      size="44"
                      rounded="lg"
                    >
                      <span class="text-body-1 font-weight-medium">
                        {{ ownerInitials }}
                      </span>
                    </v-avatar>
                  </template>
                  <v-list-item-title class="font-weight-medium">
                    {{ tenant.owner?.first_name }} {{ tenant.owner?.last_name }}
                  </v-list-item-title>
                  <v-list-item-subtitle>
                    <div>{{ tenant.owner?.email }}</div>
                    <div v-if="tenant.owner?.phone" class="text-caption">
                      {{ tenant.owner?.phone }}
                    </div>
                  </v-list-item-subtitle>
                </v-list-item>
              </v-card>
            </v-window-item>

            <!-- ══ SUBSCRIPTION ══ -->
            <v-window-item value="subscription">
              <!-- Trial / expiry alert -->
              <v-alert
                v-if="subscription?.status === 'trial'"
                type="info"
                variant="tonal"
                icon="mdi-clock-outline"
                rounded="lg"
                density="compact"
                class="mb-4"
              >
                {{ $t('tenant_details.trial_ends_on') }}
                <strong>{{ formatDate(subscription.trial_ends_at) }}</strong>
                ({{ daysUntilTrial }} {{ $t('tenant_details.days_remaining') }})
              </v-alert>
              <v-alert
                v-else-if="isExpiringSoon"
                type="warning"
                variant="tonal"
                icon="mdi-alert-circle-outline"
                rounded="lg"
                density="compact"
                class="mb-4"
              >
                {{ $t('tenant_details.period_ends_in') }}
                <strong>{{ daysUntilExpiry }}</strong>
                {{ $t('tenant_details.days_suffix') }}
              </v-alert>

              <!-- No subscription yet — tenant was created without a plan -->
              <v-card
                v-if="!subscription"
                rounded="lg"
                variant="outlined"
                class="pa-8 text-center"
              >
                <v-icon size="40" color="grey-lighten-1">mdi-crown-off-outline</v-icon>
                <div class="text-body-2 text-medium-emphasis mt-3 mb-4">
                  {{ $t('tenant_details.no_subscription') }}
                </div>
                <v-btn
                  color="primary"
                  variant="flat"
                  rounded="lg"
                  prepend-icon="mdi-plus"
                  :to="{ name: 'subscriptions' }"
                >
                  {{ $t('tenant_details.assign_plan') }}
                </v-btn>
              </v-card>

              <template v-else>
                <!-- Plan overview -->
                <v-card
                  rounded="lg"
                  variant="tonal"
                  :color="planColor"
                  class="pa-4 mb-4"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div class="d-flex align-center ga-3">
                      <v-avatar
                        :color="planColor"
                        variant="flat"
                        size="44"
                        rounded="lg"
                      >
                        <v-icon :icon="planIcon" size="22" color="white" />
                      </v-avatar>
                      <div>
                        <div class="text-h6 font-weight-bold">
                          {{ plan?.name }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          ${{ plan?.price_usd }} {{ $t('tenant_create.per_month_suffix') }}
                        </div>
                      </div>
                    </div>
                    <v-chip
                      :color="subscriptionStatusColor"
                      variant="flat"
                      size="small"
                    >
                      {{ subscriptionStatusLabel.toUpperCase() }}
                    </v-chip>
                  </div>

                  <!-- Plan features -->
                  <div class="d-flex flex-wrap ga-2 mt-3">
                    <v-chip size="x-small" variant="tonal" :color="planColor">
                      {{ $t('subscription.plan.seats_count', plan?.seats ?? 0) }}
                    </v-chip>
                    <v-chip size="x-small" variant="tonal" :color="planColor">
                      {{ plan?.storage_gb }}{{ $t('tenant_create.storage_suffix') }}
                    </v-chip>
                    <v-chip size="x-small" variant="tonal" :color="planColor">
                      {{
                        plan?.api_limit > 0
                          ? plan.api_limit.toLocaleString() + ' ' + $t('tenant_create.api_calls_suffix')
                          : $t('tenant_create.unlimited_api')
                      }}
                    </v-chip>
                  </div>
                </v-card>

                <!-- Billing period -->
                <div class="section-label mb-3">{{ $t('tenant_details.billing_period') }}</div>
                <v-row dense class="mb-4">
                  <v-col cols="12" sm="4">
                    <div class="info-tile pa-3">
                      <div class="info-tile-label">{{ $t('subscription.list.field.period_start') }}</div>
                      <div class="info-tile-value">
                        {{
                          subscription?.current_period_start
                            ? formatDate(subscription.current_period_start)
                            : '—'
                        }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" sm="4">
                    <div class="info-tile pa-3">
                      <div class="info-tile-label">{{ $t('subscription.list.field.period_end') }}</div>
                      <div class="info-tile-value">
                        {{
                          subscription?.current_period_end
                            ? formatDate(subscription.current_period_end)
                            : '—'
                        }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" sm="4">
                    <div class="info-tile pa-3">
                      <div class="info-tile-label">{{ $t('tenant_details.trial_ends') }}</div>
                      <div class="info-tile-value">
                        {{
                          subscription?.trial_ends_at
                            ? formatDate(subscription.trial_ends_at)
                            : $t('common.na')
                        }}
                      </div>
                    </div>
                  </v-col>
                </v-row>

                <!-- Plan features list -->
                <template v-if="plan?.features?.length">
                  <div class="section-label mb-3">{{ $t('tenant_details.included_features') }}</div>
                  <v-list density="compact" rounded="lg" border>
                    <v-list-item
                      v-for="(feat, i) in plan.features"
                      :key="i"
                      :title="feat.en"
                      density="compact"
                    >
                      <template #prepend>
                        <v-icon size="14" color="success" class="me-2">
                          mdi-check-circle
                        </v-icon>
                      </template>
                    </v-list-item>
                  </v-list>
                </template>
              </template>
            </v-window-item>

            <!-- ══ BRANCHES ══ -->
            <v-window-item value="branches">
              <div v-if="!tenant.branches?.length" class="text-center py-10">
                <v-icon size="48" color="grey-lighten-1">
                  mdi-store-off-outline
                </v-icon>
                <div class="text-body-2 text-medium-emphasis mt-3">
                  {{ $t('tenant_details.no_branches') }}
                </div>
              </div>

              <v-row dense v-else>
                <v-col
                  v-for="branch in tenant.branches"
                  :key="branch.id"
                  cols="12"
                  sm="6"
                >
                  <v-card rounded="lg" variant="outlined" class="pa-4">
                    <div class="d-flex align-center justify-space-between mb-2">
                      <div class="d-flex align-center ga-2">
                        <v-avatar
                          size="32"
                          rounded="md"
                          :color="tenant.primary_color ?? 'primary'"
                          variant="tonal"
                        >
                          <v-icon size="16">mdi-storefront-outline</v-icon>
                        </v-avatar>
                        <div class="text-body-2 font-weight-medium">
                          {{ branch.name }}
                        </div>
                      </div>
                      <div class="d-flex ga-1">
                        <v-chip
                          size="x-small"
                          :color="branch.is_active ? 'success' : 'default'"
                          variant="tonal"
                        >
                          {{ branch.is_active ? $t('status.active') : $t('status.inactive') }}
                        </v-chip>
                        <v-chip
                          size="x-small"
                          :color="branch.is_open ? 'info' : 'default'"
                          variant="tonal"
                        >
                          {{ branch.is_open ? $t('tenant_details.branch_open') : $t('tenant_details.branch_closed') }}
                        </v-chip>
                      </div>
                    </div>
                    <div
                      v-if="branch.address_line1 || branch.city"
                      class="text-caption text-medium-emphasis"
                    >
                      <v-icon size="12" class="mr-1">
                        mdi-map-marker-outline
                      </v-icon>
                      {{
                        [branch.address_line1, branch.city, branch.country]
                          .filter(Boolean)
                          .join(', ')
                      }}
                    </div>
                    <div
                      v-if="branch.phone"
                      class="text-caption text-medium-emphasis mt-1"
                    >
                      <v-icon size="12" class="mr-1">mdi-phone-outline</v-icon>
                      {{ branch.phone }}
                    </div>
                  </v-card>
                </v-col>
              </v-row>
            </v-window-item>

            <!-- ══ PLAN HISTORY ══ -->
            <v-window-item value="history">
              <div v-if="!planHistory?.length" class="text-center py-10">
                <v-icon size="48" color="grey-lighten-1">mdi-history</v-icon>
                <div class="text-body-2 text-medium-emphasis mt-3">
                  {{ $t('tenant_details.no_plan_history') }}
                </div>
              </div>

              <v-timeline
                v-else
                density="compact"
                side="end"
                truncate-line="both"
              >
                <v-timeline-item
                  v-for="entry in planHistory"
                  :key="entry.id"
                  size="x-small"
                  :dot-color="entry.from_plan_id ? 'primary' : 'success'"
                >
                  <div class="d-flex align-start justify-space-between">
                    <div>
                      <div class="text-body-2 font-weight-medium">
                        <template v-if="!entry.from_plan_id">
                          {{ $t('tenant_details.initial_plan') }}
                          <strong>{{ entry.to_plan?.name }}</strong>
                        </template>
                        <template v-else>
                          {{ entry.from_plan?.name }} →
                          <strong>{{ entry.to_plan?.name }}</strong>
                        </template>
                      </div>
                      <div
                        v-if="entry.reason"
                        class="text-caption text-medium-emphasis"
                      >
                        {{ entry.reason }}
                      </div>
                    </div>
                    <div
                      class="text-caption text-medium-emphasis ms-3 text-no-wrap"
                    >
                      {{ formatDate(entry.changed_at) }}
                    </div>
                  </div>
                </v-timeline-item>
              </v-timeline>
            </v-window-item>
          </v-window>
        </v-card>
      </v-col>

      <!-- ── RIGHT: sidebar ── -->
      <v-col cols="12" md="4">
        <!-- Business type -->
        <v-card
          v-if="tenant.business_type"
          rounded="xl"
          border
          elevation="0"
          class="pa-5 mb-4"
        >
          <div class="section-label mb-3">{{ $t('tenant_profile.businessDetails.businessType') }}</div>
          <div class="d-flex align-center ga-3">
            <v-avatar
              size="44"
              rounded="xl"
              color="primary"
              variant="tonal"
              class="me-1"
            >
              <v-icon size="16">{{ tenant.business_type.icon }}</v-icon>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ tenant.business_type.name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ tenant.business_type.code }}
              </div>
            </div>
          </div>
        </v-card>

        <!-- Subscription summary -->
        <v-card rounded="xl" border elevation="0" class="pa-5 mb-4">
          <div class="section-label mb-3">{{ $t('tenant_details.tabs.subscription') }}</div>

          <div v-if="!subscription" class="text-center py-4">
            <div class="text-body-2 text-medium-emphasis mb-3">
              {{ $t('tenant_details.no_subscription') }}
            </div>
            <v-btn
              size="small"
              color="primary"
              variant="tonal"
              rounded="lg"
              prepend-icon="mdi-plus"
              :to="{ name: 'subscriptions' }"
            >
              {{ $t('tenant_details.assign_plan') }}
            </v-btn>
          </div>

          <template v-else>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">{{ $t('subscription.plan.table.name') }}</span>
              <v-chip size="x-small" :color="planColor" variant="tonal">
                {{ plan?.name }}
              </v-chip>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">{{ $t('form.status') }}</span>
              <v-chip
                size="x-small"
                :color="subscriptionStatusColor"
                variant="flat"
              >
                {{ subscriptionStatusLabel }}
              </v-chip>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">{{ $t('form.price') }}</span>
              <span class="text-body-2 font-weight-medium">
                ${{ cyclePrice }}/{{
                  activeBillingCycle?.months > 1
                    ? activeBillingCycle.months + ' ' + $t('tenant_create.month_unit', 2)
                    : $t('tenant_create.month_unit', 1)
                }}
              </span>
            </div>
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">{{ $t('tenant_details.billing_cycle_label') }}</span>
              <v-chip size="x-small" color="primary" variant="tonal">
                {{ activeBillingCycle?.label ?? '—' }}
                <span
                  v-if="Number(activeBillingCycle?.discount_percent) > 0"
                  class="ms-1"
                >
                  ({{ Number(activeBillingCycle.discount_percent).toFixed(0) }}{{ $t('tenant_create.percent_off') }})
                </span>
              </v-chip>
            </div>
            <div class="d-flex align-center justify-space-between">
              <span class="text-body-2 text-medium-emphasis">{{ $t('tenant_details.renews_label') }}</span>
              <span class="text-body-2 font-weight-medium">
                {{
                  subscription?.current_period_end
                    ? formatDate(subscription.current_period_end)
                    : '—'
                }}
              </span>
            </div>
          </template>
        </v-card>

        <!-- Quick actions -->
        <v-card rounded="xl" border elevation="0" class="pa-5">
          <div class="section-label mb-3">{{ $t('dashboard.quick_actions') }}</div>
          <v-btn
            block
            :color="tenant.is_active ? 'warning' : 'success'"
            variant="tonal"
            rounded="lg"
            class="mb-3"
            :loading="togglingActive"
            :prepend-icon="
              tenant.is_active
                ? 'mdi-pause-circle-outline'
                : 'mdi-play-circle-outline'
            "
            @click="toggleActive"
          >
            {{ tenant.is_active ? $t('tenant_details.suspend_tenant') : $t('tenant_details.activate_tenant') }}
          </v-btn>
          <v-btn
            block
            color="error"
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-delete-outline"
            @click="confirmDelete"
          >
            {{ $t('tenant_details.delete_tenant') }}
          </v-btn>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useTenantStore } from '@/stores/tenantStore'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import { useDate } from '@/composables/useDate'

  defineEmits(['edit'])

  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()
  const { confirm, notif } = useAppUtils()
  const tenantStore = useTenantStore()
  const { formatShortDate } = useDate()
  const tab = ref('overview')
  const togglingActive = ref(false)

  // ── The store should hold the full response: { tenant, subscription, plan, plan_history, invoices }
  const tenantData = computed(() => tenantStore.tenantDetail)

  // ── Shortcuts to nested data
  const tenant = computed(() => tenantData.value?.tenant)
  const subscription = computed(() => tenantData.value?.subscription)
  const plan = computed(() => tenantData.value?.plan)
  const planHistory = computed(() => tenantData.value?.plan_history)

  onMounted(async () => {
    await tenantStore.fetchTenantById(route.params.id)
  })

  // ── Plan UI map
  const PLAN_UI = {
    free: { color: 'grey', icon: 'mdi-gift-outline' },
    starter: { color: 'blue', icon: 'mdi-star-half-full' },
    pro: { color: 'primary', icon: 'mdi-star' },
    enterprise: { color: 'warning', icon: 'mdi-crown' }
  }

  const activeBillingCycle = computed(
    () => tenantData.value?.active_billing_cycle
  )

  const planColor = computed(
    () => PLAN_UI[plan.value?.code?.toLowerCase()]?.color ?? 'grey'
  )
  const planIcon = computed(
    () =>
      PLAN_UI[plan.value?.code?.toLowerCase()]?.icon ??
      'mdi-help-circle-outline'
  )

  const cyclePrice = computed(() => {
    const base = Number(plan.value?.price_usd || 0)
    const months = activeBillingCycle.value?.months ?? 1
    const discount =
      Number(activeBillingCycle.value?.discount_percent || 0) / 100
    return (base * months * (1 - discount)).toFixed(2)
  })

  const subscriptionStatusColor = computed(
    () =>
      ({
        active: 'success',
        trial: 'info',
        cancelled: 'error',
        suspended: 'warning'
      })[subscription.value?.status] ?? 'default'
  )

  const subscriptionStatusLabel = computed(() => {
    if (!subscription.value) return t('tenant_details.no_subscription_short')
    return (
      {
        active: t('status.active'),
        trial: t('subscription.status.trial'),
        cancelled: t('status.cancelled'),
        suspended: t('subscription.status.suspended')
      }[subscription.value.status] ?? subscription.value.status
    )
  })

  // ── Owner
  const ownerInitials = computed(() => {
    const o = tenant.value?.owner
    if (!o) return '?'
    return (
      ((o.first_name?.[0] ?? '') + (o.last_name?.[0] ?? '')).toUpperCase() ||
      '?'
    )
  })

  // ── Dates & expiry
  const formatDate = d => (d ? formatShortDate(d) : t('common.na'))

  const daysUntilExpiry = computed(() => {
    const end = subscription.value?.current_period_end
    if (!end) return null
    return Math.round((new Date(end) - new Date()) / 864e5)
  })

  const daysUntilTrial = computed(() => {
    const end = subscription.value?.trial_ends_at
    if (!end) return null
    return Math.round((new Date(end) - new Date()) / 864e5)
  })

  const isExpiringSoon = computed(
    () => daysUntilExpiry.value !== null && daysUntilExpiry.value < 14
  )

  // ── Actions
  const toggleActive = async () => {
    if (!tenant.value?.id) return
    togglingActive.value = true
    try {
      await tenantStore.toggleTenantActive(tenant.value.id)
      // toggleTenantActive only patches the tenants LIST state — refetch the
      // full detail so this page's is_active chip/buttons reflect it too.
      await tenantStore.fetchTenantById(tenant.value.id)
    } catch (err) {
      notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
    } finally {
      togglingActive.value = false
    }
  }

  const confirmDelete = () => {
    if (!tenant.value?.id) return
    confirm({
      title: t('tenant_details.list.confirm_delete.title'),
      message: t('tenant_details.list.confirm_delete.message', { name: tenant.value.name }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        try {
          await tenantStore.deleteTenant(tenant.value.id)
          notif(t('messages.deleted_success'), { type: 'success' })
          router.push({ name: 'tenants' })
        } catch (err) {
          notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }
</script>

<style scoped>
  .v-timeline {
    max-height: 440px;
    overflow-y: auto;
    /* fade-out at bottom */
    mask-image: linear-gradient(to bottom, black 80%, transparent 100%);
    -webkit-mask-image: linear-gradient(to bottom, black 80%, transparent 100%);
  }
  .section-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: rgb(var(--v-theme-primary));
  }

  .info-tile {
    background: rgba(var(--v-theme-surface-variant), 0.1);
    border-radius: 10px;
    border: 0.5px solid rgba(var(--v-border-color), 0.15);
    height: 100%;
  }
  .info-tile-label {
    font-size: 11px;
    color: rgba(var(--v-theme-on-surface), 0.45);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .info-tile-value {
    font-size: 14px;
    font-weight: 500;
    color: rgba(var(--v-theme-on-surface), 0.87);
  }
</style>
