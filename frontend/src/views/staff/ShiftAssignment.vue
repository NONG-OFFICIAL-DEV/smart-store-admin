<template>
  <custom-title
    title="Staff Assignments"
    subtitle="Assign staff to shifts by date"
  >
    <template #right>
      <v-btn
        color="primary"
        prepend-icon="mdi-account-plus"
        rounded="lg"
        @click="openCreate"
      >
        Assign Staff
      </v-btn>
    </template>
  </custom-title>

  <v-container fluid class="pa-0">
    <!-- ── Stats ─────────────────────────────────────────────────────────────── -->
    <v-row class="mb-5">
      <v-col v-for="(stat, i) in stats" :key="i" cols="12" sm="6" lg="3">
        <v-card
          rounded="xl"
          border
          elevation="0"
          class="pa-4 d-flex align-center gap-4"
        >
          <v-avatar :color="stat.color" variant="tonal" rounded="lg" size="48">
            <v-icon :icon="stat.icon" size="22" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption text-grey">{{ stat.label }}</div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filters ────────────────────────────────────────────────────────────── -->
    <v-card rounded="xl" border elevation="0" class="mb-5">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="filters.date_from"
              type="date"
              label="From Date"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              prepend-inner-icon="mdi-calendar-start"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="filters.date_to"
              type="date"
              label="To Date"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              prepend-inner-icon="mdi-calendar-end"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <!-- Pre-filled when navigating from ShiftsView via "Assign Staff" button -->
            <v-select
              v-model="filters.shift_id"
              :items="shiftList"
              item-value="id"
              item-title="name"
              label="Shift"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              prepend-inner-icon="mdi-clock-outline"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select
              v-model="filters.staff_id"
              :items="staffList"
              item-value="id"
              item-title="full_name"
              label="Staff Member"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              prepend-inner-icon="mdi-account-outline"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── Assignments Table ──────────────────────────────────────────────────── -->
    <v-card rounded="xl" border elevation="0">
      <v-data-table
        :headers="headers"
        :items="filteredAssignments"
        :loading="loading"
        item-value="id"
        rounded="xl"
        hover
      >
        <!-- Staff column -->
        <template #item.staff="{ item }">
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
        <template #item.shift="{ item }">
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
        <template #item.shift_date="{ item }">
          <div class="text-body-2">{{ formatDate(item.shift_date) }}</div>
        </template>

        <!-- Actual time column -->
        <template #item.actual="{ item }">
          <div v-if="item.actual_start" class="text-body-2 font-mono">
            {{ formatTime(item.actual_start) }}
            <span class="text-grey mx-1">→</span>
            {{ item.actual_end ? formatTime(item.actual_end) : '...' }}
          </div>
          <span v-else class="text-caption text-grey">—</span>
        </template>

        <!-- Status column -->
        <template #item.status="{ item }">
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
        <template #item.actions="{ item }">
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
              @click="confirmDelete(item)"
            />
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="text-center py-10">
            <v-icon
              icon="mdi-calendar-account-outline"
              size="48"
              color="grey-lighten-1"
              class="mb-3"
            />
            <p class="text-body-1 text-medium-emphasis">No assignments found</p>
            <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-account-plus"
              class="mt-3"
              @click="openCreate"
            >
              Assign First Staff
            </v-btn>
          </div>
        </template>
      </v-data-table>
    </v-card>
  </v-container>

  <!-- ── Form Dialog ─────────────────────────────────────────────────────────── -->
  <StaffShiftFormDialog
    v-model="dialog"
    :item="selectedItem"
    :loading="saving"
    :shift-list="shiftList"
    :staff-list="staffList"
    @save="handleSave"
  />

  <!-- ── Delete Confirm ──────────────────────────────────────────────────────── -->
  <v-dialog v-model="deleteDialog" max-width="400" persistent>
    <v-card rounded="xl" elevation="0" border>
      <v-card-text class="pa-6 text-center">
        <v-avatar color="error" size="56" rounded="lg" class="mb-4">
          <v-icon icon="mdi-delete-outline" size="28" />
        </v-avatar>
        <h3 class="text-h6 font-weight-bold mb-2">Remove Assignment?</h3>
        <p class="text-body-2 text-medium-emphasis">
          <strong>{{ deleteTarget?.staff?.user?.full_name }}</strong>
          will be removed from
          <strong>{{ deleteTarget?.shift?.name }}</strong>
          on
          <strong>{{ formatDate(deleteTarget?.shift_date) }}</strong>
          .
        </p>
      </v-card-text>
      <v-card-actions class="px-6 pb-6 pt-0 gap-3">
        <v-btn block variant="tonal" rounded="lg" @click="deleteDialog = false">
          Cancel
        </v-btn>
        <v-btn
          block
          color="error"
          variant="flat"
          rounded="lg"
          :loading="saving"
          @click="handleDelete"
        >
          Remove
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- ── Snackbar ────────────────────────────────────────────────────────────── -->
  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    location="bottom right"
    rounded="lg"
    :timeout="3000"
  >
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute } from 'vue-router'
  import { useShiftAssignmentStore } from '@/stores/shiftAssignmentStore'
  import { useShiftStore } from '@/stores/shiftStore'
  import { useStaffStore } from '@/stores/staffStore'
  import StaffShiftFormDialog from '@/components/staff/StaffShiftFormDialog.vue'

  const route = useRoute()
  const staffShiftStore = useShiftAssignmentStore()
  const shiftStore = useShiftStore()
  const staffStore = useStaffStore()

  const { assignmentList, loading } = storeToRefs(staffShiftStore)
  const { shiftList } = storeToRefs(shiftStore)
  const { staffList } = storeToRefs(staffStore)

  const dialog = ref(false)
  const deleteDialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)
  const deleteTarget = ref(null)
  const snackbar = ref({ show: false, message: '', color: 'success' })

  // Pre-fill shift_id if navigating from ShiftsView
  const filters = ref({
    date_from: null,
    date_to: null,
    shift_id: route.query.shift_id || null,
    staff_id: route.query.staff_id || null
  })

  const showSnack = (message, color = 'success') => {
    snackbar.value = { show: true, message, color }
  }

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Staff', key: 'staff', sortable: false },
    { title: 'Shift', key: 'shift', sortable: false },
    { title: 'Date', key: 'shift_date', sortable: true },
    { title: 'Actual Time', key: 'actual', sortable: false },
    { title: 'Status', key: 'status', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => {
    const today = new Date().toISOString().split('T')[0]
    const list = assignmentList.value
    return [
      {
        label: 'Total',
        value: list.length,
        icon: 'mdi-calendar-account-outline',
        color: 'primary'
      },
      {
        label: 'Today',
        value: list.filter(a => a.shift_date === today).length,
        icon: 'mdi-calendar-today',
        color: 'blue'
      },
      {
        label: 'Clocked In',
        value: list.filter(a => a.actual_start && !a.actual_end).length,
        icon: 'mdi-clock-in',
        color: 'success'
      },
      {
        label: 'Completed',
        value: list.filter(a => a.actual_end).length,
        icon: 'mdi-clock-check-outline',
        color: 'grey'
      }
    ]
  })

  // ── Filter ────────────────────────────────────────────────────────────────────
  const filteredAssignments = computed(() => {
    let list = assignmentList.value
    if (filters.value.shift_id)
      list = list.filter(a => a.shift_id === filters.value.shift_id)
    if (filters.value.staff_id)
      list = list.filter(a => a.staff_id === filters.value.staff_id)
    if (filters.value.date_from)
      list = list.filter(a => a.shift_date >= filters.value.date_from)
    if (filters.value.date_to)
      list = list.filter(a => a.shift_date <= filters.value.date_to)
    return list
  })

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selectedItem.value = { ...item }
    dialog.value = true
  }
  const confirmDelete = item => {
    deleteTarget.value = item
    deleteDialog.value = true
  }

  const handleDelete = async () => {
    saving.value = true
    try {
      await staffShiftStore.deleteAssignment(deleteTarget.value.id)
      deleteDialog.value = false
      showSnack('Assignment removed')
      await staffShiftStore.fetchAssignments()
    } catch {
      showSnack('Failed to remove', 'error')
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
      dialog.value = false
      showSnack(payload.id ? 'Assignment updated' : 'Staff assigned to shift')
      await staffShiftStore.fetchAssignments()
    } catch {
      showSnack('Failed to save', 'error')
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
    n ? ['#3b5bdb', '#2f9e44', '#e67700', '#c92a2a'][n.length % 4] : '#808080'
  const formatDate = d =>
    d
      ? new Date(d).toLocaleDateString([], {
          weekday: 'short',
          month: 'short',
          day: 'numeric'
        })
      : '—'
  const formatTime = t =>
    t
      ? new Date(t).toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit'
        })
      : '—'
  const shiftTypeColor = type =>
    ({
      morning: '#f59e0b',
      afternoon: '#3b82f6',
      evening: '#8b5cf6',
      full_day: '#10b981',
      split: '#ef4444'
    })[type] || '#6b7280'

  onMounted(async () => {
    await Promise.all([
      staffShiftStore.fetchAssignments(),
      shiftStore.fetchShifts(),
      staffStore.fetchStaff()
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
