<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-office-building-marker"
      :title="$t('menu.tenant')"
      :subtitle="$t('tenant_details.list.subtitle')"
    >
      <template #right>
        <v-btn
          variant="tonal"
          prepend-icon="mdi-briefcase-outline"
          rounded="lg"
          @click="manageBusinessTypesDialog = true"
        >
          {{ $t('business_type.manage_title') }}
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          class="ms-2"
          @click="openCreate"
        >
          {{ $t('tenant_details.list.add') }}
        </v-btn>
      </template>
    </custom-title>

    <BusinessTypeManagerDialog
      v-model="manageBusinessTypesDialog"
      @update:model-value="val => !val && tenantStore.fetchBusinessTypes()"
    />

    <!-- ── Filters ────────────────────────────────────────────────────────────── -->
    <v-row dense align="center" class="mb-2">
      <v-col cols="12" sm="3">
        <custom-select
          v-model="businessFiter"
          :items="businessTypes"
          item-title="name"
          item-value="id"
          :label="$t('tenant_details.list.business_types')"
          :multiple="true"
          :chips="true"
          :max-visible-chips="1"
        />
      </v-col>
      <v-col cols="12" sm="3">
        <custom-select
          v-model="planFilter"
          :items="plans"
          item-title="name"
          item-value="id"
          :label="$t('subscription.plan.table.name')"
          :multiple="true"
          :chips="true"
          :max-visible-chips="1"
        />
      </v-col>
      <v-col cols="12" sm="auto">
        <v-btn-toggle
          v-model="activeFilter"
          color="primary"
          variant="tonal"
          rounded="lg"
          density="compact"
        >
          <v-btn value="" size="small" class="text-none px-3">
            {{ $t('status.all') }}
          </v-btn>
          <v-btn value="1" size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="success" class="mr-1" />
            {{ $t('status.active') }}
          </v-btn>
          <v-btn value="0" size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="error" class="mr-1" />
            {{ $t('tenant_details.status.suspended') }}
          </v-btn>
        </v-btn-toggle>
      </v-col>
    </v-row>

    <!-- ── Table ──────────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTenantsForTable"
        :filters="filters"
        :show-search="true"
        item-label="tenants"
      >
        <!-- Logo + Name -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3">
            <v-avatar
              size="36"
              rounded="lg"
              :color="item.primary_color || 'primary'"
              variant="tonal"
            >
              <v-img v-if="item.logo_url" :src="item.logo_url" cover />
              <span v-else class="text-caption font-weight-bold">
                {{ item.name.charAt(0).toUpperCase() }}
              </span>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">{{ item.name }}</div>
              <div class="text-caption text-grey">{{ item.slug }}</div>
            </div>
          </div>
        </template>

        <!-- Owner -->
        <template #item.owner="{ item }">
          <div v-if="item.owner" class="d-flex align-center gap-2">
            <v-avatar color="primary" variant="tonal" size="28" rounded="lg">
              <span style="font-size: 10px; font-weight: 700">
                {{ ownerInitials(item.owner) }}
              </span>
            </v-avatar>
            <div>
              <div class="text-caption font-weight-medium">
                {{ item.owner.first_name }} {{ item.owner.last_name }}
              </div>
              <div class="text-caption text-grey">{{ item.owner.email }}</div>
            </div>
          </div>
          <span v-else class="text-grey text-caption">—</span>
        </template>

        <!-- BU Type -->
        <template #item.bu_type="{ item }">
          <v-chip
            :color="getBuType(item.business_type?.name).color"
            :prepend-icon="item.business_type?.icon"
            variant="tonal"
            size="small"
            rounded="lg"
          >
            {{ getBuType(item.business_type?.name).label }}
          </v-chip>
        </template>

        <!-- Plan -->
        <template #item.plan="{ item }">
          <v-chip
            v-if="item.active_subscription?.plan"
            :color="planColor(item.active_subscription.plan.code)"
            variant="tonal"
            size="small"
            :prepend-icon="planIcon(item.active_subscription.plan.code)"
          >
            {{ item.active_subscription.plan.name }}
          </v-chip>
          <v-tooltip v-else :text="$t('subscription.list.action.assign_plan')">
            <template #activator="{ props: tp }">
              <v-btn
                v-bind="tp"
                size="x-small"
                variant="tonal"
                color="primary"
                rounded="lg"
                prepend-icon="mdi-plus"
                @click="openManageSubscription(item)"
              >
                {{ $t('subscription.list.assign_plan') }}
              </v-btn>
            </template>
          </v-tooltip>
        </template>

        <!-- Currency -->
        <template #item.locale="{ item }">
          <v-chip size="x-small" variant="tonal" color="grey">
            {{ item.currency }}
          </v-chip>
        </template>

        <!-- Status -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
            variant="tonal"
            :prepend-icon="
              item.is_active
                ? 'mdi-check-circle-outline'
                : 'mdi-minus-circle-outline'
            "
          >
            {{
              item.is_active
                ? $t('status.active')
                : $t('tenant_details.status.suspended')
            }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-end">
            <v-menu>
              <template #activator="{ props: menuProps }">
                <v-btn
                  icon="mdi-dots-vertical"
                  variant="text"
                  size="small"
                  v-bind="menuProps"
                />
              </template>
              <v-list density="compact" min-width="200">
                <v-list-item
                  :title="$t('btn.view')"
                  prepend-icon="mdi-eye-outline"
                  class="text-primary"
                  :to="`/tenants/${item.id}`"
                />
                <v-list-item
                  :title="$t('btn.edit')"
                  prepend-icon="mdi-pencil-outline"
                  class="text-info"
                  @click="openEdit(item)"
                />
                <v-list-item
                  :title="$t('admin_tenant_users.manage_users')"
                  prepend-icon="mdi-account-multiple-outline"
                  class="text-secondary"
                  @click="openUsersDialog(item)"
                />
                <v-list-item
                  :title="$t('subscription.manage_subscription')"
                  prepend-icon="mdi-crown-outline"
                  class="text-deep-purple"
                  @click="openManageSubscription(item)"
                />
                <v-list-item
                  :title="$t('impersonation.log_in_as_tenant')"
                  prepend-icon="mdi-login-variant"
                  class="text-indigo"
                  :disabled="impersonatingId === item.id"
                  @click="loginAsTenant(item)"
                />
                <v-list-item
                  :title="
                    item.is_active
                      ? $t('tenant_details.suspend_tenant')
                      : $t('tenant_details.activate_tenant')
                  "
                  :prepend-icon="
                    item.is_active
                      ? 'mdi-pause-circle-outline'
                      : 'mdi-play-circle-outline'
                  "
                  :class="item.is_active ? 'text-warning' : 'text-success'"
                  @click="toggleActive(item)"
                />
                <v-list-item
                  :title="$t('btn.delete')"
                  prepend-icon="mdi-delete-outline"
                  class="text-error"
                  @click="confirmDelete(item)"
                />
              </v-list>
            </v-menu>
          </div>
        </template>

        <!-- Empty -->
        <template #no-data>
          <div class="text-center py-12">
            <v-icon
              icon="mdi-office-building-off-outline"
              size="56"
              color="grey-lighten-1"
              class="mb-3"
            />
            <p class="text-h6 text-medium-emphasis mb-1">
              {{ $t('tenant_details.list.empty') }}
            </p>
            <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-plus"
              class="mt-2"
              @click="openCreate"
            >
              {{ $t('tenant_details.list.add_first') }}
            </v-btn>
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- ── Manage Users Dialog ── -->
    <AdminTenantUsersDialog v-model="usersDialog" :tenant="usersTarget" />
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import CustomSelect from '@/components/customs/CustomSelect.vue'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useAuthStore } from '@/stores/authStore'
  import { usePlanStore } from '../../stores/planStore'
  import { storeToRefs } from 'pinia'
  import { AppTable, useAppUtils } from '@nong-official-dev/core'
  import { getAllTenantsApi } from '@/api/tenantService'
  import { useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import BusinessTypeManagerDialog from '@/components/business/BusinessTypeManagerDialog.vue'
  import AdminTenantUsersDialog from '@/components/tenants/AdminTenantUsersDialog.vue'

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const router = useRouter()

  const tenantStore = useTenantStore()
  const authStore = useAuthStore()
  const planStore = usePlanStore()
  const { businessTypes } = storeToRefs(tenantStore)

  const tableRef = ref(null)
  const saving = ref(false)
  const manageBusinessTypesDialog = ref(false)
  const usersDialog = ref(false)
  const usersTarget = ref(null)
  const impersonatingId = ref(null)
  const planFilter = ref([])
  const businessFiter = ref([])
  const activeFilter = ref('')
  const showFilters = ref(true)

  const plans = computed(() => planStore.plans)

  // ── Structured filters passed to AppTable — deep-watched, auto-refetches
  // on change (free-text search is AppTable's own built-in field). ─────────────
  const filters = computed(() => ({
    plan: planFilter.value?.length ? planFilter.value.join(',') : undefined,
    business_type: businessFiter.value?.length
      ? businessFiter.value.join(',')
      : undefined,
    is_active:
      activeFilter.value !== '' ? activeFilter.value === '1' : undefined
  }))

  // ── AppTable's fetch-fn contract: params -> { items, total } ─────────────────
  async function fetchTenantsForTable(params) {
    const { data } = await getAllTenantsApi(params)
    return { items: data.data, total: data.meta.total }
  }

  const activeFilterCount = computed(() => {
    let count = 0
    if (planFilter.value?.length > 0) count++
    if (businessFiter.value?.length > 0) count++
    if (activeFilter.value !== '') count++
    return count
  })

  const hasActiveFilters = computed(
    () =>
      planFilter.value?.length > 0 ||
      businessFiter.value?.length > 0 ||
      activeFilter.value !== ''
  )

  const resetFilters = () => {
    planFilter.value = []
    businessFiter.value = []
    activeFilter.value = ''
  }

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    {
      title: t('tenant_details.list.table.tenant'),
      key: 'name',
      sortable: true
    },
    { title: t('tenant_create.review.owner'), key: 'owner', sortable: false },
    {
      title: t('tenant_details.list.table.bu_type'),
      key: 'bu_type',
      sortable: false
    },
    { title: t('subscription.plan.table.name'), key: 'plan', sortable: true },
    { title: t('form.status'), key: 'is_active', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  const typeOptions = [
    {
      value: 'Restaurant',
      label: t('tenant_details.list.bu_types.restaurant'),
      icon: 'mdi-silverware-fork-knife',
      color: 'orange'
    },
    {
      value: 'Coffee Shop',
      label: t('tenant_details.list.bu_types.cafe'),
      icon: 'mdi-coffee',
      color: 'brown'
    },
    {
      value: 'bakery',
      label: t('tenant_details.list.bu_types.bakery'),
      icon: 'mdi-bread-slice-outline',
      color: 'amber'
    },
    {
      value: 'kiosk',
      label: t('tenant_details.list.bu_types.kiosk'),
      icon: 'mdi-store-outline',
      color: 'teal'
    },
    {
      value: 'food_truck',
      label: t('tenant_details.list.bu_types.food_truck'),
      icon: 'mdi-truck-outline',
      color: 'cyan'
    },
    {
      value: 'Mart',
      label: t('tenant_details.list.bu_types.mini_mart'),
      icon: 'mdi-shopping-outline',
      color: 'primary'
    },
    {
      value: 'retail',
      label: t('tenant_details.list.bu_types.retail'),
      icon: 'mdi-tag-outline',
      color: 'indigo'
    },
    {
      value: 'wholesale',
      label: t('tenant_details.list.bu_types.wholesale'),
      icon: 'mdi-warehouse',
      color: 'purple'
    }
  ]

  const getBuType = value =>
    typeOptions.find(t => t.value === value) ?? {
      label: value,
      icon: 'mdi-store',
      color: 'grey'
    }

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    router.push({ name: 'tenant-create' })
  }

  const openEdit = t => {
    router.push({ name: 'tenant-edit', params: { id: t.id } })
  }

  const openManageSubscription = tenant => {
    router.push({ name: 'tenant-subscription', params: { id: tenant.id }, query: { tenantName: tenant.name } })
  }

  const openUsersDialog = tenant => {
    usersTarget.value = tenant
    usersDialog.value = true
  }

  const loginAsTenant = async tenant => {
    impersonatingId.value = tenant.id
    try {
      await authStore.impersonateTenant(tenant.id, tenant.name)
      router.push('/dashboard')
    } catch {
      notif(t('impersonation.error'), { type: 'error' })
    } finally {
      impersonatingId.value = null
    }
  }

  const toggleActive = async tenant => {
    saving.value = true
    try {
      await tenantStore.toggleTenantActive(tenant.id)
      tableRef.value?.refresh()
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = async tenant => {
    confirm({
      title: t('tenant_details.list.confirm_delete.title'),
      message: t('tenant_details.list.confirm_delete.message', {
        name: tenant.name
      }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        await tenantStore.deleteTenant(tenant.id)
        notif(t('messages.deleted_success'), { type: 'success' })
        tableRef.value?.refresh()
      },
      cancel: () => {}
    })
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const ownerInitials = owner => {
    if (!owner) return '?'
    return (
      (owner.first_name?.[0] || '') + (owner.last_name?.[0] || '')
    ).toUpperCase()
  }

  const planColor = plan =>
    ({ free: 'grey', starter: 'info', pro: 'primary', enterprise: 'warning' })[
      plan
    ] || 'grey'

  const planIcon = plan =>
    ({
      free: 'mdi-star-outline',
      starter: 'mdi-star-half-full',
      pro: 'mdi-star',
      enterprise: 'mdi-crown'
    })[plan] || 'mdi-star-outline'

  onMounted(() => {
    tenantStore.fetchBusinessTypes()
    planStore.fetchPlans()
  })
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
