<template>
  <custom-title title="Shifts" subtitle="Define reusable shift schedules">
    <template #right>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        rounded="lg"
        @click="openCreate"
      >
        New Shift
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
    <v-row align="center" class="mb-4 px-1">
      <v-col cols="12" sm="auto">
        <v-btn-toggle
          v-model="filter"
          color="primary"
          variant="tonal"
          rounded="lg"
          mandatory
        >
          <v-btn value="all" size="small" class="text-none px-4">All</v-btn>
          <v-btn value="active" size="small" class="text-none px-4">
            Active
          </v-btn>
          <v-btn value="inactive" size="small" class="text-none px-4">
            Inactive
          </v-btn>
        </v-btn-toggle>
      </v-col>
      <v-spacer />
      <v-col cols="12" sm="auto">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          placeholder="Search shifts..."
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          max-width="280"
          bg-color="white"
        />
      </v-col>
    </v-row>

    <!-- ── Shift Cards ────────────────────────────────────────────────────────── -->
    <v-row v-if="!loading" dense>
      <v-col
        v-for="shift in shiftList"
        :key="shift.id"
        cols="12"
        sm="6"
        lg="4"
      >
        <v-card rounded="xl" border elevation="0" class="shift-card">
          <!-- Top color bar by shift type -->
          <div
            class="shift-bar"
            :style="{ background: shiftTypeColor(shift.shift_type) }"
          />

          <v-card-text class="pa-4">
            <div class="d-flex align-start justify-space-between mb-3">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{ shift.name }}
                </div>
                <v-chip
                  size="x-small"
                  variant="tonal"
                  :color="shiftTypeColor(shift.shift_type)"
                  class="mt-1"
                >
                  {{ shift.shift_type || 'custom' }}
                </v-chip>
              </div>
              <v-badge
                :color="shift.is_active ? 'success' : 'grey'"
                dot
                inline
              />
            </div>

            <!-- Time display -->
            <div class="d-flex align-center gap-3 mb-3">
              <div class="time-block">
                <div class="text-h6 font-weight-bold font-mono">
                  {{ shift.start_time }}
                </div>
                <div class="text-caption text-grey">Start</div>
              </div>
              <div class="d-flex flex-column align-center">
                <v-icon
                  icon="mdi-arrow-right"
                  color="grey-lighten-2"
                  size="20"
                />
                <div class="text-caption text-primary font-weight-medium">
                  {{ calcDuration(shift) }}
                </div>
              </div>
              <div class="time-block">
                <div class="text-h6 font-weight-bold font-mono">
                  {{ shift.end_time }}
                  <v-icon
                    v-if="isOvernight(shift)"
                    icon="mdi-weather-night"
                    size="14"
                    color="indigo"
                    class="ml-1"
                  />
                </div>
                <div class="text-caption text-grey">End</div>
              </div>
            </div>

            <v-divider class="mb-3" />

            <div class="d-flex justify-space-between align-center">
              <div class="d-flex gap-2">
                <v-chip
                  size="x-small"
                  variant="tonal"
                  color="orange"
                  prepend-icon="mdi-coffee-outline"
                >
                  {{ shift.break_minutes ?? 0 }}m break
                </v-chip>
                <v-chip
                  v-if="isOvernight(shift)"
                  size="x-small"
                  variant="tonal"
                  color="indigo"
                  prepend-icon="mdi-weather-night"
                >
                  Overnight
                </v-chip>
              </div>
              <div class="d-flex gap-1">
                <!-- Assign staff to this shift -->
                <v-tooltip text="Assign Staff">
                  <template #activator="{ props }">
                    <v-btn
                      v-bind="props"
                      icon="mdi-account-plus-outline"
                      size="x-small"
                      variant="tonal"
                      color="success"
                      :to="{
                        name: 'ShiftAssignments',
                        query: { shift_id: shift.id }
                      }"
                    />
                  </template>
                </v-tooltip>
                <v-btn
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="tonal"
                  @click="openEdit(shift)"
                />
                <v-btn
                  icon="mdi-delete-outline"
                  size="x-small"
                  variant="tonal"
                  color="error"
                  @click="confirmDelete(shift)"
                />
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Empty state -->
      <v-col v-if="!shiftList.length" cols="12">
        <div class="text-center py-12">
          <v-icon
            icon="mdi-clock-outline"
            size="56"
            color="grey-lighten-1"
            class="mb-3"
          />
          <p class="text-body-1 text-medium-emphasis">No shifts defined yet</p>
          <v-btn
            color="primary"
            variant="tonal"
            prepend-icon="mdi-plus"
            class="mt-3"
            @click="openCreate"
          >
            Create First Shift
          </v-btn>
        </div>
      </v-col>
    </v-row>

    <!-- Skeleton -->
    <v-row v-else dense>
      <v-col v-for="n in 6" :key="n" cols="12" sm="6" lg="4">
        <v-skeleton-loader type="card" rounded="xl" border />
      </v-col>
    </v-row>
  </v-container>

  <!-- ── Form Dialog ─────────────────────────────────────────────────────────── -->
  <ShiftFormDialog
    v-model="dialog"
    :item="selectedItem"
    :loading="saving"
    @save="handleSave"
  />

  <!-- ── Delete Confirm ──────────────────────────────────────────────────────── -->
  <v-dialog v-model="deleteDialog" max-width="400" persistent>
    <v-card rounded="xl" elevation="0" border>
      <v-card-text class="pa-6 text-center">
        <v-avatar color="error" size="56" rounded="lg" class="mb-4">
          <v-icon icon="mdi-delete-outline" size="28" />
        </v-avatar>
        <h3 class="text-h6 font-weight-bold mb-2">Delete Shift?</h3>
        <p class="text-body-2 text-medium-emphasis">
          <strong>{{ deleteTarget?.name }}</strong>
          will be deleted. All staff assignments for this shift will also be
          removed.
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
          Delete
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
{{ shiftList }}
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
  import { useShiftStore } from '@/stores/shiftStore'
  import ShiftFormDialog from '@/components/staff/ShiftFormDialog.vue'

  const shiftStore = useShiftStore()
  const { shiftList, loading } = storeToRefs(shiftStore)

  const search = ref('')
  const filter = ref('all')
  const dialog = ref(false)
  const deleteDialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)
  const deleteTarget = ref(null)
  const snackbar = ref({ show: false, message: '', color: 'success' })

  const showSnack = (message, color = 'success') => {
    snackbar.value = { show: true, message, color }
  }

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => [
    {
      label: 'Total Shifts',
      value: shiftList.value.length,
      icon: 'mdi-clock-outline',
      color: 'primary'
    },
    {
      label: 'Active',
      value: shiftList.value.filter(s => s.is_active).length,
      icon: 'mdi-check-circle-outline',
      color: 'success'
    },
    {
      label: 'Inactive',
      value: shiftList.value.filter(s => !s.is_active).length,
      icon: 'mdi-minus-circle-outline',
      color: 'grey'
    },
    {
      label: 'Overnight',
      value: shiftList.value.filter(s => isOvernight(s)).length,
      icon: 'mdi-weather-night',
      color: 'indigo'
    }
  ])


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
      await shiftStore.deleteShift(deleteTarget.value.id)
      deleteDialog.value = false
      showSnack(`${deleteTarget.value.name} deleted`)
      await shiftStore.fetchShifts()
    } catch {
      showSnack('Failed to delete', 'error')
    } finally {
      saving.value = false
    }
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      payload.id
        ? await shiftStore.updateShift(payload.id, payload)
        : await shiftStore.createShift(payload)
      dialog.value = false
      showSnack(payload.id ? 'Shift updated' : 'Shift created')
      await shiftStore.fetchShifts()
    } catch {
      showSnack('Failed to save', 'error')
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const isOvernight = shift => shift.end_time < shift.start_time

  const calcDuration = shift => {
    if (!shift.start_time || !shift.end_time) return '—'
    const [sh, sm] = shift.start_time.split(':').map(Number)
    let [eh, em] = shift.end_time.split(':').map(Number)
    let totalMins = eh * 60 + em - (sh * 60 + sm)
    if (totalMins <= 0) totalMins += 24 * 60 // overnight
    totalMins -= shift.break_minutes ?? 0
    const h = Math.floor(totalMins / 60)
    const m = totalMins % 60
    return `${h}h ${String(m).padStart(2, '0')}m`
  }

  const shiftTypeColor = type =>
    ({
      morning: '#f59e0b',
      afternoon: '#3b82f6',
      evening: '#8b5cf6',
      full_day: '#10b981',
      split: '#ef4444'
    })[type] || '#6b7280'

  onMounted(() => shiftStore.fetchShifts())
</script>

<style scoped>
  .shift-card {
    transition: all 0.2s ease;
    overflow: hidden;
  }
  .shift-card:hover {
    transform: translateY(-2px);
    border-color: rgb(var(--v-theme-primary)) !important;
  }
  .shift-bar {
    height: 4px;
    width: 100%;
  }
  .time-block {
    min-width: 72px;
  }
  .font-mono {
    font-family: 'Courier New', monospace;
    letter-spacing: 0.05em;
  }
</style>
