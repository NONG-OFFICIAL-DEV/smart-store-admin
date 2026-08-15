<template>
  <custom-title :title="$t('staff.title')" :subtitle="$t('staff.list.subtitle')">
    <template #right>
      <v-btn
        :color="showFilters ? 'primary' : 'default'"
        :variant="showFilters ? 'flat' : 'tonal'"
        rounded="lg"
        :prepend-icon="
          showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
        "
        @click="showFilters = !showFilters"
      >
        {{ $t('btn.filter') }}
        <v-badge
          v-if="activeFilterCount > 0"
          :content="activeFilterCount"
          color="error"
          floating
        />
      </v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" class="ms-2" @click="openCreate">
        {{ $t('btn.add_staff') }}
      </v-btn>
    </template>
  </custom-title>

  <v-container fluid class="pa-0">

    <!-- ── Stats ──────────────────────────────────────────────────────────────── -->
    <v-row class="mb-4">
      <v-col v-for="(stat, i) in stats" :key="i" cols="12" sm="6" lg="3">
        <v-card rounded="xl" border elevation="0" class="pa-4 d-flex align-center gap-4">
          <v-avatar :color="stat.color" variant="tonal" rounded="lg" size="48">
            <v-icon :icon="stat.icon" size="24" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption text-grey">{{ stat.label }}</div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filters ────────────────────────────────────────────────────────────── -->
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
        <v-card-text>
          <v-row dense align="center">
            <v-col cols="12" sm="auto">
              <v-btn-toggle
                v-model="activeFilter"
                color="primary"
                variant="tonal"
                rounded="lg"
                mandatory
              >
                <v-btn value="all"      size="small" class="text-none px-4">{{ $t('status.all') }}</v-btn>
                <v-btn value="active"   size="small" class="text-none px-4">{{ $t('status.active') }}</v-btn>
                <v-btn value="inactive" size="small" class="text-none px-4">{{ $t('status.inactive') }}</v-btn>
              </v-btn-toggle>
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                :placeholder="$t('placeholder.search_staff')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
                @keyup.enter="onFilterChange"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4">
          <v-spacer />
          <v-btn
            v-if="hasActiveFilters"
            rounded="lg"
            variant="tonal"
            color="error"
            prepend-icon="mdi-close"
            @click="resetFilters"
          >
            {{ $t('btn.reset') }}
          </v-btn>
          <v-btn
            class="bg-primary"
            rounded="lg"
            prepend-icon="mdi-magnify"
            @click="onFilterChange"
          >
            {{ $t('btn.search') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

    <!-- ── Staff Cards ──────────────────────────────────────────────────────────── -->
    <v-row v-if="!staffLoading" dense>
      <v-col
        v-for="member in filteredStaff"
        :key="member.id"
        cols="12" md="6" lg="4"
      >
        <v-card rounded="xl" border elevation="0" class="pa-4 hover-card">
          <div class="d-flex align-center gap-4 mb-4">
            <v-badge
              :color="member.is_active ? 'success' : 'grey'"
              dot
              location="bottom end"
              offset-x="3"
              offset-y="3"
            >
              <v-avatar size="56" rounded="lg" :color="avatarColor(member.full_name)">
                <span class="text-white font-weight-bold">
                  {{ initials(member.full_name) }}
                </span>
              </v-avatar>
            </v-badge>

            <div class="flex-grow-1 overflow-hidden">
              <div class="text-subtitle-1 font-weight-bold text-truncate">
                {{ member.full_name }}
              </div>
              <div class="text-caption text-grey text-truncate">{{ member.phone }}</div>
              <div class="text-caption text-grey text-truncate">{{ member.role_name }}</div>
            </div>

            <div class="d-flex flex-column gap-1">
              <!-- Go to Shifts page filtered by this staff -->
              <v-tooltip :text="$t('staff.list.view_shifts')">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon="mdi-calendar-clock"
                    size="x-small"
                    variant="tonal"
                    color="info"
                    :to="{ name: 'Shifts', query: { staff_id: member.id } }"
                  />
                </template>
              </v-tooltip>
              <v-btn icon="mdi-pencil-outline" size="x-small" variant="tonal"              @click="openEdit(member)" />
              <v-btn icon="mdi-delete-outline" size="x-small" variant="tonal" color="error" @click="confirmDelete(member)" />
            </div>
          </div>

          <v-divider class="mb-3" />

          <div class="d-flex justify-space-between align-center">
            <span class="text-caption text-grey">
              <v-icon size="14" icon="mdi-identifier" />
              {{ member.employee_code }}
            </span>
            <span class="font-weight-bold text-primary">
              ${{ member.hourly_rate }}/hr
            </span>
          </div>
        </v-card>
      </v-col>

      <!-- Empty -->
      <v-col v-if="!filteredStaff.length" cols="12">
        <div class="text-center py-12">
          <v-icon icon="mdi-account-group-outline" size="56" color="grey-lighten-1" class="mb-3" />
          <p class="text-body-1 text-medium-emphasis">{{ $t('staff.empty') }}</p>
          <v-btn color="primary" variant="tonal" prepend-icon="mdi-plus" class="mt-3" @click="openCreate">
            {{ $t('staff.list.add_first') }}
          </v-btn>
        </div>
      </v-col>
    </v-row>

    <!-- Skeleton -->
    <v-row v-else dense>
      <v-col v-for="n in 6" :key="n" cols="12" md="6" lg="4">
        <v-skeleton-loader type="article" border rounded="xl" />
      </v-col>
    </v-row>

  </v-container>

  <!-- ── Form Dialog ─────────────────────────────────────────────────────────── -->
  <StaffDialogForm
    v-model="dialog"
    mode="staff"
    :item="selectedItem"
    :loading="saving"
    @save="handleSave"
  />

  <!-- ── Delete Confirm ──────────────────────────────────────────────────────── -->
  <AppDialog
    v-model="deleteDialog"
    :max-width="400"
    :title="$t('staff.list.confirm_deactivate.title')"
    icon="mdi-account-off-outline"
    color="error"
    :loading="saving"
    :submit-text="$t('btn.deactivate')"
    body-class="pa-6 text-center"
    @submit="handleDelete"
  >
    <p class="text-body-2 text-medium-emphasis">
      <strong>{{ deleteTarget?.full_name }}</strong> {{ $t('staff.list.confirm_deactivate.message') }}
    </p>
  </AppDialog>

  <!-- ── Snackbar ────────────────────────────────────────────────────────────── -->
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="bottom right" rounded="lg" :timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>

</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useStaffStore } from '@/stores/staffStore'
import StaffDialogForm from '@/components/staff/StaffDialogForm.vue'
import AppDialog from '@/components/common/AppDialog.vue'
import { useAvatar } from '@/composables/useAvatar'

const { t } = useI18n()
const { getInitials, getAvatarColor } = useAvatar()
const staffStore = useStaffStore()
const { staffList, loading: staffLoading } = storeToRefs(staffStore)

const search       = ref('')
const activeFilter = ref('all')
const showFilters  = ref(false)
const dialog       = ref(false)
const deleteDialog = ref(false)
const saving       = ref(false)
const selectedItem = ref(null)
const deleteTarget = ref(null)
const snackbar     = ref({ show: false, message: '', color: 'success' })

const showSnack = (message, color = 'success') => {
  snackbar.value = { show: true, message, color }
}

// ── Stats ─────────────────────────────────────────────────────────────────────
const stats = computed(() => [
  { label: t('staff.list.stats.total_staff'), value: staffList.value.length,                            icon: 'mdi-account-group-outline', color: 'blue'    },
  { label: t('status.active'),      value: staffList.value.filter(s => s.is_active).length,  icon: 'mdi-account-check-outline', color: 'success' },
  { label: t('status.inactive'),    value: staffList.value.filter(s => !s.is_active).length, icon: 'mdi-account-off-outline',   color: 'error'   },
  { label: t('menu.branches'),    value: new Set(staffList.value.map(s => s.branch_id)).size, icon: 'mdi-store-outline',      color: 'warning' },
])

// ── Filter ────────────────────────────────────────────────────────────────────
const filteredStaff = computed(() => {
  let list = staffList.value
  if (activeFilter.value === 'active')   list = list.filter(s => s.is_active)
  if (activeFilter.value === 'inactive') list = list.filter(s => !s.is_active)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(s =>
      s.full_name?.toLowerCase().includes(q)   ||
      s.phone?.toLowerCase().includes(q)       ||
      s.role_name?.toLowerCase().includes(q)   ||
      s.employee_code?.toLowerCase().includes(q)
    )
  }
  return list
})

