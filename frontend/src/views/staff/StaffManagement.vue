<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useStaffStore } from '@/stores/staffStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import StaffDialogForm from '@/components/staff/StaffDialogForm.vue'

  const staffStore = useStaffStore()
  const branchStore = useBranchStore()
  const { confirm, notif } = useAppUtils()

  const { staffList, loading: staffLoading } = storeToRefs(staffStore)
  const { branches } = storeToRefs(branchStore)

  const search = ref('')
  const activeFilter = ref('all')
  const branchFilter = ref(null)
  const viewMode = ref('card')
  const dialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)

  // ── Branch options for filter ──────────────────────────────────────────────
  const branchOptions = computed(
    () => branches.value?.data ?? branches.value ?? []
  )

  // ── Stats ──────────────────────────────────────────────────────────────────
  const stats = computed(() => [
    {
      label: 'Total Staff',
      value: staffList.value.length,
      icon: 'mdi-account-group-outline',
      color: 'primary',
      bg: '#e8eaf6',
      trend:
        staffList.value.length > 0 ? `${staffList.value.length} total` : '—',
      trendClass: 'trend-neutral'
    },
    {
      label: 'Active',
      value: staffList.value.filter(s => s.is_active).length,
      icon: 'mdi-account-check-outline',
      color: 'success',
      bg: '#e8f5e9',
      trend: staffList.value.length
        ? Math.round(
            (staffList.value.filter(s => s.is_active).length /
              staffList.value.length) *
              100
          ) + '%'
        : '—',
      trendClass: 'trend-up'
    },
    {
      label: 'Inactive',
      value: staffList.value.filter(s => !s.is_active).length,
      icon: 'mdi-account-off-outline',
      color: 'error',
      bg: '#fce4ec',
      trend:
        staffList.value.filter(s => !s.is_active).length > 0
          ? 'needs action'
          : 'all good',
      trendClass:
        staffList.value.filter(s => !s.is_active).length > 0
          ? 'trend-down'
          : 'trend-up'
    },
    {
      label: 'Branches',
      value: new Set(staffList.value.map(s => s.branch_id)).size,
      icon: 'mdi-store-outline',
      color: 'warning',
      bg: '#fff8e1',
      trend: `${new Set(staffList.value.map(s => s.branch_id)).size} locations`,
      trendClass: 'trend-neutral'
    }
  ])

  // ── Table headers ──────────────────────────────────────────────────────────
  const tableHeaders = [
    { title: 'Name', key: 'full_name', sortable: true },
    { title: 'Code', key: 'employee_code', sortable: false },
    { title: 'Branch', key: 'branch_name', sortable: true },
    { title: 'Role', key: 'role_name', sortable: true },
    { title: 'Compensation', key: 'salary', sortable: false },
    { title: 'Status', key: 'is_active', sortable: true },
    { title: '', key: 'actions', sortable: false, width: '10%' }
  ]

  // ── Filter ──────────────────────────────────────────────────────────────────
  const filteredStaff = computed(() => {
    let list = staffList.value
    if (activeFilter.value === 'active') list = list.filter(s => s.is_active)
    if (activeFilter.value === 'inactive') list = list.filter(s => !s.is_active)
    if (branchFilter.value)
      list = list.filter(s => s.branch_id === branchFilter.value)
    if (search.value) {
      const q = search.value.toLowerCase()
      list = list.filter(
        s =>
          s.full_name?.toLowerCase().includes(q) ||
          s.phone?.toLowerCase().includes(q) ||
          s.role_name?.toLowerCase().includes(q) ||
          s.employee_code?.toLowerCase().includes(q)
      )
    }
    return list
  })

  // ── Actions ────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selectedItem.value = { ...item }
    dialog.value = true
  }

  const confirmDelete = member => {
    confirm({
      title: 'Deactivate Staff Member',
      message: `Are you sure you want to deactivate "${member.full_name}"? Their history will be kept.`,
      options: { type: 'warning', width: 480 },
      agree: async () => {
        try {
          await staffStore.deleteStaff(member.id)
          notif(`${member.full_name} deactivated`, { type: 'success' })
          await staffStore.fetchStaff()
        } catch {
          notif('Failed to deactivate staff', { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await staffStore.updateStaff(payload.id, payload)
        notif('Staff updated successfully', { type: 'success' })
      } else {
        await staffStore.createStaff(payload)
        notif('Staff created successfully', { type: 'success' })
      }
      dialog.value = false
      await staffStore.fetchStaff()
    } catch {
      notif('Failed to save staff', { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  const initials = n =>
    n
      ? n
          .split(' ')
          .map(x => x[0])
          .join('')
          .toUpperCase()
          .slice(0, 2)
      : '?'

  const avatarColor = n =>
    n
      ? ['#3b5bdb', '#2f9e44', '#e67700', '#c92a2a', '#0c8599', '#6741d9'][
          n.length % 6
        ]
      : '#808080'

  onMounted(() => {
    staffStore.fetchStaff()
    branchStore.fetchBranches()
  })
</script>
<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-account-group"
      title="Staff Management"
      subtitle="Manage employees, roles, and branch access"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          class="text-none px-6"
          @click="openCreate"
        >
          Add Staff
        </v-btn>
      </template>
    </custom-title>

    <v-row class="mb-4" dense>
      <v-col v-for="(stat, i) in stats" :key="i" cols="6" sm="3">
        <v-card rounded="xl" border flat class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <v-avatar :color="stat.bg" rounded="lg" size="36">
              <v-icon :icon="stat.icon" :color="stat.color" size="20" />
            </v-avatar>
            <v-chip
              :color="stat.trendClass === 'trend-up' ? 'success' : 'grey'"
              size="x-small"
              variant="flat"
            >
              {{ stat.trend }}
            </v-chip>
          </div>
          <div class="text-h5 font-weight-black">{{ stat.value }}</div>
          <div class="text-caption font-weight-medium text-medium-emphasis">
            {{ stat.label }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-card flat rounded="xl" border class="pa-3 mb-6">
      <v-row align="center" dense>
        <v-col cols="12" md="4">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            placeholder="Search staff..."
            variant="solo-filled"
            density="compact"
            rounded="lg"
            flat
            hide-details
            clearable
          />
        </v-col>

        <v-col
          cols="12"
          md="8"
          class="d-flex gap-2 justify-md-end align-center"
        >
          <v-select
            v-model="branchFilter"
            :items="branchOptions"
            item-title="name"
            item-value="id"
            placeholder="All Branches"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            clearable
            max-width="200"
            prepend-inner-icon="mdi-store-outline"
          />

          <v-btn-toggle
            v-model="activeFilter"
            color="primary"
            variant="tonal"
            rounded="lg"
            mandatory
            density="compact"
          >
            <v-btn value="all" class="text-none px-4">All</v-btn>
            <v-btn value="active" class="text-none px-4">Active</v-btn>
          </v-btn-toggle>

          <v-divider vertical inset class="mx-2" />

          <v-btn-toggle
            v-model="viewMode"
            mandatory
            rounded="lg"
            density="compact"
            color="primary"
          >
            <v-btn value="card" icon="mdi-view-grid-outline" size="small" />
            <v-btn value="table" icon="mdi-view-list-outline" size="small" />
          </v-btn-toggle>
        </v-col>
      </v-row>
    </v-card>

    <div class="text-caption font-weight-bold text-medium-emphasis mb-4 px-1">
      <v-icon size="14" class="mr-1">mdi-filter-variant</v-icon>
      Showing {{ filteredStaff.length }} staff members
    </div>

    <v-window v-model="viewMode">
      <v-window-item value="card">
        <v-row v-if="staffLoading" dense>
          <v-col v-for="n in 6" :key="n" cols="12" sm="6" lg="4">
            <v-skeleton-loader
              type="list-item-avatar-three-line"
              rounded="xl"
              border
            />
          </v-col>
        </v-row>

        <v-row v-else dense>
          <v-col
            v-for="member in filteredStaff"
            :key="member.id"
            cols="12"
            sm="6"
            lg="4"
          >
            <v-hover v-slot="{ isPropping, props: hoverProps }">
              <v-card
                v-bind="hoverProps"
                rounded="xl"
                border
                flat
                class="staff-card-v transition-swing"
                :elevation="isPropping ? 4 : 0"
                :alpha="member.is_active ? 1 : 0.7"
              >
                <div
                  :style="`height: 4px; background: ${avatarColor(member.full_name)}`"
                />

                <v-card-item class="pa-4">
                  <template #prepend>
                    <v-avatar
                      :color="avatarColor(member.full_name)"
                      size="48"
                      variant="tonal"
                      rounded="lg"
                      class="mr-3"
                    >
                      <span class="text-h6 font-weight-black">
                        {{ initials(member.full_name) }}
                      </span>
                    </v-avatar>
                  </template>

                  <v-card-title class="text-subtitle-1 font-weight-black pb-0">
                    {{ member.full_name }}
                  </v-card-title>
                  <v-card-subtitle class="text-caption font-weight-medium">
                    <v-icon
                      size="12"
                      icon="mdi-shield-account-outline"
                      class="mr-1"
                    />
                    {{ member.role_name || 'No Role' }}
                  </v-card-subtitle>

                  <template #append>
                    <v-menu location="bottom end">
                      <template #activator="{ props: menuProps }">
                        <v-btn
                          icon="mdi-dots-vertical"
                          variant="text"
                          size="small"
                          v-bind="menuProps"
                        />
                      </template>
                      <v-list density="compact" rounded="lg">
                        <v-list-item
                          prepend-icon="mdi-pencil-outline"
                          title="Edit"
                          @click="openEdit(member)"
                        />
                        <v-list-item
                          prepend-icon="mdi-calendar-clock"
                          title="Shifts"
                          :to="{
                            name: 'Shifts',
                            query: { staff_id: member.id }
                          }"
                        />
                        <v-divider class="my-1" />
                        <v-list-item
                          prepend-icon="mdi-delete-outline"
                          title="Deactivate"
                          color="error"
                          @click="confirmDelete(member)"
                        />
                      </v-list>
                    </v-menu>
                  </template>
                </v-card-item>

                <v-divider />

                <v-card-text class="pa-4">
                  <v-row dense>
                    <v-col cols="6">
                      <div
                        class="text-tiny text-uppercase text-medium-emphasis font-weight-bold"
                      >
                        Branch
                      </div>
                      <div class="text-caption font-weight-bold">
                        {{ member.branch_name || '—' }}
                      </div>
                    </v-col>
                    <v-col cols="6">
                      <div
                        class="text-tiny text-uppercase text-medium-emphasis font-weight-bold"
                      >
                        Phone
                      </div>
                      <div class="text-caption font-weight-bold">
                        {{ member.phone || '—' }}
                      </div>
                    </v-col>
                    <v-col cols="6" class="mt-2">
                      <div
                        class="text-tiny text-uppercase text-medium-emphasis font-weight-bold"
                      >
                        Employee ID
                      </div>
                      <div class="text-caption font-weight-bold">
                        {{ member.employee_code || '—' }}
                      </div>
                    </v-col>
                    <v-col cols="6" class="mt-2 text-right">
                      <div
                        class="text-tiny text-uppercase text-primary font-weight-black"
                      >
                        Compensation
                      </div>
                      <div class="text-caption font-weight-black text-primary">
                        {{
                          member.hourly_rate
                            ? `$${member.hourly_rate}/hr`
                            : member.salary
                              ? `$${member.salary}/mo`
                              : '—'
                        }}
                      </div>
                    </v-col>
                  </v-row>
                </v-card-text>
              </v-card>
            </v-hover>
          </v-col>
        </v-row>
      </v-window-item>

      <v-window-item value="table">
        <v-card rounded="xl" border flat class="overflow-hidden">
          <v-data-table
            :headers="tableHeaders"
            :items="filteredStaff"
            :loading="staffLoading"
            hover
          >
            <template #item.full_name="{ item }">
              <div class="d-flex align-center py-2">
                <v-avatar
                  :color="avatarColor(item.full_name)"
                  size="32"
                  variant="tonal"
                  class="mr-3"
                  rounded="sm"
                >
                  <span class="text-caption font-weight-black">
                    {{ initials(item.full_name) }}
                  </span>
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-bold">
                    {{ item.full_name }}
                  </div>
                  <div class="text-tiny text-medium-emphasis">
                    {{ item.phone }}
                  </div>
                </div>
              </div>
            </template>

            <template #item.is_active="{ item }">
              <v-chip
                :color="item.is_active ? 'success' : 'grey'"
                size="x-small"
                variant="tonal"
              >
                {{ item.is_active ? 'Active' : 'Inactive' }}
              </v-chip>
            </template>

            <template #item.actions="{ item }">
              <v-btn
                icon="mdi-pencil-outline"
                variant="text"
                size="small"
                @click="openEdit(item)"
              />
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                color="error"
                @click="confirmDelete(item)"
              />
            </template>
          </v-data-table>
        </v-card>
      </v-window-item>
    </v-window>

    <v-sheet
      v-if="!filteredStaff.length && !staffLoading"
      class="d-flex flex-column align-center justify-center pa-12 bg-transparent text-center"
    >
      <v-icon
        size="64"
        color="grey-lighten-2"
        icon="mdi-account-search-outline"
      />
      <div class="text-h6 font-weight-bold mt-4">No staff members found</div>
      <div class="text-body-2 text-medium-emphasis mb-6">
        Try adjusting your filters or search terms
      </div>
      <v-btn color="primary" variant="tonal" rounded="lg" @click="openCreate">
        Add New Staff
      </v-btn>
    </v-sheet>

    <StaffDialogForm
      v-model="dialog"
      :item="selectedItem"
      :loading="saving"
      @save="handleSave"
    />
  </v-container>
</template>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
  .text-tiny {
    font-size: 0.65rem !important;
    line-height: 1rem;
    letter-spacing: 0.05em;
  }

  .staff-card-v {
    transition: all 0.25s ease-in-out;
  }

  /* Custom scrollbar for data table if needed */
  :deep(.v-table__wrapper) {
    scrollbar-width: thin;
  }
</style>
