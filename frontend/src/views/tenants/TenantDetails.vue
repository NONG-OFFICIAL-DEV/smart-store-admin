<template>
  <v-container fluid v-if="tenant" class="pa-0">
    <AppPageHeader
      title="Tenant Details"
      show-back
      :breadcrumbs="[
        { title: 'Tenants', to: '/tenants' },
        { title: tenant.name ?? 'Detail' }
      ]"
    >
      <template #title-after>
        <v-chip
          :color="tenant.is_active ? 'success' : 'error'"
          size="x-small"
          variant="flat"
        >
          {{ tenant.is_active ? 'Active' : 'Suspended' }}
        </v-chip>
      </template>

      <template #right>
        <v-btn
          prepend-icon="mdi-pencil"
          rounded="lg"
          class="bg-primary"
          @click="$emit('edit', tenant)"
        >
          Edit Tenant
        </v-btn>
      </template>
    </AppPageHeader>

    <!-- ── Identity banner ── -->
    <v-card rounded="xl" border elevation="0" class="mb-5 overflow-hidden">
      <div class="tenant-banner" :style="bannerStyle">
        <div class="banner-content pa-5">
          <!-- Logo / emoji avatar -->
          <div class="d-flex align-center gap-4">
            <v-avatar
              size="64"
              rounded="xl"
              :color="tenant.primary_color ?? 'primary'"
              class="tenant-logo-avatar me-3"
            >
              <span v-if="!tenant.logo_url" class="text-h5">
                {{
                  tenant.business_type?.icon ?? tenant.name?.[0]?.toUpperCase()
                }}
              </span>
              <v-img v-else :src="tenant.logo_url" cover />
            </v-avatar>

            <div>
              <div class="text-h6 font-weight-bold">{{ tenant.name }}</div>
              <div class="text-body-2 text-medium-emphasis">
                {{ tenant.slug }}.app.com
              </div>
              <div class="d-flex align-center gap-2 mt-1">
                <v-chip size="x-small" variant="tonal" color="primary">
                  {{ tenant.business_type?.name ?? tenant.bu_type }}
                </v-chip>
                <v-chip
                  size="x-small"
                  variant="tonal"
                  :color="planColor"
                  class="ms-2 me-2"
                >
                  {{ tenant.plan?.toUpperCase() }}
                </v-chip>
                <v-chip
                  v-if="tenant.primary_color"
                  size="x-small"
                  variant="outlined"
                  class="pl-1"
                >
                  <span
                    class="color-dot mr-1"
                    :style="{ background: tenant.primary_color }"
                  />
                  {{ tenant.primary_color }}
                </v-chip>
              </div>
            </div>
          </div>

          <!-- Key stats -->
          <v-row class="mt-5" dense>
            <v-col cols="6" sm="3">
              <div class="stat-card pa-3">
                <div class="stat-value">{{ tenant.branches?.length ?? 0 }}</div>
                <div class="stat-label">Branches</div>
              </div>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="stat-card pa-3">
                <div class="stat-value">{{ tenant.currency }}</div>
                <div class="stat-label">Currency</div>
              </div>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="stat-card pa-3">
                <div class="stat-value">{{ tenant.timezone }}</div>
                <div class="stat-label">Timezone</div>
              </div>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="stat-card pa-3">
                <div class="stat-value">{{ tenant.locale }}</div>
                <div class="stat-label">Locale</div>
              </div>
            </v-col>
          </v-row>
        </div>
      </div>
    </v-card>

    <v-row>
      <!-- ── LEFT: tabs ── -->
      <v-col cols="12" md="8">
        <v-card rounded="xl" border elevation="0">
          <v-tabs v-model="tab" color="primary">
            <v-tab value="overview">
              <v-icon size="16" class="mr-1">mdi-information-outline</v-icon>
              Overview
            </v-tab>
            <v-tab value="branches">
              <v-icon size="16" class="mr-1">mdi-store-outline</v-icon>
              Branches
              <v-badge
                v-if="tenant.branches?.length"
                :content="tenant.branches.length"
                color="primary"
                inline
                class="ml-1"
              />
            </v-tab>
            <v-tab value="billing">
              <v-icon size="16" class="mr-1">mdi-credit-card-outline</v-icon>
              Billing
            </v-tab>
            <v-tab value="settings">
              <v-icon size="16" class="mr-1">mdi-earth</v-icon>
              Localization
            </v-tab>
          </v-tabs>

          <v-divider />

          <v-window v-model="tab" class="pa-5">
            <!-- ── OVERVIEW ── -->
            <v-window-item value="overview">
              <div class="section-label mb-3">General</div>

              <v-row dense class="mb-4">
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Tenant ID</div>
                    <div class="info-tile-value font-mono text-truncate">
                      {{ tenant.id }}
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Slug / Subdomain</div>
                    <div class="info-tile-value">{{ tenant.slug }}.app.com</div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Business type</div>
                    <div class="d-flex align-center gap-2 mt-1">
                      <span class="text-h6">
                        {{ tenant.business_type?.icon }}
                      </span>
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
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Created</div>
                    <div class="info-tile-value">
                      {{ formatDate(tenant.created_at) }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ formatDateTime(tenant.created_at) }}
                    </div>
                  </div>
                </v-col>
              </v-row>

              <v-divider class="mb-4" />
              <div class="section-label mb-3">Owner</div>

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

                  <template #append>
                    <v-btn
                      variant="tonal"
                      size="small"
                      prepend-icon="mdi-account-switch-outline"
                      rounded="lg"
                      @click="openTransfer"
                    >
                      Transfer
                    </v-btn>
                  </template>
                </v-list-item>
              </v-card>
            </v-window-item>

            <!-- ── BRANCHES ── -->
            <v-window-item value="branches">
              <div v-if="!tenant.branches?.length" class="text-center py-10">
                <v-icon size="48" color="grey-lighten-1">
                  mdi-store-off-outline
                </v-icon>
                <div class="text-body-2 text-medium-emphasis mt-3">
                  No branches yet
                </div>
              </div>

              <v-row dense v-else>
                <v-col
                  v-for="branch in tenant.branches"
                  :key="branch.id"
                  cols="12"
                  sm="6"
                >
                  <v-card
                    rounded="lg"
                    variant="outlined"
                    class="branch-card pa-4"
                  >
                    <div class="d-flex align-center justify-space-between mb-2">
                      <div class="d-flex align-center gap-2">
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
                      <div class="d-flex gap-1">
                        <v-chip
                          size="x-small"
                          :color="branch.is_active ? 'success' : 'default'"
                          variant="tonal"
                        >
                          {{ branch.is_active ? 'Active' : 'Inactive' }}
                        </v-chip>
                        <v-chip
                          size="x-small"
                          :color="branch.is_open ? 'info' : 'default'"
                          variant="tonal"
                        >
                          {{ branch.is_open ? 'Open' : 'Closed' }}
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

            <!-- ── BILLING ── -->
            <v-window-item value="billing">
              <v-alert
                v-if="isExpiringSoon"
                type="warning"
                variant="tonal"
                icon="mdi-alert-circle-outline"
                rounded="lg"
                class="mb-5"
              >
                Plan expires in
                <strong>{{ daysUntilExpiry }}</strong>
                days. Consider renewing soon.
              </v-alert>

              <v-alert
                v-else-if="!tenant.plan_expires_at"
                type="info"
                variant="tonal"
                icon="mdi-infinity"
                rounded="lg"
                class="mb-5"
              >
                This plan has no expiry date.
              </v-alert>

              <div class="d-flex align-center justify-space-between mb-5">
                <div>
                  <div
                    class="text-h5 font-weight-bold"
                    :style="{
                      color:
                        tenant.primary_color ?? 'rgb(var(--v-theme-primary))'
                    }"
                  >
                    {{ tenant.plan?.toUpperCase() }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Current subscription plan
                  </div>
                </div>
                <v-btn color="primary" variant="flat" rounded="lg">
                  Change Plan
                </v-btn>
              </div>

              <v-row dense>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Expiry date</div>
                    <div class="info-tile-value">
                      {{
                        tenant.plan_expires_at
                          ? formatDate(tenant.plan_expires_at)
                          : 'No expiry'
                      }}
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Billing currency</div>
                    <div class="info-tile-value">{{ tenant.currency }}</div>
                  </div>
                </v-col>
              </v-row>
            </v-window-item>

            <!-- ── LOCALIZATION ── -->
            <v-window-item value="settings">
              <v-row dense>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Timezone</div>
                    <div class="d-flex align-center gap-2 mt-1">
                      <v-icon size="16" color="primary">
                        mdi-clock-outline
                      </v-icon>
                      <div class="info-tile-value">{{ tenant.timezone }}</div>
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Locale</div>
                    <div class="d-flex align-center gap-2 mt-1">
                      <v-icon size="16" color="primary">mdi-translate</v-icon>
                      <div class="info-tile-value">
                        {{ tenant.locale || 'en-US' }}
                      </div>
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Currency</div>
                    <div class="d-flex align-center gap-2 mt-1">
                      <v-icon size="16" color="primary">
                        mdi-cash-multiple
                      </v-icon>
                      <div class="info-tile-value">{{ tenant.currency }}</div>
                    </div>
                  </div>
                </v-col>
                <v-col cols="12" sm="6">
                  <div class="info-tile pa-3">
                    <div class="info-tile-label">Brand color</div>
                    <div class="d-flex align-center gap-2 mt-1">
                      <span
                        class="color-swatch"
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
              </v-row>
            </v-window-item>
          </v-window>
        </v-card>
      </v-col>

      <!-- ── RIGHT: sidebar ── -->
      <v-col cols="12" md="4">
        <!-- Business type card -->
        <v-card
          v-if="tenant.business_type"
          rounded="xl"
          border
          elevation="0"
          class="pa-5"
        >
          <div class="section-label mb-3">Business Type</div>
          <div class="d-flex align-center gap-3">
            <v-avatar size="48" rounded="xl" color="primary" variant="tonal" class="me-2">
              <span class="text-h5">{{ tenant.business_type.icon }}</span>
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
        <!-- Quick actions -->
        <v-card rounded="xl" border elevation="0" class="pa-5 mt-4">
          <div class="section-label mb-3">Quick Actions</div>

          <v-btn
            block
            :color="tenant.is_active ? 'warning' : 'success'"
            variant="tonal"
            rounded="lg"
            class="mb-3"
            :prepend-icon="
              tenant.is_active
                ? 'mdi-pause-circle-outline'
                : 'mdi-play-circle-outline'
            "
            @click="toggleActive"
          >
            {{ tenant.is_active ? 'Suspend Tenant' : 'Activate Tenant' }}
          </v-btn>

          <v-btn
            block
            color="error"
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-delete-outline"
            @click="confirmDelete"
          >
            Delete Records
          </v-btn>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRoute } from 'vue-router'
  import { useTenantStore } from '@/stores/tenantStore'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'

  defineEmits(['edit'])

  const route = useRoute()
  const tenantStore = useTenantStore()
  const tab = ref('overview')

  const tenant = computed(() => tenantStore.tenant)

  onMounted(async () => {
    await tenantStore.fetchTenantById(route.params.id)
  })

  // ─── Computed ─────────────────────────────────────────────────────────────────
  const ownerInitials = computed(() => {
    const o = tenant.value?.owner
    if (!o) return '?'
    return (
      ((o.first_name?.[0] ?? '') + (o.last_name?.[0] ?? '')).toUpperCase() ||
      '?'
    )
  })

  const planColor = computed(() => {
    const map = {
      free: 'default',
      starter: 'info',
      pro: 'primary',
      enterprise: 'warning'
    }
    return map[tenant.value?.plan?.toLowerCase()] ?? 'default'
  })

  const bannerStyle = computed(() => {
    const color = tenant.value?.primary_color ?? '#6366f1'
    return {
      background: `linear-gradient(135deg, ${color}14 0%, ${color}06 100%)`,
      borderBottom: `2px solid ${color}22`
    }
  })

  const daysUntilExpiry = computed(() => {
    const exp = tenant.value?.plan_expires_at
    if (!exp) return null
    return Math.round((new Date(exp) - new Date()) / 864e5)
  })

  const isExpiringSoon = computed(
    () => daysUntilExpiry.value !== null && daysUntilExpiry.value < 14
  )

  // ─── Helpers ──────────────────────────────────────────────────────────────────
  const formatDate = d =>
    d
      ? new Date(d).toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'short',
          day: 'numeric'
        })
      : 'N/A'

  const formatDateTime = d =>
    d
      ? new Date(d).toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit'
        })
      : ''

  // ─── Actions (stubs — connect to store) ───────────────────────────────────────
  const toggleActive = () => tenantStore.toggleTenantActive(tenant.value?.id)
  const confirmDelete = () => console.log('confirm delete', tenant.value?.id)
  const openTransfer = () => console.log('transfer owner', tenant.value?.id)
