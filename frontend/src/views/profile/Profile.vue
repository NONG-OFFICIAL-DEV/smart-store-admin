<template>
  <v-container fluid class="pa-0">
    <!-- Clean Premium Page Header -->

    <custom-title
      icon="mdi-account-circle-outline"
      :title="$t('profile.title')"
      :subtitle="$t('profile.subtitle')"
      class="custom-title-modern"
    />

    <!-- Loading State (Modernized Skeleton Grid) -->
    <template v-if="tenantStore.loading">
      <v-row>
        <v-col cols="12" md="4">
          <v-skeleton-loader
            type="card"
            height="320"
            class="mb-6 rounded-xl-border"
          />
          <v-skeleton-loader
            type="card"
            height="200"
            class="rounded-xl-border"
          />
        </v-col>
        <v-col cols="12" md="8">
          <v-skeleton-loader
            type="card"
            height="544"
            class="rounded-xl-border"
          />
        </v-col>
      </v-row>
    </template>

    <!-- Error State -->
    <v-alert
      v-else-if="tenantStore.error"
      type="error"
      variant="tonal"
      border="start"
      class="mb-6 modern-alert"
    >
      {{ tenantStore.error }}
    </v-alert>

    <!-- Main Content -->
    <template v-else>
      <v-row class="ga-y-6">
        <!-- LEFT COLUMN: Profile & Metadata Overview -->
        <v-col cols="12" md="4">
          <!-- Identity Card -->
          <v-card class="profile-hero-card mb-6 pa-6" rounded="xl">
            <div class="d-flex flex-column align-center text-center">
              <v-avatar
                color="indigo-lighten-5"
                size="84"
                class="avatar-glow mb-4"
              >
                <v-img
                  v-if="tenant.tenant?.logo_url"
                  :src="tenant.tenant.logo_url"
                  cover
                />
                <span
                  v-else
                  class="text-h5 font-weight-bold text-indigo-darken-2"
                >
                  {{ initials }}
                </span>
              </v-avatar>

              <h2
                class="text-h6 font-weight-bold text-high-emphasis tracking-tight mb-1"
              >
                {{ displayName }}
              </h2>

              <v-chip
                v-if="tenant.tenant?.id"
                :color="statusColor"
                :prepend-icon="statusIcon"
                variant="flat"
                size="small"
                class="px-3 font-weight-medium text-capitalize mb-4"
              >
                {{ tenant.status ?? 'Active' }}
              </v-chip>

              <!-- Tenant id / joined-since only mean something when a tenant is loaded (not for a super admin) -->
              <div v-if="tenant.tenant?.id" class="w-100 text-start mt-2 pt-4 border-top-dashed">
                <div class="d-flex justify-space-between mb-2">
                  <span class="text-caption text-medium-emphasis">
                    {{ $t('tenant_profile.identity.tenantId') }}
                  </span>
                  <span
                    class="text-caption font-mono font-weight-medium bg-neutral-light px-2 py-0.5 rounded"
                  >
                    {{ tenant.tenant?.id }}
                  </span>
                </div>
                <div class="d-flex justify-space-between">
                  <span class="text-caption text-medium-emphasis">
                    {{ $t('tenant_profile.identity.joinedSince') }}
                  </span>
                  <span
                    class="text-caption font-weight-medium text-high-emphasis"
                  >
                    {{ formatDate(tenant.tenant.created_at) }}
                  </span>
                </div>
              </div>
            </div>
          </v-card>

          <!-- Business Details Card — only meaningful once a tenant has actually loaded (a super admin owns none) -->
          <v-card v-if="tenant.tenant?.id" class="pa-5" rounded="lg">
            <div class="text-overline tracking-wider text-medium-emphasis mb-4">
              {{ $t('tenant_profile.businessDetails.title') }}
            </div>

            <div class="d-flex align-start ga-3 mb-4">
              <v-icon color="indigo" size="20" class="mt-0.5">
                mdi-domain
              </v-icon>
              <div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('tenant_profile.businessDetails.businessType') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ tenant.tenant?.business_type?.code ?? '—' }}
                </div>
              </div>
            </div>

            <div class="d-flex align-start ga-3">
              <v-icon color="indigo" size="20" class="mt-0.5">
                mdi-translate
              </v-icon>
              <div>
                <div class="text-caption text-medium-emphasis">
                  {{ $t('tenant_profile.businessDetails.localization') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ tenant.tenant?.locale ?? '—' }}
                  <span class="text-disabled mx-1">•</span>
                  {{ tenant.tenant?.currency ?? 'USD' }}
                </div>
              </div>
            </div>
          </v-card>
        </v-col>

        <!-- RIGHT COLUMN: Analytics Metrics & Form Data -->
        <v-col cols="12" md="8">
          <!-- Modern Stat KPI Grid Widget (none of this applies to a super admin — no tenant) -->
          <v-row v-if="kpiStats.length" class="mb-6" dense>
            <v-col cols="6" sm="3" v-for="(stat, idx) in kpiStats" :key="idx">
              <v-card class="pa-4 text-start" rounded="lg">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span
                    class="text-caption text-medium-emphasis font-weight-medium"
                  >
                    {{ stat.label }}
                  </span>
                  <v-icon :color="stat.iconColor" size="18">
                    {{ stat.icon }}
                  </v-icon>
                </div>
                <div
                  :class="`text-h5 font-weight-bold ${stat.textColor || 'text-high-emphasis'}`"
                >
                  {{ stat.value }}
                </div>
              </v-card>
            </v-col>
          </v-row>

          <!-- Main Account Information Form Card -->
          <v-card class="pa-6" rounded="lg">
            <div class="d-flex align-center justify-space-between mb-6">
              <div>
                <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
                  {{ $t('tenant_profile.registry.title') }}
                </h3>
                <p class="text-caption text-medium-emphasis">
                  {{ $t('tenant_profile.registry.subtitle') }}
                </p>
              </div>
            </div>

            <v-row class="ga-y-4">
              <v-col cols="12" sm="6">
                <div class="info-group">
                  <label>
                    <v-icon size="14" class="me-1">mdi-email-outline</v-icon>
                    {{ $t('tenant_profile.registry.email') }}
                  </label>
                  <p>{{ authStore.me?.email ?? '—' }}</p>
                </div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="info-group">
                  <label>
                    <v-icon size="14" class="me-1">mdi-phone-outline</v-icon>
                    {{ $t('tenant_profile.registry.phone') }}
                  </label>
                  <p>{{ canViewOwnerPhone ? (tenant.tenant?.owner?.phone ?? '—') : '—' }}</p>
                </div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="info-group">
                  <label>
                    <v-icon size="14" class="me-1">
                      mdi-map-marker-outline
                    </v-icon>
                    {{ $t('tenant_profile.registry.country') }}
                  </label>
                  <p>{{ tenant.country ?? '—' }}</p>
                </div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="info-group">
                  <label>
                    <v-icon size="14" class="me-1">
                      mdi-account-key-outline
                    </v-icon>
                    {{ $t('tenant_profile.registry.admin') }}
                  </label>
                  <p>{{ authStore.me?.full_name ?? '—' }}</p>
                </div>
              </v-col>
            </v-row>
          </v-card>
        </v-col>
      </v-row>
    </template>

  </v-container>
