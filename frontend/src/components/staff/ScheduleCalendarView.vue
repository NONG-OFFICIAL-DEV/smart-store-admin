<template>
  <v-card rounded="lg" border flat class="pa-4">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <v-select
        v-model="staffFilter"
        :items="staffOptions"
        item-title="full_name"
        item-value="id"
        :label="t('staff.schedule.filter_staff')"
        variant="outlined"
        density="compact"
        rounded="lg"
        hide-details
        clearable
        style="max-width: 260px"
      />
    </div>

    <!-- One quick-assign button per weekday — the calendar's own internal
         hour grid isn't reliably slot-interceptable across Vuetify 3
         versions, so "click an empty slot to assign" is surfaced here
         instead. The opened form has its own staff picker, so no staff
         needs to be selected here first. -->
    <div class="d-flex ga-2 mb-3 flex-wrap">
      <AssignmentQuickMenu
        v-for="day in weekDays"
        :key="day"
        :staff="staffFilter ? props.staffList.find(s => s.id === staffFilter) : null"
        :day="day"
        :assignment="null"
        :shifts="shifts"
        :staff-options="staffList"
        :branch-id="branchId"
        @changed="emit('changed')"
      >
        <template #default="{ props: activatorProps }">
          <v-btn
            v-bind="activatorProps"
            size="small"
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-plus"
          >
            {{ formatShortDate(day) }}
          </v-btn>
        </template>
      </AssignmentQuickMenu>
    </div>
    <v-sheet height="400">
      <v-calendar
        :key="weekStart"
        :start="weekStart"
        type="week"
        first-time="05:00"
        :events="calendarEvents"
        :event-color="e => e.color"
      >
        <template #event="{ event }">
          <AssignmentQuickMenu
            :staff="staffList.find(s => s.id === event.assignment.staff_id) ?? { id: event.assignment.staff_id, full_name: staffName(event.assignment.staff_id) }"
            :day="event.assignment.shift_date"
            :assignment="event.assignment"
            :shifts="shifts"
            :staff-options="staffList"
            :branch-id="branchId"
            @changed="emit('changed')"
          >
            <template #default="{ props: activatorProps }">
              <div
                v-bind="activatorProps"
                class="calendar-event-chip px-2 py-1"
                :style="{ cursor: 'pointer' }"
              >
                <span class="text-caption font-weight-bold">{{ event.title }}</span>
              </div>
            </template>
          </AssignmentQuickMenu>
        </template>
      </v-calendar>
    </v-sheet>
  </v-card>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useDate } from '@/composables/useDate'
  import { calendarEventRange } from '@/utils/scheduleTime'
  import AssignmentQuickMenu from '@/components/staff/AssignmentQuickMenu.vue'

  const props = defineProps({
    staffList: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    weekDays: { type: Array, default: () => [] },
    weekStart: { type: String, required: true },
    shifts: { type: Array, default: () => [] },
    branchId: { type: String, default: null }
  })

  const emit = defineEmits(['changed'])

  const { t } = useI18n()
  const { formatShortDate } = useDate()

  const staffFilter = ref(null)
  const staffOptions = computed(() => props.staffList)

  const visibleAssignments = computed(() =>
    staffFilter.value
      ? props.assignments.filter(a => a.staff_id === staffFilter.value)
      : props.assignments
  )

  function staffName(staffId) {
    return props.staffList.find(s => s.id === staffId)?.full_name ?? '—'
  }

  const calendarEvents = computed(() =>
    visibleAssignments.value
      .map(a => {
        const range = calendarEventRange(a)
        if (!range) return null
        return {
          title: `${staffName(a.staff_id)} · ${a.shift?.name ?? ''}`,
          start: range.start,
          end: range.end,
          color: a.shift?.color ?? 'primary',
          assignment: a
        }
      })
      .filter(Boolean)
  )

</script>

<style scoped>
  .calendar-event-chip {
    color: #fff;
    border-radius: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
</style>