</script>

<style scoped>
  /* ── Banner ── */
  .tenant-banner {
    transition: background 0.3s;
  }
  .banner-content {
    padding: 24px;
  }
  .tenant-logo-avatar {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  }

  /* ── Stat cards in banner ── */
  .stat-card {
    background: rgba(255, 255, 255, 0.6);
    border-radius: 10px;
    border: 0.5px solid rgba(0, 0, 0, 0.07);
    backdrop-filter: blur(4px);
  }
  .stat-value {
    font-size: 15px;
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 0.87);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .stat-label {
    font-size: 11px;
    color: rgba(var(--v-theme-on-surface), 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
  }

  /* ── Section label ── */
  .section-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: rgb(var(--v-theme-primary));
  }

  /* ── Info tiles ── */
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

  /* ── Sidebar meta ── */
  .meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 2px 0;
  }
  .meta-label {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.5);
    flex-shrink: 0;
  }
  .meta-value {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.87);
    font-weight: 500;
    text-align: right;
  }

  /* ── Branch cards ── */
  .branch-card {
    transition: box-shadow 0.15s;
  }
  .branch-card:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  }

  /* ── Color utilities ── */
  .color-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
  }
  .color-swatch {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 6px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
  }

  /* ── Mono font ── */
  .font-mono {
    font-family: 'Courier New', monospace;
    font-size: 12px;
  }
</style>
