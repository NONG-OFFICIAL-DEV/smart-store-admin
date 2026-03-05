<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-office-building-marker"
      title="Tenants"
      subtitle="Manage tenant profiles and subscription plans"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreate"
        >
          Add Tenant
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Stats ─────────────────────────────────────────────────────────────── -->
    <v-row class="mb-5" dense>
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

    <!-- ── Filters ────────────────────────────────────────────────────────────── -->
    <v-row dense align="center" class="mb-4">
      <v-col cols="12" sm="4">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          placeholder="Search by name or slug..."
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
        />
      </v-col>
      <v-col cols="12" sm="auto">
        <v-btn-toggle v-model="planFilter" color="primary" variant="tonal" rounded="lg">
          <v-btn value=""           size="small" class="text-none px-3">All</v-btn>
          <v-btn value="free"       size="small" class="text-none px-3">Free</v-btn>
          <v-btn value="starter"    size="small" class="text-none px-3">Starter</v-btn>
          <v-btn value="pro"        size="small" class="text-none px-3">Pro</v-btn>
          <v-btn value="enterprise" size="small" class="text-none px-3">Enterprise</v-btn>
        </v-btn-toggle>
      </v-col>
      <v-col cols="12" sm="auto">
        <v-btn-toggle v-model="activeFilter" color="primary" variant="tonal" rounded="lg">
          <v-btn value=""    size="small" class="text-none px-3">All</v-btn>
          <v-btn value="1"   size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="success" class="mr-1" />Active
          </v-btn>
          <v-btn value="0"   size="small" class="text-none px-3">
            <v-icon icon="mdi-circle" size="10" color="error" class="mr-1" />Suspended
          </v-btn>
        </v-btn-toggle>
      </v-col>
    </v-row>

    <!-- ── Table ──────────────────────────────────────────────────────────────── -->
    <v-card rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="filteredTenants"
        :loading="loading"
        item-value="id"
        hover
        rounded="xl"
      >

        <!-- Logo + Name -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3">
            <v-avatar size="36" rounded="lg" :color="item.primary_color || 'primary'" variant="tonal">
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
              <span style="font-size:10px; font-weight:700">
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

        <!-- Plan -->
        <template #item.plan="{ item }">
          <v-chip :color="planColor(item.plan)" variant="tonal" size="small" :prepend-icon="planIcon(item.plan)">
            {{ item.plan }}
          </v-chip>
        </template>

        <!-- Currency + Timezone -->
        <template #item.locale="{ item }">
          <div class="text-caption">
            <v-chip size="x-small" variant="tonal" color="grey" class="mr-1">{{ item.currency }}</v-chip>
            <span class="text-grey">{{ item.timezone }}</span>
          </div>
        </template>

        <!-- Status -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
            variant="tonal"
            :prepend-icon="item.is_active ? 'mdi-check-circle-outline' : 'mdi-minus-circle-outline'"
          >
            {{ item.is_active ? 'Active' : 'Suspended' }}
          </v-chip>
        </template>

        <!-- Plan expires -->
        <template #item.plan_expires_at="{ item }">
          <span v-if="item.plan_expires_at" class="text-caption" :class="isExpiringSoon(item) ? 'text-warning font-weight-bold' : 'text-grey'">
            <v-icon v-if="isExpiringSoon(item)" icon="mdi-alert-outline" size="13" class="mr-1" />
            {{ formatDate(item.plan_expires_at) }}
          </span>
          <span v-else class="text-caption text-grey">—</span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-end">
            <!-- Edit -->
            <v-btn
              icon="mdi-pencil-outline"
              variant="text"
              size="small"
              @click="openEdit(item)"
            />

            <!-- Suspend / Activate -->
            <v-tooltip :text="item.is_active ? 'Suspend tenant' : 'Activate tenant'">
              <template #activator="{ props: ttProps }">
                <v-btn
                  v-bind="ttProps"
                  :icon="item.is_active ? 'mdi-pause-circle-outline' : 'mdi-play-circle-outline'"
                  :color="item.is_active ? 'warning' : 'success'"
                  variant="text"
                  size="small"
                  @click="toggleActive(item)"
                />
              </template>
            </v-tooltip>

            <!-- Transfer ownership -->
            <v-tooltip text="Transfer ownership">
              <template #activator="{ props: ttProps }">
                <v-btn
                  v-bind="ttProps"
                  icon="mdi-swap-horizontal"
                  color="info"
                  variant="text"
                  size="small"
                  @click="openTransfer(item)"
                />
              </template>
            </v-tooltip>

            <!-- Delete -->
            <v-btn
              icon="mdi-delete-outline"
              color="error"
              variant="text"
              size="small"
              @click="handleDelete(item)"
            />
          </div>
        </template>

        <!-- Empty -->
        <template #no-data>
          <div class="text-center py-12">
            <v-icon icon="mdi-office-building-off-outline" size="56" color="grey-lighten-1" class="mb-3" />
            <p class="text-h6 text-medium-emphasis mb-1">No tenants found</p>
            <v-btn color="primary" variant="tonal" prepend-icon="mdi-plus" class="mt-2" @click="openCreate">
              Add First Tenant
            </v-btn>
          </div>
        </template>

      </v-data-table>
    </v-card>

    <!-- ── Tenant Form Dialog ──────────────────────────────────────────────────── -->
    <TenantFormDialog
      v-model="dialog.show"
      :item="dialog.tenant"
      :loading="saving"
      @save="handleSave"
      @transfer="handleTransfer"
    />

    <!-- ── Transfer Ownership Dialog ─────────────────────────────────────────── -->
    <v-dialog v-model="transferDialog.show" max-width="420" persistent>
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="warning" size="56" rounded="lg" class="mb-4">
            <v-icon icon="mdi-swap-horizontal" size="28" />
          </v-avatar>
          <h3 class="text-h6 font-weight-bold mb-1">Transfer Ownership</h3>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Transfer <strong>{{ transferDialog.tenant?.name }}</strong> to a new owner
          </p>
          <v-text-field
            v-model="transferDialog.newOwnerEmail"
            label="New Owner Email"
            type="email"
            variant="outlined"
            density="comfortable"
            rounded="lg"
            prepend-inner-icon="mdi-email-outline"
            hint="Must be an existing user in the system"
            persistent-hint
          />
        </v-card-text>
        <v-card-actions class="px-6 pb-6 pt-0 gap-3">
          <v-btn block variant="tonal" rounded="lg" @click="transferDialog.show = false">Cancel</v-btn>
          <v-btn block color="warning" variant="flat" rounded="lg" :loading="saving" @click="confirmTransfer">
            Transfer
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ── Delete Confirm ─────────────────────────────────────────────────────── -->
    <v-dialog v-model="deleteDialog.show" max-width="420" persistent>
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="error" size="56" rounded="lg" class="mb-4">
            <v-icon icon="mdi-delete-outline" size="28" />
          </v-avatar>
          <h3 class="text-h6 font-weight-bold mb-1">Delete Tenant?</h3>
          <p class="text-body-2 text-medium-emphasis">
            <strong>{{ deleteDialog.tenant?.name }}</strong> and all its data
            will be permanently deleted. This cannot be undone.
          </p>
        </v-card-text>
        <v-card-actions class="px-6 pb-6 pt-0 gap-3">
          <v-btn block variant="tonal" rounded="lg" @click="deleteDialog.show = false">Cancel</v-btn>
          <v-btn block color="error" variant="flat" rounded="lg" :loading="saving" @click="confirmDelete">
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenantStore'
import { storeToRefs }    from 'pinia'
import TenantFormDialog   from '@/components/tenants/TenantFormDialog.vue'

