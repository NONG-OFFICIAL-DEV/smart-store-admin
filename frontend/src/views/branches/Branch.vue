<template>
  <v-container fluid class="pa-0">
    <custom-title
      :title="t('branches.title')"
      :subtitle="t('branches.subtitle')"
      icon="mdi-map-marker-path"
    >
      <template #right>
        <v-btn
          v-if="can('branches.manage')"
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          class="ms-2"
          @click="openCreate"
        >
          {{ t('btn.add_branch') }}
        </v-btn>
      </template>
    </custom-title>
    <!-- ── Filters — tenant/branch-type/status apply immediately; AppTable
    deep-watches `filters` below and refetches, resetting to page 1. Text
    search is AppTable's own built-in search box, not this panel. ────────── -->
    <v-row dense class="mb-2">
      <v-col v-if="!isTenantUser" cols="12" md="3" sm="3">
        <custom-select
          v-model="filterState.tenant"
          :items="tenants"
          item-title="name"
          item-value="id"
          :label="t('menu.tenant')"
          :multiple="false"
          :chips="true"
          :max-visible-chips="3"
        />
      </v-col>
      <v-col cols="12" md="3" sm="3">
        <custom-select
          v-model="filterState.branchType"
          :items="branchTypes"
          item-title="name"
          item-value="id"
          :label="t('branches.form.branch_type')"
          :multiple="true"
          :chips="true"
          :max-visible-chips="1"
          :disabled="!filterState.tenant"
          :hint="!filterState.tenant ? t('branches.select_tenant_first') : ''"
          persistent-hint
        />
      </v-col>
      <v-col cols="12" md="3" sm="3">
        <v-select
          v-model="filterState.status"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          :placeholder="t('branches.all_status')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
    </v-row>

    <!-- Table Card -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchBranches"
        :filters="filters"
        item-label="branches"
      >
        <!-- Name + address -->
        <template #[`item.name`]="{ item }">
          <div class="d-flex align-center gap-3 py-1">
            <v-avatar
              :color="typeColor(item.branch_type?.name)"
              variant="tonal"
              rounded="lg"
              size="36"
            >
              <v-icon :icon="typeIcon(item.branch_type?.name)" size="18" />
            </v-avatar>
            <div style="min-width: 0">
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ item.name }}
              </div>
              <div class="text-caption text-grey text-truncate">
                {{ item.full_address }}
              </div>
            </div>
          </div>
        </template>

        <!-- Branch type -->
        <template #[`item.branch_type`]="{ item }">
          <v-chip
            v-if="item.branch_type?.name"
            :color="typeColor(item.branch_type.name)"
            size="x-small"
            variant="tonal"
            class="text-capitalize"
          >
            {{ item.branch_type.name.replace('_', ' ') }}
          </v-chip>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <!-- Tenant (superadmin only column) -->
        <template #[`item.tenant`]="{ item }">
          <v-chip
            v-if="item.tenant"
            size="x-small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-domain"
          >
            {{ item.tenant.name }}
          </v-chip>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <!-- Contact -->
        <template #[`item.contact`]="{ item }">
          <div class="text-caption text-medium-emphasis">
            <div v-if="item.phone" class="d-flex align-center gap-1">
              <v-icon icon="mdi-phone-outline" size="13" />
              {{ item.phone }}
            </div>
            <div v-if="item.email" class="d-flex align-center gap-1">
              <v-icon icon="mdi-email-outline" size="13" />
              {{ item.email }}
            </div>
            <span v-if="!item.phone && !item.email">—</span>
          </div>
        </template>

        <!-- Status -->
        <template #[`item.is_active`]="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <v-chip
              :color="item.is_open ? 'success' : 'error'"
              size="x-small"
              variant="tonal"
              :prepend-icon="item.is_open ? 'mdi-circle' : 'mdi-circle-outline'"
            >
              {{
                item.is_open
                  ? t('tenant_details.branch_open')
                  : t('tenant_details.branch_closed')
              }}
            </v-chip>
            <v-chip
              :color="item.is_active ? 'primary' : 'default'"
              size="x-small"
              variant="tonal"
            >
              {{ item.is_active ? t('status.active') : t('status.inactive') }}
            </v-chip>
          </div>
        </template>

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex align-center gap-1">
            <v-btn
              icon="mdi-arrow-right-circle"
              size="small"
              variant="text"
              color="primary"
              @click="onDetails(item)"
            />
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              @click="openEdit(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="handleDelete(item)"
            />
          </div>
        </template>
      </AppTable>
    </v-card>
    <!-- Branch Dialog — keep your existing component name -->
    <BranchDialog
      v-if="dialog.show"
      v-model="dialog.show"
      :branch="dialog.branch"
      @saved="handleSave"
    />
    <v-navigation-drawer
      v-model="drawer"
      :location="$vuetify.display.mdAndDown ? 'bottom' : 'right'"
      temporary
      width="450"
    >
      <BranchDetail
        v-if="selectedBranch"
        :branch-id="selectedBranch.id"
        @close="drawer = false"
      />
    </v-navigation-drawer>
  </v-container>
