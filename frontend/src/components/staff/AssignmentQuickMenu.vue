<template>
  <v-menu v-model="open" :close-on-content-click="false" location="bottom" min-width="280">
    <template #activator="{ props: activatorProps }">
      <slot :props="activatorProps" />
    </template>

    <v-card rounded="lg" border elevation="0" min-width="280">
      <v-card-text class="pb-2">
        <template v-if="isEdit">
          <div class="text-caption text-medium-emphasis mb-1">
            {{ staff?.full_name }} · {{ dateLabel }}
          </div>
        </template>

        <template v-else>
          <v-autocomplete
            v-model="staffId"
            :items="staffOptions"
            item-title="full_name"
            item-value="id"
            :label="t('staff.schedule.menu.staff')"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            class="mb-3"
          />
          <AppDatePicker
            v-model="assignDate"
            :label="t('staff.schedule.menu.date')"
            class="mb-3"
          />
        </template>

        <v-select
          v-model="shiftId"
          :items="shifts"
          item-title="name"
          item-value="id"
          :label="t('staff.schedule.menu.choose_shift')"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          class="mb-3"
        >
          <template #item="{ props: itemProps, item: opt }">
            <v-list-item v-bind="itemProps" :subtitle="shiftTimeLabel(opt.raw)">
              <template #prepend>
                <v-icon icon="mdi-circle" size="12" :color="opt.raw.color" />
              </template>
            </v-list-item>
          </template>
        </v-select>

        <template v-if="isEdit">
          <div class="text-caption text-medium-emphasis mb-1">
            {{ t('staff.schedule.menu.mark_attendance') }}
          </div>
          <v-btn-toggle v-model="attendance" color="primary" rounded="lg" density="comfortable" mandatory class="mb-2 w-100">
            <v-btn value="none" class="flex-grow-1">{{ t('staff.schedule.menu.none') }}</v-btn>
            <v-btn value="present" class="flex-grow-1">{{ t('staff.schedule.menu.present') }}</v-btn>
            <v-btn value="late" class="flex-grow-1">{{ t('staff.schedule.menu.late') }}</v-btn>
            <v-btn value="absent" class="flex-grow-1">{{ t('staff.schedule.menu.absent') }}</v-btn>
          </v-btn-toggle>
        </template>

        <v-checkbox
          v-else
          v-model="repeatWeek"
          :label="t('staff.schedule.menu.repeat_week')"
          color="primary"
          density="compact"
          hide-details
          class="mt-n2"
        />
      </v-card-text>

      <v-card-actions class="pt-0">
        <v-btn
          v-if="isEdit"
          variant="text"
          color="error"
          size="small"
          @click="remove"
        >
          {{ t('btn.remove') }}
        </v-btn>
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" size="small" @click="open = false">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" size="small" :loading="saving" @click="save">
          {{ t('btn.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-menu>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils, AppDatePicker } from '@nong-official-dev/core'
  import { useDate } from '@/composables/useDate'
  import { shiftAssignmentService } from '@/api/shiftAssignmentService'
  import { weekRangeFor } from '@/utils/scheduleTime'

  const props = defineProps({
    staff: { type: Object, default: null },
    day: { type: String, default: null },
    assignment: { type: Object, default: null },
    shifts: { type: Array, default: () => [] },
    staffOptions: { type: Array, default: () => [] },
    branchId: { type: String, default: null }
  })

  const emit = defineEmits(['changed'])

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const { formatShortDate } = useDate()

  const open = ref(false)
  const saving = ref(false)
  const shiftId = ref(null)
  const staffId = ref(null)
  const assignDate = ref(null)
  const repeatWeek = ref(false)
  const attendance = ref('none')

  const isEdit = computed(() => !!props.assignment)
  const dateLabel = computed(() => (props.day ? formatShortDate(props.day) : ''))

  function shiftTimeLabel(s) {
    return `${s.start_time?.slice(0, 5)} – ${s.end_time?.slice(0, 5)}`
  }

  // Each time this menu opens (for whichever cell/event it's attached to,
  // v-menu's own activator-slot wiring owns open/close/toggle entirely —
  // no imperative ref-juggling), re-seed the form from the current props.
  watch(open, val => {
    if (!val) return
    shiftId.value = props.assignment?.shift_id ?? null
    staffId.value = props.staff?.id ?? null
    assignDate.value = props.day ?? null
    repeatWeek.value = false
    attendance.value = 'none'
  })

  // Each staff member already has their own branch — resolve from the
  // selected staff rather than the page's branch filter, which is empty
  // when viewing "All Branches" and would otherwise send branch_id: undefined.
  function resolvedBranchId() {
    return props.staffOptions.find(s => s.id === staffId.value)?.branch_id ?? props.branchId ?? null
  }

  async function save() {
    if (!shiftId.value) {
      notif(t('staff.schedule.menu.pick_shift_first'), { type: 'error' })
      return
    }
    if (!isEdit.value && (!staffId.value || !assignDate.value)) {
      notif(t('staff.schedule.menu.pick_staff_date_first'), { type: 'error' })
      return
    }
    saving.value = true
    try {
      if (isEdit.value) {
        const payload = { shift_id: shiftId.value }
        if (attendance.value === 'present') {
          const s = props.shifts.find(x => x.id === shiftId.value)
          payload.actual_start = `${props.day}T${s.start_time.slice(0, 5)}:00+07:00`
          payload.actual_end = `${props.day}T${s.end_time.slice(0, 5)}:00+07:00`
        } else if (attendance.value === 'late') {
          const s = props.shifts.find(x => x.id === shiftId.value)
          const [h, m] = s.start_time.slice(0, 5).split(':').map(Number)
          const lateH = String(Math.floor((h * 60 + m + 15) / 60) % 24).padStart(2, '0')
          const lateM = String((h * 60 + m + 15) % 60).padStart(2, '0')
          payload.actual_start = `${props.day}T${lateH}:${lateM}:00+07:00`
          payload.actual_end = `${props.day}T${s.end_time.slice(0, 5)}:00+07:00`
        } else if (attendance.value === 'absent') {
          payload.actual_start = null
          payload.actual_end = null
        }
        await shiftAssignmentService.update(props.assignment.id, payload)
        open.value = false
        notif(t('messages.saved_success'), { type: 'success' })
      } else if (repeatWeek.value) {
        const days = weekRangeFor(assignDate.value).days
        const branchId = resolvedBranchId()
        let created = 0
        let skipped = 0
        for (const day of days) {
          try {
            await shiftAssignmentService.create({
              shift_id: shiftId.value,
              staff_id: staffId.value,
              branch_id: branchId,
              shift_date: day
            })
            created++
          } catch {
            // Most likely the unique (shift_id, staff_id, shift_date)
            // constraint — already assigned that day, skip it.
            skipped++
          }
        }
        open.value = false
        notif(t('staff.schedule.menu.repeat_result', { created, skipped }), { type: 'success' })
      } else {
        await shiftAssignmentService.create({
          shift_id: shiftId.value,
          staff_id: staffId.value,
          branch_id: resolvedBranchId(),
          shift_date: assignDate.value
        })
        open.value = false
        notif(t('messages.saved_success'), { type: 'success' })
      }
      emit('changed')
    } catch (err) {
      notif(err?.response?.data?.message || t('common.error'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  function remove() {
    confirm({
      title: t('staff.schedule.menu.remove_title'),
      message: t('staff.schedule.menu.remove_message'),
      options: { type: 'warning', width: 400 },
      agree: async () => {
        try {
          await shiftAssignmentService.delete(props.assignment.id)
          open.value = false
          notif(t('messages.deleted_success'), { type: 'success' })
          emit('changed')
        } catch (err) {
          notif(err?.response?.data?.message || t('common.error'), { type: 'error' })
        }
      }
    })
  }
</script>
