<template>
  <div>
    <!-- ── Week toolbar ─────────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
      <div class="d-flex align-center ga-1">
        <v-btn icon="mdi-chevron-left" variant="text" :aria-label="t('staff.schedule.prev_week')" @click="shiftWeek(-7)" />
        <span class="text-body-1 font-weight-bold mx-1">{{ weekLabel }}</span>
        <v-btn icon="mdi-chevron-right" variant="text" :aria-label="t('staff.schedule.next_week')" @click="shiftWeek(7)" />
        <v-btn variant="outlined" rounded="lg" size="small" class="ml-2" @click="goToday">
          {{ t('staff.schedule.today') }}
        </v-btn>
      </div>
      <div class="d-flex align-center ga-2">
        <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-content-copy" @click="confirmCopyLastWeek">
          {{ t('staff.schedule.copy_last_week') }}
        </v-btn>
        <v-btn-toggle v-model="viewMode" mandatory color="primary" rounded="lg" density="comfortable">
          <v-btn value="staff">{{ t('staff.schedule.by_staff') }}</v-btn>
          <v-btn value="calendar">{{ t('staff.schedule.calendar') }}</v-btn>
        </v-btn-toggle>
      </div>
    </div>

    <!-- ── Shift legend ─────────────────────────────────────────────────── -->
    <div class="d-flex flex-wrap align-center ga-2 mb-4">
      <v-chip
        v-for="s in activeShifts"
        :key="s.id"
        :color="s.color"
        size="small"
        variant="flat"
      >
        {{ s.name }} · {{ s.start_time?.slice(0, 5) }} – {{ s.end_time?.slice(0, 5) }}
      </v-chip>
      <v-btn variant="text" size="small" prepend-icon="mdi-cog-outline" @click="emit('open-manage-shifts')">
        {{ t('staff.schedule.manage_shifts') }}
      </v-btn>
    </div>

    <v-window v-model="viewMode">
      <v-window-item value="staff">
        <ScheduleByStaffTable
          :staff-list="staffList"
          :assignments="assignments"
          :week-days="weekDays"
          :shifts="shiftStore.shiftList"
          @changed="onAssignmentChanged"
        />
      </v-window-item>
      <v-window-item value="calendar">
        <ScheduleCalendarView
          :staff-list="staffList"
          :assignments="assignments"
          :week-days="weekDays"
          :week-start="weekStart"
          :shifts="shiftStore.shiftList"
          :branch-id="branchId"
          @changed="onAssignmentChanged"
        />
      </v-window-item>
    </v-window>
  </div>
</template>

<script setup>
  import { ref, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useShiftStore } from '@/stores/shiftStore'
  import { getAllStaffApi } from '@/api/staffService'
  import { shiftAssignmentService } from '@/api/shiftAssignmentService'
  import { weekRangeFor, addDays } from '@/utils/scheduleTime'
  import ScheduleByStaffTable from '@/components/staff/ScheduleByStaffTable.vue'
  import ScheduleCalendarView from '@/components/staff/ScheduleCalendarView.vue'

  const props = defineProps({
    branchId: { type: String, default: null }
  })
  const emit = defineEmits(['changed', 'open-manage-shifts'])

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const shiftStore = useShiftStore()

  const viewMode = ref('staff')

  // ── Week navigation ──────────────────────────────────────────────────────
  const weekStart = ref(weekRangeFor().from)
  const weekDays = computed(() => weekRangeFor(weekStart.value).days)

  function shiftWeek(days) {
    weekStart.value = addDays(weekStart.value, days)
  }
  function goToday() {
    weekStart.value = weekRangeFor().from
  }

  const weekLabel = computed(() => {
    const start = new Date(`${weekDays.value[0]}T00:00:00`)
    const end = new Date(`${weekDays.value[6]}T00:00:00`)
    const sameMonth = start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()
    if (sameMonth) {
      const monthYear = end.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' })
      return `${start.getDate()} – ${end.getDate()} ${monthYear}`
    }
    const startLabel = start.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
    const endLabel = end.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    return `${startLabel} – ${endLabel}`
  })

  // ── Shifts (shared store — safe, nothing else re-fetches it with
  // different params) ─────────────────────────────────────────────────────
  async function loadShifts() {
    await shiftStore.fetchShifts({ perPage: -1 })
  }
  const activeShifts = computed(() => shiftStore.shiftList.filter(s => s.is_active))

  // ── Staff roster for the selected branch (active only — scheduling an
  // inactive staff member isn't meaningful). ──────────────────────────────
  const staffList = ref([])
  async function loadStaff() {
    const { data } = await getAllStaffApi({
      branch_id: props.branchId || undefined,
      is_active: true,
      perPage: -1
    })
    staffList.value = data.data
  }

  // ── This week's assignments — local list, independent of the shared
  // shiftAssignmentStore (see StaffManagement.vue for why). ───────────────
  const assignments = ref([])
  async function loadAssignments() {
    const { data } = await shiftAssignmentService.getAll({
      branch_id: props.branchId || undefined,
      date_from: weekDays.value[0],
      date_to: weekDays.value[6],
      perPage: -1
    })
    assignments.value = data.data
  }

  async function reload() {
    await Promise.all([loadStaff(), loadAssignments()])
  }

  watch(() => props.branchId, reload)
  watch(weekStart, loadAssignments)

  onMounted(() => {
    loadShifts()
    reload()
  })

  function onAssignmentChanged() {
    loadAssignments()
    emit('changed')
  }

  // ── Copy last week ───────────────────────────────────────────────────────
  function confirmCopyLastWeek() {
    confirm({
      title: t('staff.schedule.copy.title'),
      message: t('staff.schedule.copy.message'),
      options: { type: 'warning', width: 480 },
      agree: copyLastWeek
    })
  }

  async function copyLastWeek() {
    const prevFrom = addDays(weekDays.value[0], -7)
    const prevTo = addDays(weekDays.value[6], -7)
    const { data } = await shiftAssignmentService.getAll({
      branch_id: props.branchId || undefined,
      date_from: prevFrom,
      date_to: prevTo,
      perPage: -1
    })
    const activeStaffIds = new Set(staffList.value.map(s => s.id))
    const sourceRows = data.data.filter(a => activeStaffIds.has(a.staff_id))

    let copied = 0
    let skipped = 0
    for (const a of sourceRows) {
      try {
        await shiftAssignmentService.create({
          shift_id: a.shift_id,
          staff_id: a.staff_id,
          branch_id: a.branch_id,
          shift_date: addDays(a.shift_date, 7)
        })
        copied++
      } catch {
        // Most likely the unique (shift_id, staff_id, shift_date) constraint
        // — that slot's already assigned this week, skip it rather than
        // aborting the whole copy.
        skipped++
      }
    }

    await loadAssignments()
    emit('changed')
    notif(t('staff.schedule.copy.result', { copied, skipped }), { type: 'success' })
  }
</script>
