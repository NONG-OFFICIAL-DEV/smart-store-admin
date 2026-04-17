<template>
  <v-container fluid class="pa-0">
    <custom-title
      :title="t('branches.title')"
      :subtitle="t('branches.subtitle')"
      icon="mdi-map-marker-path"
    >
      <template #right>
        <v-btn
          v-if="isSuperAdmin()"
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreate"
        >
          Add Branch
        </v-btn>
      </template>
    </custom-title>
    <!-- ── Filters ─────────────────────────────────────────────────────────────── -->

    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <v-text-field
        v-model="search"
        placeholder="Search branch name or city..."
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        density="compact"
        hide-details
        rounded="lg"
        style="max-width: 300px"
      />
      <v-select
        v-model="filterType"
        :items="typeOptions"
        placeholder="All types"
        variant="outlined"
        density="compact"
        hide-details
        rounded="lg"
        clearable
        style="max-width: 160px"
      />
      <v-select
        v-model="filterStatus"
        :items="['Active', 'Inactive']"
        placeholder="All status"
        variant="outlined"
        density="compact"
        hide-details
        rounded="lg"
        clearable
        style="max-width: 140px"
      />
    </div>

    <!-- Card grid -->
    <v-row dense>
      <v-col
        v-for="branch in filteredBranches"
        :key="branch.id"
        cols="12"
        sm="6"
        lg="4"
      >
        <v-card rounded="lg" elevation="0" border hover>
          <!-- Top: avatar + name -->
          <v-card-text class="pb-2">
            <div class="d-flex align-start gap-3">
              <v-avatar
                :color="typeColor(branch.type)"
                variant="tonal"
                rounded="lg"
                size="40"
              >
                <v-icon :icon="typeIcon(branch.type)" size="20" />
              </v-avatar>
              <div style="min-width: 0">
                <div class="text-body-2 font-weight-bold text-truncate">
                  {{ branch.name }}
                </div>
                <div class="text-caption text-grey text-truncate">
                  {{ branch.address_line1 }}
                </div>
              </div>
            </div>

            <!-- Badges -->
            <div class="d-flex gap-2 flex-wrap mt-3 m">
              <v-chip
                :color="typeColor(branch.type)"
                size="x-small"
                variant="tonal"
                class="text-capitalize me-1"
              >
                {{ branch.type?.replace('_', ' ') }}
              </v-chip>
              <v-chip
                :color="branch.is_open ? 'success' : 'error'"
                size="x-small"
                variant="tonal"
                class="me-1"
                :prepend-icon="
                  branch.is_open ? 'mdi-circle' : 'mdi-circle-outline'
                "
              >
                {{ branch.is_open ? 'Open' : 'Closed' }}
              </v-chip>
              <v-chip
                :color="branch.is_active ? 'primary' : 'default'"
                size="x-small"
                variant="tonal"
              >
                {{ branch.is_active ? 'Active' : 'Inactive' }}
              </v-chip>
            </div>
          </v-card-text>

          <v-divider />

          <!-- Meta info -->
          <v-card-text class="py-2">
            <div
              class="d-flex align-center gap-2 text-caption text-medium-emphasis mb-1"
            >
              <v-icon icon="mdi-map-marker-outline" size="13" />
              {{ branch.city }}, {{ branch.country }}
            </div>
            <div
              v-if="branch.phone"
              class="d-flex align-center gap-2 text-caption text-medium-emphasis mb-1"
            >
              <v-icon icon="mdi-phone-outline" size="13" />
              {{ branch.phone }}
            </div>
            <div
              v-if="branch.email"
              class="d-flex align-center gap-2 text-caption text-medium-emphasis"
            >
              <v-icon icon="mdi-email-outline" size="13" />
              {{ branch.email }}
            </div>
          </v-card-text>

          <v-divider />

          <!-- Footer: tenant + actions -->
          <v-card-actions
            class="px-3 py-2"
            style="background: rgba(0, 0, 0, 0.02)"
          >
            <v-chip
              v-if="branch.tenant && isSuperAdmin()"
              size="x-small"
              variant="tonal"
              color="primary"
              prepend-icon="mdi-domain"
            >
              {{ branch.tenant.name }}
            </v-chip>
            <v-spacer />
            <v-btn
              v-if="can('branches.details')"
              icon="mdi-arrow-right-circle"
              size="small"
              variant="text"
              color="primary"
              @click="router.push(`/branches/${branch.id}`)"
            />
            <v-btn
              v-if="isSuperAdmin()"
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              @click="openEdit(branch)"
            />
            <v-btn
              v-if="isSuperAdmin()"
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="handleDelete(branch)"
            />
          </v-card-actions>
        </v-card>
      </v-col>

      <!-- Empty state -->
      <v-col v-if="!filteredBranches.length" cols="12">
        <div class="text-center py-12">
          <v-icon
            icon="mdi-store-off-outline"
            size="56"
            color="grey-lighten-1"
            class="mb-3"
          />
          <p class="text-h6 text-medium-emphasis mb-1">No branches found</p>
          <v-btn
            color="primary"
            variant="tonal"
            prepend-icon="mdi-plus"
            class="mt-2"
            @click="openCreate"
          >
            Add first branch
          </v-btn>
        </div>
      </v-col>
    </v-row>
    <!-- Branch Dialog — keep your existing component name -->
    <BranchDialog
      v-if="dialog.show"
      v-model="dialog.show"
      :branch="dialog.branch"
      @saved="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useBranchStore } from '@/stores/branchStore'
  import { usePermission } from '@/composables/usePermission'
  import { useAppUtils } from '@nong-official-dev/core'
  import BranchDialog from '@/components/branches/BranchDialog.vue'
  import { useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const router = useRouter()

  const { can, isSuperAdmin } = usePermission()
  const { confirm, notif } = useAppUtils()
  const branchStore = useBranchStore()

  const search = ref('')
  const filterType = ref(null)
  const filterStatus = ref(null)

  const dialog = reactive({ show: false, branch: null })

  // ── Branch types ──────────────────────────────────────────────────────────────
  const typeOptions = [
    'retail',
    'minimart',
    'wholesale',
    'restaurant',
    'cafe',
    'bakery',
    'kiosk',
    'food_truck'
  ]

  // ── Normalise branches — handle both array and paginated {data:[]} ────────────
  const allBranches = computed(() => {
    const b = branchStore.branches
    return Array.isArray(b) ? b : (b?.data ?? [])
  })

  // ── Filtered ──────────────────────────────────────────────────────────────────
  const filteredBranches = computed(() => {
    let list = allBranches.value
    if (search.value) {
      const q = search.value.toLowerCase()
      list = list.filter(
        b =>
          b.name?.toLowerCase().includes(q) ||
          b.city?.toLowerCase().includes(q) ||
          b.phone?.toLowerCase().includes(q) ||
          b.email?.toLowerCase().includes(q)
      )
    }
    if (filterType.value) {
      list = list.filter(b => b.type === filterType.value)
    }
    return list
  })

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    dialog.branch = null
    dialog.show = true
  }
  const openEdit = b => {
    dialog.branch = { ...b }
    dialog.show = true
  }

  const handleSave = async branchData => {
    if (branchData.id) {
      await branchStore.updateBranch(branchData.id, branchData)
    } else {
      await branchStore.createBranch(branchData)
    }
    await branchStore.fetchBranches()
    notif('Branch saved successfully')
  }

  const handleDelete = branch => {
    confirm({
      title: 'Delete Branch',
      message: `Are you sure you want to delete "${branch.name}"?`,
      options: { type: 'warning', width: 550 },
      agree: async () => {
        await branchStore.deleteBranch(branch.id)
        await branchStore.fetchBranches()
        notif('Branch deleted')
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

  const formatRate = rate =>
    rate ? (parseFloat(rate) * 100).toFixed(1) + '%' : '0%'

  onMounted(() => branchStore.fetchBranches())
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