// ── Active filter badge ────────────────────────────────────────────────────────
const activeFilterCount = computed(() => {
  let count = 0
  if (search.value?.trim()) count++
  if (activeFilter.value !== 'all') count++
  return count
})
const hasActiveFilters = computed(() => activeFilterCount.value > 0)

// ── Filter actions ──────────────────────────────────────────────────────────────
const onFilterChange = () => {}

const resetFilters = () => {
  search.value = ''
  activeFilter.value = 'all'
}

// ── Actions ───────────────────────────────────────────────────────────────────
const openCreate = () => { selectedItem.value = null; dialog.value = true }
const openEdit   = (item) => { selectedItem.value = { ...item }; dialog.value = true }
const confirmDelete = (item) => { deleteTarget.value = item; deleteDialog.value = true }

const handleDelete = async () => {
  saving.value = true
  try {
    await staffStore.deleteStaff(deleteTarget.value.id)
    deleteDialog.value = false
    showSnack(t('staff.list.messages.deactivated', { name: deleteTarget.value.full_name }))
    await staffStore.fetchStaff()
  } catch {
    showSnack(t('staff.list.messages.deactivate_failed'), 'error')
  } finally {
    saving.value = false
  }
}

const handleSave = async (payload) => {
  saving.value = true
  try {
    payload.id
      ? await staffStore.updateStaff(payload.id, payload)
      : await staffStore.createStaff(payload)
    dialog.value = false
    showSnack(payload.id ? t('staff.list.messages.updated') : t('staff.list.messages.created'))
    await staffStore.fetchStaff()
  } catch {
    showSnack(t('staff.list.messages.save_failed'), 'error')
  } finally {
    saving.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const initials = n => getInitials(n)
const avatarColor = n =>
  getAvatarColor(n, {
    palette: ['#3b5bdb', '#2f9e44', '#e67700', '#c92a2a'],
    fallback: '#808080'
  })

onMounted(() => staffStore.fetchStaff())
</script>

<style scoped>
.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }
.hover-card { transition: all 0.3s ease; }
.hover-card:hover {
  border-color: rgb(var(--v-theme-primary)) !important;
  transform: translateY(-3px);
}
</style>