</template>

<script setup>
  import { computed, onMounted } from 'vue'
  import { useTenantStore } from '../../stores/tenantStore'
  import { useAuthStore } from '../../stores/authStore'
  import { formatDate } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useAvatar } from '@/composables/useAvatar'

  const { t } = useI18n()
  const tenantStore = useTenantStore()
  const authStore = useAuthStore()
  const { getInitials } = useAvatar()

  // Super admins own no tenant (authStore.tenant_id is null for them) — skip
  // the fetch rather than requesting a non-existent "/tenants/null".
  onMounted(() => {
    if (authStore.tenant_id) tenantStore.fetchTenantById(authStore.tenant_id)
  })

  const tenant = computed(() => tenantStore.tenant ?? {})

  // A super admin has no tenant to name the card after — fall back to their own name
  const displayName = computed(() => tenant.value?.tenant?.name || authStore.me?.full_name || '—')

  // Initials computation
  const initials = computed(() => getInitials(displayName.value, ''))

  // The owner's personal phone is only shown to the owner themselves or a super admin
  const canViewOwnerPhone = computed(() => authStore.isOwner || authStore.isSuperAdmin)

  // Status Style Maps
  const STATUS_COLOR = {
    active: 'success',
    suspended: 'error',
    trial: 'warning'
  }
  const STATUS_ICON = {
    active: 'mdi-check-circle',
    suspended: 'mdi-cancel',
    trial: 'mdi-alert-circle'
  }

  const statusColor = computed(
    () => STATUS_COLOR[tenant.value.status] ?? 'grey'
  )
  const statusIcon = computed(
    () => STATUS_ICON[tenant.value.status] ?? 'mdi-help-circle'
  )

  // Centralized modern stats object array
  const kpiStats = computed(() => {
    // Super admin isn't tied to any tenant — none of these stats apply
    if (authStore.isSuperAdmin) return []

    const tenantData = tenant.value?.tenant
    const branches = tenantData?.branches ?? []

    const stats = [
      {
        label: t('tenant_profile.kpi.branches'),
        value: branches.length,
        icon: 'mdi-store-outline',
        iconColor: 'indigo'
      }
    ]

    const sub = tenantData?.subscriptions?.[0]
    stats.push(
      {
        label: t('tenant_profile.kpi.plan'),
        value: sub?.plan?.name ?? '—',
        icon: 'mdi-crown-outline',
        iconColor: 'warning'
      },
      {
        label: t('tenant_profile.kpi.seats'),
        value: sub?.plan?.seats ?? '—',
        icon: 'mdi-account-group-outline',
        iconColor: 'success'
      },
      {
        label: t('tenant_profile.kpi.storage'),
        value: sub?.plan?.storage_gb ? `${sub.plan.storage_gb} GB` : '—',
        icon: 'mdi-database-outline',
        iconColor: 'indigo'
      }
    )
    return stats
  })
</script>

<style scoped>
  /* Modern Typography Utilities */
  .tracking-tight {
    letter-spacing: -0.025em !important;
  }
  .tracking-wider {
    letter-spacing: 0.05em !important;
  }

  /* Custom Profile Design Tweaks */
  .avatar-glow {
    border: 4px solid #f8fafc;
    box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.15);
  }

  .border-top-dashed {
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
  }

  .bg-neutral-light {
    background-color: #f1f5f9;
  }

  /* Clean, Non-list Form Styling */
  .info-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(var(--v-theme-medium-emphasis));
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
  }

  .info-group p {
    font-size: 0.9rem;
    font-weight: 500;
    color: rgb(var(--v-theme-high-emphasis));
  }

  .modern-alert {
    border-radius: 12px !important;
  }

  .rounded-xl-border {
    border-radius: 16px !important;
  }
</style>
