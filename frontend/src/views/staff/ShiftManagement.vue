<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-calendar-clock"
      title="Shifts"
      subtitle="Define reusable shift schedules for your team"
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
          New Shift
        </v-btn>
      </template>
    </custom-title>

    <v-row class="mb-4" dense>
      <v-col v-for="(stat, i) in stats" :key="i" cols="12" sm="6" lg="3">
        <v-card rounded="xl" border flat class="pa-4 d-flex align-center gap-4">
          <v-avatar :color="stat.color + '-lighten-5'" rounded="lg" size="48">
            <v-icon :icon="stat.icon" :color="stat.color" size="22" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-black">{{ stat.value }}</div>
            <div class="text-caption font-weight-medium text-medium-emphasis">
              {{ stat.label }}
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-card flat rounded="xl" border class="pa-3 mb-6">
      <v-row align="center" dense>
        <v-col cols="12" sm="auto">
          <v-btn-toggle
            v-model="filter"
            color="primary"
            variant="tonal"
            rounded="lg"
            mandatory
            density="compact"
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
        <v-col cols="12" sm="4" md="3">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            placeholder="Search shifts..."
            variant="solo-filled"
            density="compact"
            rounded="lg"
            flat
            hide-details
            clearable
          />
        </v-col>
      </v-row>
    </v-card>

    <v-row v-if="!loading" dense>
      <v-col
        v-for="shift in filteredShifts"
        :key="shift.id"
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
            class="shift-card transition-swing"
            :elevation="isPropping ? 4 : 0"
            :alpha="shift.is_active ? 1 : 0.7"
          >
            <div
              :style="`height: 4px; background: ${shiftTypeColor(shift.shift_type)}`"
            />

            <v-card-text class="pa-4">
              <div class="d-flex align-start justify-space-between mb-4">
                <div>
                  <div class="text-subtitle-1 font-weight-black line-height-1">
                    {{ shift.name }}
                  </div>
                  <v-chip
                    size="x-small"
                    variant="tonal"
                    :color="shiftTypeColor(shift.shift_type)"
                    class="mt-1 font-weight-bold"
                  >
                    {{ shift.shift_type || 'custom' }}
                  </v-chip>
                </div>
                <v-badge
                  :color="shift.is_active ? 'success' : 'grey'"
                  dot
                  location="bottom right"
                />
              </div>

              <div
                class="d-flex align-center justify-space-between bg-grey-lighten-5 pa-3 rounded-lg border mb-4"
              >
                <div class="text-center flex-1">
                  <div class="text-h6 font-weight-black letter-spacing-1">
                    {{ shift.start_time }}
                  </div>
                  <div
                    class="text-tiny text-uppercase font-weight-bold text-medium-emphasis"
                  >
                    Start
                  </div>
                </div>

                <div class="d-flex flex-column align-center px-4">
                  <v-icon
                    icon="mdi-arrow-right"
                    color="grey-lighten-2"
                    size="20"
                  />
                  <div class="text-tiny font-weight-black text-primary">
                    {{ calcDuration(shift) }}
                  </div>
                </div>

                <div class="text-center flex-1">
                  <div class="text-h6 font-weight-black letter-spacing-1">
                    {{ shift.end_time }}
                    <v-icon
                      v-if="isOvernight(shift)"
                      icon="mdi-weather-night"
                      size="14"
                      color="indigo"
                      class="ml-1"
                    />
                  </div>
                  <div
                    class="text-tiny text-uppercase font-weight-bold text-medium-emphasis"
                  >
                    End
                  </div>
                </div>
              </div>

              <v-divider class="mb-4" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex gap-2">
                  <v-chip
                    size="x-small"
                    variant="flat"
                    color="orange-lighten-5"
                    class="text-orange-darken-2"
                  >
                    <v-icon start icon="mdi-coffee-outline" size="12" />
                    {{ shift.break_minutes ?? 0 }}m break
                  </v-chip>
                  <v-chip
                    v-if="isOvernight(shift)"
                    size="x-small"
                    variant="flat"
                    color="indigo-lighten-5"
                    class="text-indigo"
                  >
                    <v-icon start icon="mdi-weather-night" size="12" />
                    Overnight
                  </v-chip>
                </div>

                <div class="d-flex gap-1">
                  <v-btn
                    icon="mdi-account-plus-outline"
                    size="x-small"
                    variant="tonal"
                    color="success"
                    :to="{
                      name: 'ShiftAssignments',
                      query: { shift_id: shift.id }
                    }"
                  />
                  <v-btn
                    icon="mdi-pencil-outline"
                    size="x-small"
                    variant="tonal"
                    color="primary"
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
        </v-hover>
      </v-col>
    </v-row>

    <v-row v-else dense>
      <v-col v-for="n in 6" :key="n" cols="12" sm="6" lg="4">
        <v-skeleton-loader type="article" rounded="xl" border />
      </v-col>
    </v-row>

    <v-sheet
      v-if="!filteredShifts.length && !loading"
      class="d-flex flex-column align-center justify-center pa-12 bg-transparent text-center"
    >
      <v-icon size="64" color="grey-lighten-2" icon="mdi-clock-alert-outline" />
      <div class="text-h6 font-weight-bold mt-4">No shifts found</div>
      <v-btn
        color="primary"
        variant="tonal"
        rounded="lg"
        class="mt-4"
        @click="openCreate"
      >
        Create Shift
      </v-btn>
    </v-sheet>
  </v-container>

  <ShiftFormDialog
    v-model="dialog"
    :item="selectedItem"
    :loading="saving"
    @save="handleSave"
  />
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useShiftStore } from '@/stores/shiftStore'
  import { useAppUtils } from '@/composables/useAppUtils' // Using your project utility
  import ShiftFormDialog from '@/components/staff/ShiftFormDialog.vue'

  const shiftStore = useShiftStore()
  const { confirm, notif } = useAppUtils()
  const { shiftList, loading } = storeToRefs(shiftStore)

  const search = ref('')
  const filter = ref('all')
  const dialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)

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
      value: shiftList.value.filter(isOvernight).length,
      icon: 'mdi-weather-night',
      color: 'indigo'
    }
  ])

  // ── Filtered List ─────────────────────────────────────────────────────────────
  const filteredShifts = computed(() => {
    let list = shiftList.value
    if (filter.value === 'active') list = list.filter(s => s.is_active)
    if (filter.value === 'inactive') list = list.filter(s => !s.is_active)
    if (search.value) {
      const q = search.value.toLowerCase()
      list = list.filter(
        s =>
          s.name.toLowerCase().includes(q) ||
          s.shift_type.toLowerCase().includes(q)
      )
    }
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
    confirm({
      title: 'Delete Shift Definition',
      message: `Are you sure you want to delete "${item.name}"? This will remove all associated staff schedules.`,
      options: { type: 'warning', width: 520 },
      agree: async () => {
        saving.value = true
        try {
          await shiftStore.deleteShift(item.id)
          notif('Shift definition removed', { type: 'success' })
          await shiftStore.fetchShifts()
        } catch {
          notif('Failed to remove shift', { type: 'error' })
        } finally {
          saving.value = false
        }
      }
    })
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      payload.id
        ? await shiftStore.updateShift(payload.id, payload)
        : await shiftStore.createShift(payload)
      dialog.value = false
      notif(payload.id ? 'Shift updated' : 'New shift created', {
        type: 'success'
      })
      await shiftStore.fetchShifts()
    } catch {
      notif('Could not save shift data', { type: 'error' })
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
    if (totalMins <= 0) totalMins += 24 * 60
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
  .gap-2 {
    gap: 8px;
  }
  .gap-4 {
    gap: 16px;
  }
  .text-tiny {
    font-size: 0.65rem !important;
  }
  .letter-spacing-1 {
    letter-spacing: 1px;
  }
  .line-height-1 {
    line-height: 1.2;
  }

  .shift-card {
    transition: all 0.25s ease-in-out;
  }
</style>
