<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap ga-3 mb-4">
      <span class="text-h6 font-weight-bold">{{ t('branches.title') }}</span>
      <div class="d-flex align-center flex-wrap ga-2">
        <v-text-field
          v-model="search"
          :placeholder="t('branches.search_placeholder')"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
          style="min-width: 220px"
        />
        <v-btn
          v-if="can('branches.manage')"
          color="primary"
          variant="flat"
          rounded="lg"
          class="text-none"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ t('branches.add_branch') }}
        </v-btn>
      </div>
    </div>

    <v-card rounded="lg">
      <v-list v-if="filteredBranches.length" lines="two">
        <template v-for="(b, i) in filteredBranches" :key="b.id">
          <v-list-item>
            <template #prepend>
              <v-avatar :color="typeColor(b.branch_type?.name)" variant="tonal" rounded="lg" size="40" class="me-3">
                <v-icon :icon="typeIcon(b.branch_type?.name)" size="20" />
              </v-avatar>
            </template>

            <v-list-item-title class="d-flex align-center ga-2 flex-wrap">
              <span class="font-weight-medium">{{ b.name }}</span>
              <v-chip
                v-if="b.branch_type?.name"
                size="x-small"
                variant="tonal"
                :color="typeColor(b.branch_type.name)"
                class="text-capitalize"
              >
                {{ b.branch_type.name.replace('_', ' ') }}
              </v-chip>
            </v-list-item-title>

            <v-list-item-subtitle>
              <div v-if="b.address_line1" class="text-truncate">{{ b.address_line1 }}</div>
              <div class="d-flex ga-3 flex-wrap text-caption">
                <span v-if="b.phone" class="d-flex align-center ga-1">
                  <v-icon icon="mdi-phone-outline" size="13" />{{ b.phone }}
                </span>
                <span v-if="b.email" class="d-flex align-center ga-1">
                  <v-icon icon="mdi-email-outline" size="13" />{{ b.email }}
                </span>
                <span v-if="!b.address_line1 && !b.phone && !b.email">—</span>
              </div>
            </v-list-item-subtitle>

            <template #append>
              <div class="d-flex align-center ga-2">
                <v-chip :color="statusColor(b)" variant="tonal" size="small" prepend-icon="mdi-circle">
                  {{ statusLabel(b) }}
                </v-chip>
                <v-btn
                  v-if="can('branches.manage')"
                  variant="tonal"
                  rounded="lg"
                  size="small"
                  class="text-none"
                  @click="openEdit(b)"
                >
                  {{ t('btn.edit') }}
                </v-btn>
                <v-menu location="bottom end">
                  <template #activator="{ props: menuProps }">
                    <v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="small" variant="text" />
                  </template>
                  <v-list density="compact" min-width="170">
                    <v-list-item prepend-icon="mdi-eye-outline" @click="onDetails(b)">
                      <v-list-item-title>{{ t('btn.view_details') }}</v-list-item-title>
                    </v-list-item>
                    <v-list-item
                      v-if="can('branches.manage')"
                      prepend-icon="mdi-delete-outline"
                      base-color="error"
                      @click="handleDelete(b)"
                    >
                      <v-list-item-title>{{ t('btn.delete') }}</v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-menu>
              </div>
            </template>
          </v-list-item>
          <v-divider v-if="i < filteredBranches.length - 1" />
        </template>
      </v-list>

      <div v-else class="pa-8 text-center text-medium-emphasis">
        <v-icon icon="mdi-store-search-outline" size="36" class="mb-2" />
        <div>{{ search ? t('branches.no_results') : t('branches.empty') }}</div>
        <div v-if="!search" class="text-caption">{{ t('branches.empty_sub') }}</div>
      </div>
    </v-card>

    <p class="text-caption text-medium-emphasis mt-3 mb-0">
      {{ t('branches.status_help') }}
    </p>

    <!-- Rendered at the page root, not nested in the list's layout, so it
         always covers the full screen regardless of where it's triggered
         from. -->
    <BranchFormDrawer v-model="formOpen" :branch="editingBranch" :loading="saving" @saved="handleSave" />

    <BranchDetailDialog v-model="detailOpen" :branch-id="selectedBranch?.id" />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useBranchStore } from '@/stores/branchStore'
  import { usePermission } from '@/composables/usePermission'
  import { useAppUtils } from '@nong-official-dev/core'
  import BranchFormDrawer from '@/components/branches/BranchFormDrawer.vue'
  import BranchDetailDialog from '@/components/branches/BranchDetailDialog.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  const { can } = usePermission()
  const { confirm, notif } = useAppUtils()
  const branchStore = useBranchStore()

  const saving = ref(false)
  const search = ref('')

  const formOpen = ref(false)
  const detailOpen = ref(false)
  const editingBranch = ref(null)
  const selectedBranch = ref(null)

  const filteredBranches = computed(() => {
    const q = search.value?.trim().toLowerCase()
    if (!q) return branchStore.branches
    return branchStore.branches.filter(b =>
      [b.name, b.address_line1, b.phone, b.email].some(v => v?.toLowerCase().includes(q))
    )
  })

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    editingBranch.value = null
    formOpen.value = true
  }

  const openEdit = b => {
    editingBranch.value = { ...b }
    formOpen.value = true
  }

  const onDetails = b => {
    selectedBranch.value = b
    detailOpen.value = true
  }

  const handleSave = async branchData => {
    saving.value = true
    try {
      if (branchData.id) {
        await branchStore.updateBranch(branchData.id, branchData)
      } else {
        await branchStore.createBranch(branchData)
      }
      formOpen.value = false
      await branchStore.fetchBranches({ perPage: -1 })
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
        await branchStore.fetchBranches({ perPage: -1 })
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

  const statusColor = b => (!b.is_active ? 'grey' : b.is_open ? 'success' : 'warning')
  const statusLabel = b =>
    !b.is_active
      ? t('branches.status.disabled')
      : b.is_open
        ? t('branches.status.open')
        : t('branches.status.closed')

  onMounted(() => {
    if (!branchStore.branches.length) branchStore.fetchBranches({ perPage: -1 })
  })
</script>
