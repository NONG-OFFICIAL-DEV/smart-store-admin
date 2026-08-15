<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-calendar-account-outline"
      :title="$t('shift_assignments.title')"
      :subtitle="$t('shift_assignments.subtitle')"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-account-plus"
          rounded="lg"
          class="ms-4"
          @click="openCreate"
        >
          {{ $t('btn.assign') }}
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Filters ──────────────── -->
    <v-row dense align="center" class="mb-2">
      <v-col cols="12" sm="6" md="3">
        <v-date-input
          v-model="filterDateFrom"
          label="From Date"
          density="compact"
          rounded="lg"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-date-input
          v-model="filterDateTo"
          label="To Date"
          density="compact"
          rounded="lg"
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-select
          v-model="filterState.shift_id"
          :items="shiftList"
          item-value="id"
          item-title="name"
          label="Shift"
          variant="outlined"
          density="compact"
          rounded="lg"
          clearable
        />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-select
          v-model="filterState.staff_id"
          :items="staffList"
          item-value="id"
          item-title="full_name"
          label="Staff Member"
          variant="outlined"
          density="compact"
          rounded="lg"
          clearable
        />
      </v-col>
    </v-row>

    <!-- ── Assignments Table ──────────────────────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchAssignments"
        :filters="filters"
        :show-search="false"
        item-label="assignments"
      >
        <!-- Staff column -->
        <template #[`item.staff`]="{ item }">
          <div class="d-flex align-center gap-3 py-1">
            <v-avatar
              :color="avatarColor(item.staff?.user?.full_name)"
              size="34"
              rounded="md"
            >
              <span class="text-white text-caption font-weight-bold">
                {{ initials(item.staff?.user?.full_name) }}
              </span>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.staff?.user?.full_name }}
              </div>
              <div class="text-caption text-grey">
                {{ item.staff?.employee_code }}
              </div>
            </div>
          </div>
        </template>

        <!-- Shift column -->
        <template #[`item.shift`]="{ item }">
          <div class="d-flex align-center gap-2">
            <div
              class="shift-dot"
              :style="{ background: shiftTypeColor(item.shift?.shift_type) }"
            />
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.shift?.name }}
              </div>
              <div class="text-caption text-grey font-mono">
                {{ item.shift?.start_time }} → {{ item.shift?.end_time }}
              </div>
            </div>
          </div>
        </template>

        <!-- Date column -->
        <template #[`item.shift_date`]="{ item }">
          <div class="text-body-2">{{ formatDate(item.shift_date) }}</div>
        </template>

        <!-- Actual time column -->
        <template #[`item.actual`]="{ item }">
          <div v-if="item.actual_start" class="text-body-2 font-mono">
            {{ formatTime(item.actual_start) }}
            <span class="text-grey mx-1">→</span>
            {{ item.actual_end ? formatTime(item.actual_end) : '...' }}
          </div>
          <span v-else class="text-caption text-grey">—</span>
        </template>

        <!-- Status column -->
        <template #[`item.status`]="{ item }">
          <v-chip
            :color="statusColor(item)"
            size="small"
            variant="tonal"
            :prepend-icon="statusIcon(item)"
          >
            {{ statusLabel(item) }}
          </v-chip>
        </template>

        <!-- Actions column -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex gap-1 justify-end">
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
    <!-- ── Form Dialog ─────────────────────────────────────────────────────────── -->
    <StaffShiftFormDialog
      v-model="dialog"
      :item="selectedItem"
      :loading="saving"
      :shift-list="shiftList"
      :staff-list="staffList"
      @save="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute } from 'vue-router'
  import { useShiftAssignmentStore } from '@/stores/shiftAssignmentStore'
  import { useShiftStore } from '@/stores/shiftStore'
  import { useStaffStore } from '@/stores/staffStore'
  import { shiftAssignmentService } from '@/api/shiftAssignmentService'
  import StaffShiftFormDialog from '@/components/staff/StaffShiftFormDialog.vue'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useDate } from '@/composables/useDate'
  import { useAvatar } from '@/composables/useAvatar'
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const {
    formatLocalDate,
    formatWeekdayDate: formatDate,
    formatTime
  } = useDate()
  const { getInitials, getAvatarColor } = useAvatar()

  const route = useRoute()
  const staffShiftStore = useShiftAssignmentStore()
  const shiftStore = useShiftStore()
  const staffStore = useStaffStore()

  const { shiftList } = storeToRefs(shiftStore)
  const { staffList } = storeToRefs(staffStore)

  const tableRef = ref(null)
  const dialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)

  // Pre-fill shift_id if navigating from ShiftsView
  const filterState = ref({
    date_from: null,
    date_to: null,
    shift_id: route.query.shift_id || null,
    staff_id: route.query.staff_id || null
  })

  // filterState.date_from/to stay "YYYY-MM-DD" strings (StaffShiftRepository
  // compares them against shift_date) — these proxies are what the date
  // pickers bind to.
  const filterDateFrom = computed({
    get: () =>
      filterState.value.date_from
        ? new Date(filterState.value.date_from)
        : null,
    set: v => {
      filterState.value.date_from = v instanceof Date ? formatLocalDate(v) : v
    }
  })
  const filterDateTo = computed({
    get: () =>
      filterState.value.date_to ? new Date(filterState.value.date_to) : null,
    set: v => {
      filterState.value.date_to = v instanceof Date ? formatLocalDate(v) : v
    }
  })

  // ── Table headers — StaffShiftRepository::applySort() always forces
  // shift_date desc, created_at desc regardless of sortBy, so nothing here
  // is marked sortable (there's no user-choosable sort to advertise). ────────
  const headers = computed(() => [
    {
      title: t('shift_assignments.table.staff'),
      key: 'staff',
      sortable: false
    },
    {
      title: t('shift_assignments.table.shift'),
      key: 'shift',
      sortable: false
    },
    {
      title: t('shift_assignments.table.date'),
      key: 'shift_date',
      sortable: false
    },
    {
      title: t('shift_assignments.table.actual'),
      key: 'actual',
      sortable: false
    },
    {
      title: t('shift_assignments.table.status'),
      key: 'status',
      sortable: false
    },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

  // ── Server-driven filters — matches StaffShiftRepository's contract
  // (shift_id/staff_id/branch_id/date_from/date_to/today). No free-text
  // search — the repository never defines `$searchable`. ────────────────────
  const filters = computed(() => ({
    shift_id: filterState.value.shift_id || undefined,
    staff_id: filterState.value.staff_id || undefined,
    date_from: filterState.value.date_from || undefined,
    date_to: filterState.value.date_to || undefined
  }))

  async function fetchAssignments(params) {
    const { data } = await shiftAssignmentService.getAll(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selectedItem.value = { ...item }
    dialog.value = true
  }

  const handleDelete = async item => {
    saving.value = true
    try {
      confirm({
        title: 'Remove Assignment?',
        message: `Are you sure delete "${item.name}"?`,
        options: { type: 'warning', color: 'warning', width: 400 },
        agree: async () => {
          await staffShiftStore.deleteAssignment(item.id)
          tableRef.value?.refresh()
          notif(t('messages.deleted_success'), {
            type: 'success'
          })
        }
      })
    } catch {
      notif('Failed to remove', {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      payload.id
        ? await staffShiftStore.updateAssignment(payload.id, payload)
        : await staffShiftStore.createAssignment(payload)
      notif('Saved successfully', {
        type: 'success'
      })
      dialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif('Failed to remove', {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  // ── Status helpers ────────────────────────────────────────────────────────────
  const statusLabel = item => {
    if (item.actual_end) return 'Completed'
    if (item.actual_start) return 'Clocked In'
    if (isPast(item)) return 'Absent'
    return 'Scheduled'
  }
  const statusColor = item => {
    if (item.actual_end) return 'grey'
    if (item.actual_start) return 'success'
    if (isPast(item)) return 'error'
    return 'warning'
  }
  const statusIcon = item => {
    if (item.actual_end) return 'mdi-clock-check-outline'
    if (item.actual_start) return 'mdi-clock-in'
    if (isPast(item)) return 'mdi-account-off-outline'
    return 'mdi-clock-outline'
  }
  const isPast = item => {
    if (!item.shift_date || !item.shift?.start_time) return false
    const scheduled = new Date(`${item.shift_date}T${item.shift.start_time}`)
    return new Date() > scheduled && !item.actual_start
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const initials = n => getInitials(n)
  const avatarColor = n =>
    getAvatarColor(n, {
      palette: ['#3b5bdb', '#2f9e44', '#e67700', '#c92a2a'],
      fallback: '#808080'
    })
  const shiftTypeColor = type =>
    ({
      morning: '#f59e0b',
      afternoon: '#3b82f6',
      evening: '#8b5cf6',
      full_day: '#10b981',
      split: '#ef4444'
    })[type] || '#6b7280'

  // Shift/staff lists here are only for the filter dropdowns and the
  // assign dialog — the assignments table itself loads via AppTable's
  // own fetchAssignments call, not through the store.
  onMounted(async () => {
    await Promise.all([
      shiftStore.fetchShifts({ perPage: 100 }),
      staffStore.fetchStaff({ perPage: 100 })
    ])
  })
</script>

<style scoped>
  .shift-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .font-mono {
    font-family: 'Courier New', monospace;
  }
</style>
