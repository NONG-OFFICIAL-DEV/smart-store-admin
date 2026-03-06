<template>
  <v-container fluid class="pa-0">
    <custom-title
      title="Branches"
      subtitle="Manage your branches"
      icon="mdi-map-marker-path"
    >
      <template #right>
        <v-btn
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

    <!-- ── Stats ──────────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-5">
      <v-col v-for="stat in stats" :key="stat.label" cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4 d-flex align-center gap-3">
          <v-avatar :color="stat.color" variant="tonal" rounded="lg" size="44">
            <v-icon :icon="stat.icon" size="20" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption text-grey">{{ stat.label }}</div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filters ─────────────────────────────────────────────────────────────── -->
    <v-row dense align="center" class="mb-4">
      <v-col cols="12" sm="4">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          placeholder="Search name, city, phone..."
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
        />
      </v-col>
      <v-col cols="12" sm="auto">
        <v-btn-toggle
          v-model="typeFilter"
          color="primary"
          variant="tonal"
          rounded="lg"
        >
          <v-btn
            v-for="t in branchTypes"
            :key="t.value"
            :value="t.value"
            size="small"
            class="text-none px-3"
          >
            <v-icon :icon="t.icon" size="15" class="mr-1" />
            {{ t.label }}
          </v-btn>
        </v-btn-toggle>
      </v-col>
    </v-row>

    <!-- ── Table ───────────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="filteredBranches"
        :loading="branchStore.loading"
        item-value="id"
        hover
      >

        <!-- Name + Address -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <v-avatar :color="typeColor(item.type)" variant="tonal" rounded="lg" size="38">
              <v-icon :icon="typeIcon(item.type)" size="18" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">{{ item.name }}</div>
              <div class="text-caption text-grey">
                {{ item.address_line1 }}
                <span v-if="item.address_line2">, {{ item.address_line2 }}</span>
              </div>
            </div>
          </div>
        </template>

        <!-- Tenant -->
        <template #item.tenant.name="{ item }">
          <v-chip
            v-if="item.tenant"
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-domain"
          >
            {{ item.tenant.name }}
          </v-chip>
          <span v-else class="text-grey">—</span>
        </template>

        <!-- Type -->
        <template #item.type="{ item }">
          <v-chip
            :color="typeColor(item.type)"
            :prepend-icon="typeIcon(item.type)"
            size="small"
            variant="tonal"
            class="text-capitalize"
          >
            {{ item.type?.replace('_', ' ') }}
          </v-chip>
        </template>

        <!-- Location -->
        <template #item.city="{ item }">
          <div class="d-flex align-center gap-1">
            <v-icon icon="mdi-map-marker-outline" size="14" color="grey" />
            <div>
              <div class="text-body-2">{{ item.city }}</div>
              <div class="text-caption text-grey">{{ item.country }}</div>
            </div>
          </div>
        </template>

        <!-- Contact -->
        <template #item.phone="{ item }">
          <div>
            <div v-if="item.phone" class="d-flex align-center gap-1 text-body-2">
              <v-icon icon="mdi-phone-outline" size="13" color="grey" />
              {{ item.phone }}
            </div>
            <div v-if="item.email" class="d-flex align-center gap-1 text-caption text-grey">
              <v-icon icon="mdi-email-outline" size="13" />
              {{ item.email }}
            </div>
            <span v-if="!item.phone && !item.email" class="text-grey">—</span>
          </div>
        </template>

        <!-- Tax + Service charge -->
        <template #item.tax_rate="{ item }">
          <v-chip size="x-small" variant="tonal" color="orange" class="mr-1">
            TAX {{ formatRate(item.tax_rate) }}
          </v-chip>
          <v-chip v-if="item.service_charge_rate" size="x-small" variant="tonal" color="teal">
            SVC {{ formatRate(item.service_charge_rate) }}
          </v-chip>
        </template>

        <!-- Open -->
        <template #item.is_open="{ item }">
          <v-chip
            :color="item.is_open ? 'success' : 'error'"
            size="small"
            variant="tonal"
            :prepend-icon="item.is_open ? 'mdi-door-open' : 'mdi-door-closed'"
          >
            {{ item.is_open ? 'Open' : 'Closed' }}
          </v-chip>
        </template>

        <!-- Status -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
            variant="tonal"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            variant="text"
            size="small"
            @click="openEdit(item)"
          />
          <v-btn
            v-if="can('branches.delete')"
            icon="mdi-delete-outline"
            variant="text"
            size="small"
            color="error"
            @click="handleDelete(item)"
          />
        </template>

        <!-- Empty -->
        <template #no-data>
          <div class="text-center py-12">
            <v-icon icon="mdi-store-off-outline" size="56" color="grey-lighten-1" class="mb-3" />
            <p class="text-h6 text-medium-emphasis mb-1">No branches found</p>
            <v-btn
              color="primary" variant="tonal"
              prepend-icon="mdi-plus" class="mt-2"
              @click="openCreate"
            >
              Add First Branch
            </v-btn>
          </div>
        </template>

      </v-data-table>
    </v-card>

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
import { useBranchStore }  from '@/stores/branchStore'
import { usePermission }   from '@/composables/usePermission'
import { useAppUtils }     from '@nong-official-dev/core'
import BranchDialog        from '@/components/branches/BranchDialog.vue'

