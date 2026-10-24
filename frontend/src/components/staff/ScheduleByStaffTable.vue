<template>
  <v-card rounded="lg" border flat>
    <v-data-table
      :headers="headers"
      :items="staffList"
      item-value="id"
      :items-per-page="-1"
      hide-default-footer
      class="schedule-by-staff"
    >
      <template #item="{ item }">
        <tr>
          <td>
            <div class="d-flex align-center py-2">
              <v-avatar :color="avatarColor(item.full_name)" size="32" variant="tonal" class="mr-3" rounded="sm">
                <span class="text-caption font-weight-black">{{ initials(item.full_name) }}</span>
              </v-avatar>
              <div>
                <div class="text-body-2 font-weight-bold">{{ item.full_name }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.role_name }} · {{ t('staff.schedule.hours_this_week', { hours: weeklyHours(item.id) }) }}
                </div>
              </div>
            </div>
          </td>

          <td
            v-for="day in weekDays"
            :key="day"
            class="text-center"
            :class="{ 'bg-primary-lighten-5': day === todayStr }"
          >
            <template v-if="assignmentFor(item.id, day)">
              <AssignmentQuickMenu
                :staff="item"
                :day="day"
                :assignment="assignmentFor(item.id, day)"
                :shifts="shifts"
                :staff-options="staffList"
                @changed="emit('changed')"
              >
                <template #default="{ props: activatorProps }">
                  <v-chip
                    v-bind="activatorProps"
                    :color="assignmentFor(item.id, day).shift?.color"
                    size="small"
                    variant="flat"
                    class="mb-1"
                  >
                    {{ assignmentFor(item.id, day).shift?.name }}
                  </v-chip>
                </template>
              </AssignmentQuickMenu>
              <div v-if="day <= todayStr">
                <v-chip
                  :color="attendanceBadge(assignmentFor(item.id, day)).color"
                  size="x-small"
                  variant="tonal"
                  :prepend-icon="attendanceBadge(assignmentFor(item.id, day)).icon"
                >
                  {{ attendanceBadge(assignmentFor(item.id, day)).label }}
                </v-chip>
              </div>
            </template>
            <AssignmentQuickMenu
              v-else
              :staff="item"
              :day="day"
              :assignment="null"
              :shifts="shifts"
              :staff-options="staffList"
              @changed="emit('changed')"
            >
              <template #default="{ props: activatorProps }">
                <v-btn
                  v-bind="activatorProps"
                  icon="mdi-plus"
                  variant="text"
                  size="small"
                  :aria-label="t('staff.schedule.menu.assign')"
                />
              </template>
            </AssignmentQuickMenu>
          </td>
        </tr>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAvatar, AVATAR_HEX_PALETTE } from '@/composables/useAvatar'
  import { useDate } from '@/composables/useDate'
  import {
    nowInPhnomPenh,
    deriveStatus,
    minutesLate,
    assignmentDurationMinutes
  } from '@/utils/scheduleTime'
  import AssignmentQuickMenu from '@/components/staff/AssignmentQuickMenu.vue'

  const props = defineProps({
    staffList: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    weekDays: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] }
  })

  const emit = defineEmits(['changed'])

  const { t } = useI18n()
  const { getInitials, getAvatarColor } = useAvatar()
  const { formatShortDate } = useDate()

  const initials = n => getInitials(n)
  const avatarColor = n => getAvatarColor(n, { palette: AVATAR_HEX_PALETTE, fallback: '#808080' })

  const todayStr = computed(() => nowInPhnomPenh().dateStr)

  const headers = computed(() => [
    { title: t('staff.table.name'), key: 'full_name', sortable: false },
    ...props.weekDays.map(day => ({
      title: formatShortDate(day),
      key: day,
      sortable: false,
      align: 'center',
      class: day === todayStr.value ? 'bg-primary-lighten-5 font-weight-bold' : undefined
    }))
  ])

  function assignmentFor(staffId, day) {
    return props.assignments.find(a => a.staff_id === staffId && a.shift_date === day) ?? null
  }

  function weeklyHours(staffId) {
    const mins = props.assignments
      .filter(a => a.staff_id === staffId)
      .reduce((sum, a) => sum + assignmentDurationMinutes(a), 0)
    return Math.round(mins / 60)
  }

  function attendanceBadge(a) {
    const now = nowInPhnomPenh()
    const status = deriveStatus(a, now)
    const late = minutesLate(a)
    if (status === 'completed' || status === 'clocked_in') {
      if (late > 5) {
        return { color: 'warning', icon: 'mdi-clock-alert', label: t('staff.schedule.badge.late', { m: late }) }
      }
      return status === 'completed'
        ? { color: 'success', icon: 'mdi-check', label: t('staff.schedule.badge.present') }
        : { color: 'primary', icon: 'mdi-clock-outline', label: t('staff.schedule.badge.on_shift') }
    }
    if (status === 'absent') {
      return { color: 'error', icon: 'mdi-close', label: t('staff.schedule.badge.absent') }
    }
    return { color: 'default', icon: 'mdi-circle-outline', label: t('staff.schedule.badge.scheduled') }
  }

</script>

<style scoped>
  .schedule-by-staff :deep(td) {
    vertical-align: middle;
    min-width: 110px;
  }
</style>