const tenantStore = useTenantStore()
const { tenants, loading } = storeToRefs(tenantStore)

const saving       = ref(false)
const search       = ref('')
const planFilter   = ref('')
const activeFilter = ref('')

// ── Dialog state ──────────────────────────────────────────────────────────────
const dialog = reactive({ show: false, tenant: null })

const transferDialog = reactive({
  show         : false,
  tenant       : null,
  newOwnerEmail: '',
})

const deleteDialog = reactive({ show: false, tenant: null })

// ── Stats ─────────────────────────────────────────────────────────────────────
const stats = computed(() => [
  {
    label: 'Total Tenants', icon: 'mdi-office-building-outline',
    color: 'primary', value: tenants.value.length,
  },
  {
    label: 'Active',        icon: 'mdi-check-circle-outline',
    color: 'success', value: tenants.value.filter(t => t.is_active).length,
  },
  {
    label: 'Suspended',     icon: 'mdi-minus-circle-outline',
    color: 'error',   value: tenants.value.filter(t => !t.is_active).length,
  },
  {
    label: 'Pro / Enterprise', icon: 'mdi-crown-outline',
    color: 'warning', value: tenants.value.filter(t => ['pro','enterprise'].includes(t.plan)).length,
  },
])

// ── Filtered list ─────────────────────────────────────────────────────────────
const filteredTenants = computed(() => {
  let list = tenants.value
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(t =>
      t.name.toLowerCase().includes(q) ||
      t.slug.toLowerCase().includes(q) ||
      t.owner?.email?.toLowerCase().includes(q)
    )
  }
  if (planFilter.value)   list = list.filter(t => t.plan      === planFilter.value)
  if (activeFilter.value !== '') {
    const active = activeFilter.value === '1'
    list = list.filter(t => t.is_active === active)
  }
  return list
})

