<template>
  <v-container fluid class="pa-0">
    <v-row>
      <!-- Form — always visible, doubles as create (empty) and edit
           (populated by the table's edit action) — no separate Add button
           or dialog needed. -->
      <v-col v-if="can('branches.manage')" cols="12" md="4">
        <BranchFormPanel
          :branch="editingBranch"
          :loading="saving"
          @saved="handleSave"
          @cancel="editingBranch = null"
        />
      </v-col>

      <v-col cols="12" :md="can('branches.manage') ? 8 : 12">
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
                <div class="text-body-2 font-weight-medium text-truncate">
                  {{ item.name }}
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
                <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
              </div>
            </template>

            <!-- Actions -->
            <template #[`item.actions`]="{ item }">
              <v-menu location="bottom end">
                <template #activator="{ props: menuProps }">
                  <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="small" variant="text" />
                </template>
                <v-list density="compact" min-width="170">
                  <v-list-item prepend-icon="mdi-eye-outline" @click="onDetails(item)">
                    <v-list-item-title>{{ t('btn.view_details') }}</v-list-item-title>
                  </v-list-item>
                  <v-list-item prepend-icon="mdi-pencil-outline" @click="openEdit(item)">
                    <v-list-item-title>{{ t('btn.edit') }}</v-list-item-title>
                  </v-list-item>
                  <v-list-item prepend-icon="mdi-delete-outline" base-color="error" @click="handleDelete(item)">
                    <v-list-item-title>{{ t('btn.delete') }}</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </template>
          </AppTable>
        </v-card>
      </v-col>
    </v-row>

    <BranchDetailDialog v-model="drawer" :branch-id="selectedBranch?.id" />
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useBranchStore } from '@/stores/branchStore'
  import { usePermission } from '@/composables/usePermission'
  import { useAuthStore } from '@/stores/authStore'
  import { getAllBranchesApi } from '@/api/branchService'
  import { useAppUtils, AppTable, AppStatusChip } from '@nong-official-dev/core'
  import BranchFormPanel from '@/components/branches/BranchFormPanel.vue'
  import BranchDetailDialog from '@/components/branches/BranchDetailDialog.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  const { can } = usePermission()
  const { confirm, notif } = useAppUtils()
  const branchStore = useBranchStore()
  const authStore = useAuthStore()

  const tableRef = ref(null)
  const drawer = ref(false)
  const saving = ref(false)

  const selectedBranch = ref(null)
  // ── Raw filter input — tenant scoping applies immediately via the
  // `filters` computed below; AppTable deep-watches it and refetches. ──────────
  const filterState = reactive({
    tenant: null
  })
  // null = create mode (empty panel); set = editing that branch.
  const editingBranch = ref(null)

  // ── Headers ────────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('branches.table.name'), key: 'name', sortable: true },
    {
      title: t('branches.form.branch_type'),
      key: 'branch_type',
      sortable: false
    },
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
  // (search/sortBy/sortDesc/page/perPage + tenant). ──────────────────────────
  const filters = computed(() => ({
    tenant: filterState.tenant || undefined
  }))

  async function fetchBranches(params) {
    const { data } = await getAllBranchesApi(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openEdit = b => {
    editingBranch.value = { ...b }
  }

  const onDetails = b => {
    selectedBranch.value = b
    drawer.value = true
  }

  const handleSave = async branchData => {
    saving.value = true
    try {
      if (branchData.id) {
        await branchStore.updateBranch(branchData.id, branchData)
      } else {
        await branchStore.createBranch(branchData)
      }
      editingBranch.value = null
      tableRef.value?.refresh()
      notif(t('branches.messages.saved'))
    } finally {
      saving.value = false
    }
  }

  const handleDelete = branch => {
    confirm({
      title: t('branches.messages.delete_title'),
      message: t('branches.messages.delete_message', { name: branch.name }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        await branchStore.deleteBranch(branch.id)
        if (editingBranch.value?.id === branch.id) editingBranch.value = null
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

  onMounted(() => {
    // Branches are scoped server-side by the user's own tenant_id.
    filterState.tenant = authStore.tenant_id
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