</template>

<script setup>
  import { ref, watch, reactive, computed, onMounted } from 'vue'
  import { useBranchStore } from '@/stores/branchStore'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'
  import { useAuthStore } from '@/stores/authStore'
  import { getAllBranchesApi } from '@/api/branchService'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import BranchDialog from '@/components/branches/BranchDialog.vue'
  import BranchDetail from '@/components/branches/BranchDetail.vue'
  import CustomSelect from '@/components/customs/CustomSelect.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  const { can, isSuperAdmin } = usePermission()
  const { confirm, notif } = useAppUtils()
  const branchStore = useBranchStore()
  const tenantStore = useTenantStore()
  const authStore = useAuthStore()

  const tableRef = ref(null)
  const drawer = ref(false)

  const selectedBranch = ref(null)
  // ── Raw filter inputs — tenant/branchType/status apply immediately via the
  // `filters` computed below; AppTable deep-watches it and refetches. ──────────
  const filterState = reactive({
    branchType: [],
    tenant: null,
    status: null
  })
  const isTenantUser = computed(() => !!authStore.tenant_id)
  const statusOptions = computed(() => [
    { title: t('status.active'), value: 'Active' },
    { title: t('status.inactive'), value: 'Inactive' }
  ])
  const dialog = reactive({ show: false, branch: null })
  const tenants = computed(() => tenantStore.tenants)
  const branchTypes = computed(() => tenantStore.branchTypes ?? [])

  // ── Headers ────────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('branches.table.name'), key: 'name', sortable: true },
    {
      title: t('branches.form.branch_type'),
      key: 'branch_type',
      sortable: false
    },
    ...(isSuperAdmin()
      ? [{ title: t('menu.tenant'), key: 'tenant', sortable: false }]
      : []),
    { title: t('branches.table.phone'), key: 'contact', sortable: false },
    { title: t('branches.table.status'), key: 'is_active', sortable: true },
    ...(can('branches.manage')
      ? [
          {
            title: t('branches.table.actions'),
            key: 'actions',
            sortable: false,
            align: 'start'
          }
        ]
      : [])
  ])

  // ── Server-driven filters — matches BranchRepository's contract
  // (search/sortBy/sortDesc/page/perPage + tenant/branch_type/is_active). ──────
  const filters = computed(() => ({
    tenant: filterState.tenant || undefined,
    branch_type: filterState.branchType?.length
      ? filterState.branchType.join(',')
      : undefined,
    is_active:
      filterState.status === 'Active'
        ? true
        : filterState.status === 'Inactive'
          ? false
          : undefined
  }))

  async function fetchBranches(params) {
    const { data } = await getAllBranchesApi(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Watch tenant ──────────────────────────────────────────────────────────────
  // Note: tenants list & branch-type-by-business-type are superadmin-only
  // endpoints — tenant-logged-in users never have a list to search, so this
  // simply no-ops for them (they get Forbidden if we call it directly).
  watch(
    () => filterState.tenant,
    async tenantId => {
      filterState.branchType = []
      tenantStore.branchTypes = []

      if (!tenantId || isTenantUser.value) return

      const allTenants = tenantStore.tenants?.data ?? tenantStore.tenants ?? []
      const tenant = allTenants.find(t => t.id === tenantId)

      if (tenant?.business_type_id) {
        await tenantStore.fetchBranchTypeByBusinessType(tenant.business_type_id)
      }
    }
  )

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    dialog.branch = null
    dialog.show = true
  }
  const openEdit = b => {
    dialog.branch = { ...b }
    dialog.show = true
  }

  const onDetails = b => {
    selectedBranch.value = b
    drawer.value = true
  }

  const handleSave = async branchData => {
    if (branchData.id) {
      await branchStore.updateBranch(branchData.id, branchData)
    } else {
      await branchStore.createBranch(branchData)
    }
    tableRef.value?.refresh()
    notif(t('branches.messages.saved'))
  }

  const handleDelete = branch => {
    confirm({
      title: t('branches.messages.delete_title'),
      message: t('branches.messages.delete_message', { name: branch.name }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        await branchStore.deleteBranch(branch.id)
        tableRef.value?.refresh()
        notif(t('branches.messages.deleted'))
      },
      cancel: () => {}
    })
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const typeColor = type =>
    ({
      restaurant: 'primary',
      cafe: 'brown',
      kiosk: 'teal',
      food_truck: 'orange'
    })[type] || 'grey'

  const typeIcon = type =>
    ({
      restaurant: 'mdi-silverware-fork-knife',
      cafe: 'mdi-coffee-outline',
      kiosk: 'mdi-storefront-outline',
      food_truck: 'mdi-truck-outline'
    })[type] || 'mdi-store-outline'

  onMounted(async () => {
    if (isTenantUser.value) {
      // Tenant users get their branches scoped server-side by their own
      // tenant_id — no need to (and no permission to) load the full tenant
      // list, which is a superadmin-only endpoint.
      filterState.tenant = authStore.tenant_id
    } else {
      await tenantStore.fetchTenants()
    }
  })
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
