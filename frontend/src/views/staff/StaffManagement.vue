<script setup>
  import { ref, computed, onMounted, watch } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useStaffStore } from '@/stores/staffStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useRoleStore } from '@/stores/roleStore'
  import { useShiftStore } from '@/stores/shiftStore'
  import { getAllStaffApi } from '@/api/staffService'
  import { shiftAssignmentService } from '@/api/shiftAssignmentService'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import StaffDialogForm from '@/components/staff/StaffDialogForm.vue'
  import ScheduleTab from '@/components/staff/ScheduleTab.vue'
  import ManageShiftsDrawer from '@/components/staff/ManageShiftsDrawer.vue'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import { useI18n } from 'vue-i18n'
  import { useAvatar, AVATAR_HEX_PALETTE } from '@/composables/useAvatar'
  import {
    nowInPhnomPenh,
    weekRangeFor,
    deriveStatus,
    isWithinShiftWindow,
    assignmentDurationMinutes,
    formatHoursMinutes
  } from '@/utils/scheduleTime'

  const { t } = useI18n()
  const { getInitials, getAvatarColor } = useAvatar()
  const staffStore = useStaffStore()
  const authStore = useAuthStore()
  const roleStore = useRoleStore()
  const shiftStore = useShiftStore()
  const { notif } = useAppUtils()

  const { roles } = storeToRefs(roleStore)

  // Branch context comes from the global sidebar branch switcher (see
  // BranchSwitcher.vue / authStore.activeBranchId) — no separate branch
  // filter on this page, matching OrdersManagement.vue/KitchenDisplay.vue.
  const branchId = computed(
    () => authStore.activeBranchId || authStore.branch_id
  )

  const tableRef = ref(null)
  const roleFilter = ref(null)
  const teamSearch = ref('')
  const dialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)
  const activeView = ref('schedule')
  // Rendered at the page root (not nested inside <v-window>/<v-window-item>)
  // — v-window's slide transition applies a CSS transform to its content,
  // which creates a new containing block for position:fixed descendants.
  // A temporary v-navigation-drawer nested inside it gets its scrim/panel
  // confined to that transformed ancestor's box instead of the viewport.
  const manageShiftsOpen = ref(false)

  // ── Role options ───────────────────────────────────────────────────────────
  const roleOptions = computed(() => roles.value ?? [])

  // ── Table headers — only employee_code is in StaffRepository::ALLOWED_SORTS;
  // the rest fall back to a silent latest()-sort server-side, so they're not
  // marked sortable to avoid implying an order that isn't happening. ──────────
  const headers = computed(() => [
    { title: t('staff.table.name'), key: 'full_name', sortable: false },
    { title: t('staff.table.role'), key: 'role_name', sortable: false },
    { title: t('staff.table.compensation'), key: 'salary', sortable: false },
    { title: t('staff.table.this_week'), key: 'this_week', sortable: false },
    { title: t('staff.table.status'), key: 'is_active', sortable: false },
    {
      title: t('staff.table.actions'),
      key: 'actions',
      sortable: false,
      width: '10%'
    }
  ])

  // ── Server-driven filters — matches StaffRepository's contract
  // (search/sortBy/sortDesc/page/perPage + branch_id/role_id). The Team tab's
  // own v-text-field feeds `search` here instead of AppTable's built-in one
  // (disabled via :show-search="false") so it can sit inside the v-card-title
  // next to the role filter. ────────────────────────────────────────────────
  const filters = computed(() => ({
    branch_id: branchId.value || undefined,
    role_id: roleFilter.value || undefined,
    search: teamSearch.value || undefined
  }))

  async function fetchStaffForTable(params) {
    const { data } = await getAllStaffApi(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Actions ────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }

  const openEdit = item => {
    selectedItem.value = { ...item }
    dialog.value = true
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
      tableRef.value?.refresh()
      loadRoster()
    } catch {
      notif('Failed to save staff', { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const toggleActive = async (item, value) => {
    try {
      await staffStore.updateStaff(item.id, { is_active: value })
      notif(
        value ? `${item.full_name} activated` : `${item.full_name} deactivated`,
        { type: 'success' }
      )
      tableRef.value?.refresh()
      loadRoster()
    } catch {
      notif('Failed to update status', { type: 'error' })
    }
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  const initials = n => getInitials(n)
  const avatarColor = n =>
    getAvatarColor(n, { palette: AVATAR_HEX_PALETTE, fallback: '#808080' })

  // ── "This week" hours — derived from the same weekAssignments list the
  // summary cards use, so no extra fetch is needed for the Team table. ───────
  const weeklyHoursLabel = staffId => {
    const minutes = weekAssignments.value
      .filter(a => a.staff_id === staffId)
      .reduce((sum, a) => sum + assignmentDurationMinutes(a), 0)
    return formatHoursMinutes(minutes)
  }

  // ── Summary cards — "roster" is the full (unpaginated) staff list for the
  // selected branch, separate from the Team tab's own server-paginated
  // AppTable fetch, so changing the Team tab's page/sort/search never
  // affects these counts. ─────────────────────────────────────────────────────
  const roster = ref([])
  async function loadRoster() {
    const { data } = await getAllStaffApi({
      branch_id: branchId.value || undefined,
      perPage: -1
    })
    roster.value = data.data
  }

  const activeStaffCount = computed(
    () => roster.value.filter(s => s.is_active).length
  )

  // ── This week's assignments — a LOCAL list, not the shared
  // shiftAssignmentStore. ScheduleTab (below) independently browses other
  // weeks via that same store's actions; if these summary cards also read
  // the store's shared `assignmentList`, navigating a week in the Schedule
  // tab would silently corrupt "today"/"this week" here. Mutations (create/
  // update/delete) still go through the store; only the read/list state is
  // kept local to each consumer. ───────────────────────────────────────────
  const weekAssignments = ref([])

  async function loadWeekAssignments() {
    const { from, to } = weekRangeFor()
    const { data } = await shiftAssignmentService.getAll({
      branch_id: branchId.value || undefined,
      date_from: from,
      date_to: to,
      perPage: -1
    })
    weekAssignments.value = data.data
  }

  const scheduledTodayCount = computed(() => {
    const { dateStr } = nowInPhnomPenh()
    return weekAssignments.value.filter(a => a.shift_date === dateStr).length
  })

  const onShiftNowCount = computed(() => {
    const now = nowInPhnomPenh()
    return weekAssignments.value.filter(
      a => !a.actual_end && isWithinShiftWindow(a, now)
    ).length
  })

  const absentThisWeekCount = computed(() => {
    const now = nowInPhnomPenh()
    return weekAssignments.value.filter(a => deriveStatus(a, now) === 'absent')
      .length
  })

  watch(branchId, () => {
    loadRoster()
    loadWeekAssignments()
  })

  onMounted(() => {
    roleStore.fetchRoles({ perPage: -1 })
    loadRoster()
    loadWeekAssignments()
  })
</script>

<template>
  <v-container fluid class="pa-0">
    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <AppToolbar :title="t('staff.title')" :subtitle="t('staff.subtitle')">
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ t('staff.page.add_staff') }}
        </v-btn>
      </template>
    </AppToolbar>

    <!-- ── Summary ────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card variant="outlined" rounded="lg">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">
              {{ t('staff.page.summary.on_shift_now') }}
            </div>
            <div class="text-h5 font-weight-bold mt-1">
              {{ onShiftNowCount }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="outlined" rounded="lg">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">
              {{ t('staff.page.summary.scheduled_today') }}
            </div>
            <div class="text-h5 font-weight-bold mt-1">
              {{ scheduledTodayCount }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="outlined" rounded="lg">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">
              {{ t('staff.page.summary.absent_this_week') }}
            </div>
            <div class="text-h5 font-weight-bold mt-1">
              {{ absentThisWeekCount }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card variant="outlined" rounded="lg">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">
              {{ t('staff.page.summary.active_staff') }}
            </div>
            <div class="text-h5 font-weight-bold mt-1">
              {{ activeStaffCount }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── View switch ────────────────────────────────────────────────────── -->
    <v-btn-toggle
      v-model="activeView"
      mandatory
      color="primary"
      rounded="lg"
      density="comfortable"
      class="mb-4"
    >
      <v-btn value="schedule" prepend-icon="mdi-calendar-week-outline">
        {{ t('staff.page.schedule_tab') }}
      </v-btn>
      <v-btn value="team" prepend-icon="mdi-account-group-outline">
        {{ t('staff.page.team_tab') }}
      </v-btn>
    </v-btn-toggle>

    <v-window v-model="activeView">
      <!-- ══════════════════ SCHEDULE ══════════════════════ -->
      <v-window-item value="schedule">
        <ScheduleTab
          :branch-id="branchId"
          @changed="loadWeekAssignments"
          @open-manage-shifts="manageShiftsOpen = true"
        />
      </v-window-item>

      <!-- ══════════════════ TEAM ══════════════════════════════════════ -->
      <v-window-item value="team">
        <v-card rounded="lg" border flat class="overflow-hidden">
          <v-card-title class="d-flex flex-wrap align-center ga-2 pa-4">
            <v-text-field
              v-model="teamSearch"
              :placeholder="t('staff.filter.search')"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              rounded="lg"
              clearable
              hide-details
              style="max-width: 280px"
            />
            <v-select
              v-model="roleFilter"
              :items="roleOptions"
              item-title="name"
              item-value="id"
              :placeholder="t('staff.filter.all_roles')"
              variant="outlined"
              density="compact"
              rounded="lg"
              clearable
              hide-details
              style="max-width: 220px"
            />
          </v-card-title>
          <v-divider />

          <div class="pa-4">
            <AppTable
              ref="tableRef"
              :headers="headers"
              :fetch-fn="fetchStaffForTable"
              :filters="filters"
              :show-search="false"
              item-label="staff"
            >
              <!-- Name + code + phone -->
              <template #[`item.full_name`]="{ item }">
                <div
                  class="d-flex align-center py-2"
                  :class="{ 'opacity-60': !item.is_active }"
                >
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
                    <div class="text-caption text-medium-emphasis">
                      {{ item.employee_code }} · {{ item.phone }}
                    </div>
                  </div>
                </div>
              </template>

              <!-- Role -->
              <template #[`item.role_name`]="{ item }">
                <span :class="{ 'opacity-60': !item.is_active }">
                  {{ item.role_name }}
                </span>
              </template>

              <!-- Compensation -->
              <template #[`item.salary`]="{ item }">
                <span :class="{ 'opacity-60': !item.is_active }">
                  {{
                    item.hourly_rate
                      ? `$${item.hourly_rate}/hr`
                      : item.salary
                        ? `$${item.salary}/mo`
                        : '—'
                  }}
                </span>
              </template>

              <!-- This week (hours) -->
              <template #[`item.this_week`]="{ item }">
                <span :class="{ 'opacity-60': !item.is_active }">
                  {{ weeklyHoursLabel(item.id) }}
                </span>
              </template>

              <!-- Status / active toggle -->
              <template #[`item.is_active`]="{ item }">
                <v-switch
                  :model-value="item.is_active"
                  color="success"
                  density="compact"
                  hide-details
                  :label="
                    item.is_active ? t('status.active') : t('status.inactive')
                  "
                  @update:model-value="toggleActive(item, $event)"
                />
              </template>

              <!-- Actions -->
              <template #[`item.actions`]="{ item }">
                <v-btn
                  variant="outlined"
                  size="small"
                  rounded="lg"
                  @click="openEdit(item)"
                >
                  {{ t('btn.edit') }}
                </v-btn>
              </template>
            </AppTable>
          </div>
        </v-card>
      </v-window-item>
    </v-window>

    <!-- ── Dialog ───────────────────────────────────────────────────────────── -->
    <StaffDialogForm
      v-model="dialog"
      :item="selectedItem"
      :loading="saving"
      @save="handleSave"
    />

    <ManageShiftsDrawer
      v-model="manageShiftsOpen"
      @changed="shiftStore.fetchShifts({ perPage: -1 })"
    />
  </v-container>
</template>