// ── Table headers ─────────────────────────────────────────────────────────────
const headers = [
  { title: 'Tenant',      key: 'name',           sortable: true  },
  { title: 'Owner',       key: 'owner',           sortable: false },
  { title: 'Plan',        key: 'plan',            sortable: true  },
  { title: 'Locale',      key: 'locale',          sortable: false },
  { title: 'Expires',     key: 'plan_expires_at', sortable: true  },
  { title: 'Status',      key: 'is_active',       sortable: true  },
  { title: '',            key: 'actions',         sortable: false, align: 'end' },
]

// ── Actions ───────────────────────────────────────────────────────────────────
const openCreate = () => { dialog.tenant = null; dialog.show = true }
const openEdit   = (t) => { dialog.tenant = { ...t }; dialog.show = true }

const openTransfer = (t) => {
  transferDialog.tenant        = t
  transferDialog.newOwnerEmail = ''
  transferDialog.show          = true
}

const handleDelete = (t) => { deleteDialog.tenant = t; deleteDialog.show = true }

// Save create / update
const handleSave = async (payload) => {
  saving.value = true
  try {
    if (payload.id) {
      // store.updateTenant replaces item in tenants array by index
      await tenantStore.updateTenant(payload.id, payload)
    } else {
      // store.createTenant unshifts into tenants array
      await tenantStore.createTenant(payload)
    }
    dialog.show   = false
    dialog.tenant = null
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}

// Suspend / Activate toggle
const toggleActive = async (tenant) => {
  saving.value = true
  try {
    await tenantStore.toggleActive(tenant.id)
  } finally {
    saving.value = false
  }
}

// Transfer ownership
const handleTransfer = (payload) => {
  transferDialog.tenant        = tenants.value.find(t => t.id === payload.tenant_id)
  transferDialog.newOwnerEmail = payload.new_owner_email
  transferDialog.show          = true
}

const confirmTransfer = async () => {
  if (!transferDialog.newOwnerEmail) return
  saving.value = true
  try {
    await tenantStore.transferOwnership(
      transferDialog.tenant.id,
      transferDialog.newOwnerEmail
    )
    transferDialog.show = false
  } finally {
    saving.value = false
  }
}

// Delete
const confirmDelete = async () => {
  saving.value = true
  try {
    await tenantStore.deleteTenant(deleteDialog.tenant.id)
    deleteDialog.show = false
  } finally {
    saving.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const ownerInitials = (owner) => {
  if (!owner) return '?'
  return ((owner.first_name?.[0] || '') + (owner.last_name?.[0] || '')).toUpperCase()
}

const planColor = (plan) => ({
  free: 'grey', starter: 'info', pro: 'primary', enterprise: 'warning'
}[plan] || 'grey')

const planIcon = (plan) => ({
  free: 'mdi-star-outline', starter: 'mdi-star-half-full',
  pro: 'mdi-star', enterprise: 'mdi-crown',
}[plan] || 'mdi-star-outline')

const formatDate = (d) => d
  ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  : '—'

const isExpiringSoon = (item) => {
  if (!item.plan_expires_at) return false
  const days = (new Date(item.plan_expires_at) - new Date()) / 864e5
  return days < 14 && days > 0
}

onMounted(() => tenantStore.fetchTenants())
</script>

<style scoped>
.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
</style>