const { can, isSuperAdmin } = usePermission()
const { confirm, notif }    = useAppUtils()
const branchStore           = useBranchStore()

const search     = ref('')
const typeFilter = ref(null)

const dialog = reactive({ show: false, branch: null })

// ── Branch types ──────────────────────────────────────────────────────────────
const branchTypes = [
  { value: 'restaurant', label: 'Restaurant', icon: 'mdi-silverware-fork-knife' },
  { value: 'cafe',       label: 'Cafe',       icon: 'mdi-coffee-outline'        },
  { value: 'kiosk',      label: 'Kiosk',      icon: 'mdi-storefront-outline'    },
  { value: 'food_truck', label: 'Food Truck',  icon: 'mdi-truck-outline'         },
]

// ── Normalise branches — handle both array and paginated {data:[]} ────────────
const allBranches = computed(() => {
  const b = branchStore.branches
  return Array.isArray(b) ? b : (b?.data ?? [])
})

// ── Stats ─────────────────────────────────────────────────────────────────────
const stats = computed(() => [
  {
    label: 'Total',        icon: 'mdi-store-outline',
    color: 'primary',  value: allBranches.value.length,
  },
  {
    label: 'Active',       icon: 'mdi-check-circle-outline',
    color: 'success',  value: allBranches.value.filter(b => b.is_active).length,
  },
  {
    label: 'Open Now',     icon: 'mdi-door-open',
    color: 'teal',     value: allBranches.value.filter(b => b.is_open).length,
  },
  {
    label: 'Inactive',     icon: 'mdi-minus-circle-outline',
    color: 'error',    value: allBranches.value.filter(b => !b.is_active).length,
  },
])

// ── Headers — tenant column only for super admin ──────────────────────────────
const headers = computed(() => [
  { title: 'Branch',    key: 'name',       sortable: true  },
  ...(isSuperAdmin() ? [
    { title: 'Tenant',  key: 'tenant.name', sortable: false },
  ] : []),
  { title: 'Type',      key: 'type',       sortable: true  },
  { title: 'Location',  key: 'city',       sortable: true  },
  { title: 'Contact',   key: 'phone',      sortable: false },
  { title: 'Tax / Svc', key: 'tax_rate',   sortable: false },
  { title: 'Open',      key: 'is_open',    sortable: false },
  { title: 'Status',    key: 'is_active',  sortable: false },
  { title: 'Actions',   key: 'actions',    sortable: false },
])

// ── Filtered ──────────────────────────────────────────────────────────────────
const filteredBranches = computed(() => {
  let list = allBranches.value
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(b =>
      b.name?.toLowerCase().includes(q) ||
      b.city?.toLowerCase().includes(q) ||
      b.phone?.toLowerCase().includes(q) ||
      b.email?.toLowerCase().includes(q)
    )
  }
  if (typeFilter.value) {
    list = list.filter(b => b.type === typeFilter.value)
  }
  return list
})

// ── Actions ───────────────────────────────────────────────────────────────────
const openCreate = () => { dialog.branch = null; dialog.show = true }
const openEdit   = (b) => { dialog.branch = { ...b }; dialog.show = true }

const handleSave = async (branchData) => {
  if (branchData.id) {
    await branchStore.updateBranch(branchData.id, branchData)
  } else {
    await branchStore.createBranch(branchData)
  }
  await branchStore.fetchBranches()
  notif('Branch saved successfully')
}

const handleDelete = (branch) => {
  confirm({
    title  : 'Delete Branch',
    message: `Are you sure you want to delete "${branch.name}"?`,
    options: { type: 'warning', width: 550 },
    agree  : async () => {
      await branchStore.deleteBranch(branch.id)
      await branchStore.fetchBranches()
      notif('Branch deleted')
    },
    cancel: () => {},
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const typeColor = (type) => ({
  restaurant: 'primary',
  cafe      : 'brown',
  kiosk     : 'teal',
  food_truck: 'orange',
}[type] || 'grey')

const typeIcon = (type) => ({
  restaurant: 'mdi-silverware-fork-knife',
  cafe      : 'mdi-coffee-outline',
  kiosk     : 'mdi-storefront-outline',
  food_truck: 'mdi-truck-outline',
}[type] || 'mdi-store-outline')

const formatRate = (rate) =>
  rate ? (parseFloat(rate) * 100).toFixed(1) + '%' : '0%'

onMounted(() => branchStore.fetchBranches())
</script>

<style scoped>
.gap-1 { gap: 4px; }
.gap-3 { gap: 12px; }
</style>