<template>
  <div>
    <div v-if="filteredMenus.length" class="d-flex flex-column menu-card-list">
      <v-card
        v-for="item in filteredMenus"
        :key="item.id"
        rounded="lg"
        elevation="0"
        border
        :class="{ 'menu-card--inactive': !item.is_active }"
      >
        <!-- ── Header ─────────────────────────────────────────────────── -->
        <div class="d-flex align-center flex-wrap ga-3 pa-4">
          <v-avatar color="primary" variant="tonal" size="36" rounded="md">
            <v-icon icon="mdi-book-open-page-variant-outline" size="18" />
          </v-avatar>
          <div class="flex-grow-1" style="min-width: 180px">
            <div class="d-flex align-center flex-wrap ga-2">
              <span class="text-subtitle-1 font-weight-bold">{{ item.name }}</span>
              <v-chip v-if="item.is_default" size="x-small" color="primary" variant="flat" prepend-icon="mdi-star">
                {{ t('menus.list.default_badge') }}
              </v-chip>
              <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ branchCountFor(item.id) ? t('menus.list.branches_count', { n: branchCountFor(item.id) }) : t('menus.card.no_branches') }}
            </div>
          </div>
          <div class="d-flex align-center ga-1">
            <v-btn
              icon="mdi-pencil-outline"
              size="36"
              variant="text"
              color="primary"
              :aria-label="t('btn.edit')"
              @click="startEdit(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="36"
              variant="text"
              color="error"
              :aria-label="t('btn.delete')"
              @click="confirmDelete(item)"
            />
          </div>
        </div>

        <v-divider />

        <!-- ── Branch assignments ─────────────────────────────────────── -->
        <div class="pa-4 d-flex flex-column ga-3">
          <template v-if="assignmentsFor(item.id).length">
            <div
              v-for="a in assignmentsFor(item.id)"
              :key="a.id"
              class="branch-row d-flex align-center flex-wrap ga-4"
            >
              <div class="d-flex align-center ga-2 branch-row__col">
                <v-icon icon="mdi-store-outline" size="16" class="text-medium-emphasis" />
                <span class="text-body-2">{{ a.branch?.name ?? '—' }}</span>
              </div>
              <div class="d-flex align-center ga-1 branch-row__col">
                <v-icon icon="mdi-clock-outline" size="14" class="text-medium-emphasis" />
                <span class="text-body-2">{{ timeLabel(a) }}</span>
              </div>
              <div class="text-body-2 branch-row__col">{{ daysLabel(a.days_of_week) }}</div>
              <div class="d-flex align-center ga-2 branch-row__col">
                <span class="status-dot" :class="`status-dot--${liveStatus(a).state}`" />
                <span class="text-body-2 text-medium-emphasis">{{ liveStatus(a).label }}</span>
              </div>
              <v-spacer />
              <v-btn
                size="small"
                variant="tonal"
                rounded="lg"
                :aria-label="t('btn.edit')"
                @click="openEditAssignment(item, a)"
              >
                {{ t('btn.edit') }}
              </v-btn>
              <v-btn
                icon="mdi-close-circle-outline"
                size="36"
                variant="text"
                color="error"
                :aria-label="t('menus.card.remove_from_branch')"
                @click="confirmRemoveAssignment(a.id)"
              />
            </div>
          </template>

          <div v-else class="d-flex align-center ga-2 no-branches-warning pa-3 rounded-lg">
            <v-icon icon="mdi-alert-outline" color="warning" size="18" />
            <span class="text-body-2">{{ t('menus.card.no_branches_warning') }}</span>
          </div>
        </div>

        <v-divider />

        <div class="pa-3">
          <v-btn
            block
            variant="outlined"
            rounded="lg"
            class="assign-branch-btn"
            prepend-icon="mdi-plus"
            @click="openAssignForMenu(item)"
          >
            {{ t('menus.card.assign_to_branch') }}
          </v-btn>
        </div>
      </v-card>
    </div>

    <v-card v-else rounded="lg" elevation="0" border>
      <div class="text-center py-10">
        <v-icon icon="mdi-book-open-variant" size="40" color="grey-lighten-1" />
        <p class="text-medium-emphasis mt-2 mb-0">{{ t('menus.list.empty') }}</p>
      </div>
    </v-card>

    <!-- Create / edit menu -->
    <MenuFormDialog v-model="menuDialog" :edit-item="editMenuItem" @saved="submitMenu" />

    <!-- Assign to branch / edit assignment -->
    <BranchMenuFormDialog
      v-model="assignDialog"
      :edit-item="editAssignItem"
      :preset-menu-id="presetMenuId"
      :branches="branchStore.branches"
      :menus="menuStore.menus"
      @saved="confirmAssign"
    />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils, AppStatusChip } from '@nong-official-dev/core'
  import { useMenuStore } from '@/stores/menuStore'
  import { useBranchMenuStore } from '@/stores/branchMenuStore'
  import { useBranchStore } from '@/stores/branchStore'
  import MenuFormDialog from '@/components/catalogs/MenuFormDialog.vue'
  import BranchMenuFormDialog from '@/components/catalogs/BranchMenuFormDialog.vue'

  const props = defineProps({
    search: { type: String, default: '' },
    canManage: { type: Boolean, default: false }
  })

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  const menuStore = useMenuStore()
  const branchStore = useBranchStore()
  const branchMenuStore = useBranchMenuStore()

  const saving = ref(false)

  async function load() {
    await Promise.all([
      menuStore.fetchMenus({ perPage: -1 }),
      branchMenuStore.fetchAll(),
      branchStore.fetchBranches({ perPage: -1 })
    ])
  }

  onMounted(load)

  function errorMessage(err) {
    return err?.response?.data?.message ?? err?.response?.data?.errors?.[0] ?? t('common.error')
  }

  const filteredMenus = computed(() => {
    const q = props.search.trim().toLowerCase()
    if (!q) return menuStore.menus
    return menuStore.menus.filter(m => m.name?.toLowerCase().includes(q))
  })

  // Branch assignments for a menu, derived from the already-loaded
  // branch-menus list (menuStore's list endpoint doesn't eager-load
  // branches — see CLAUDE.md notes on MenuRepository — so this composes
  // client-side instead of adding a new endpoint / causing N+1 requests).
  const assignmentsFor = menuId => branchMenuStore.getBranchesByMenu(menuId)
  const branchCountFor = menuId => assignmentsFor(menuId).length

  // ── Days-of-week shorthand ───────────────────────────────────────────────
  const DAY_LABEL_KEYS = [
    'menus.assign.day_sun',
    'menus.assign.day_mon',
    'menus.assign.day_tue',
    'menus.assign.day_wed',
    'menus.assign.day_thu',
    'menus.assign.day_fri',
    'menus.assign.day_sat'
  ]
  const sameDaySet = (a, b) => a.length === b.length && a.every(v => b.includes(v))

  function daysLabel(days) {
    if (!days?.length || days.length === 7) return t('menus.assign.every_day')
    const sorted = [...days].sort((x, y) => x - y)
    if (sameDaySet(sorted, [1, 2, 3, 4, 5])) {
      return `${t('menus.assign.day_mon')} – ${t('menus.assign.day_fri')}`
    }
    if (sameDaySet(sorted, [0, 6])) {
      return `${t('menus.assign.day_sat')} – ${t('menus.assign.day_sun')}`
    }
    return sorted.map(d => t(DAY_LABEL_KEYS[d])).join(', ')
  }

  // ── Time window ──────────────────────────────────────────────────────────
  function timeLabel(a) {
    if (!a.available_from && !a.available_until) return t('menus.card.all_day')
    const from = a.available_from ? a.available_from.slice(0, 5) : '00:00'
    const until = a.available_until ? a.available_until.slice(0, 5) : '23:59'
    return `${from} – ${until}`
  }

  // ── Live status — fixed to Asia/Phnom_Penh since no tenant/branch
  // timezone column exists in the schema (see CLAUDE.md / research notes);
  // this also fixes the prior "available now" check using naive browser-
  // local time instead of any real timezone. ────────────────────────────────
  const PHNOM_PENH_TZ = 'Asia/Phnom_Penh'
  const WEEKDAY_INDEX = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }

  function nowInPhnomPenh() {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone: PHNOM_PENH_TZ,
      weekday: 'short',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    }).formatToParts(new Date())
    const map = Object.fromEntries(parts.map(p => [p.type, p.value]))
    const hour = map.hour === '24' ? '00' : map.hour
    return { day: WEEKDAY_INDEX[map.weekday], time: `${hour}:${map.minute}` }
  }

  function liveStatus(a) {
    if (a.is_active === false) return { state: 'off', label: t('menus.card.off') }
    const { day, time } = nowInPhnomPenh()
    const daysOk = !a.days_of_week?.length || a.days_of_week.includes(day)
    const fromOk = !a.available_from || time >= a.available_from.slice(0, 5)
    const untilOk = !a.available_until || time <= a.available_until.slice(0, 5)
    return daysOk && fromOk && untilOk
      ? { state: 'serving', label: t('menus.card.serving_now') }
      : { state: 'not_now', label: t('menus.card.not_now') }
  }

  // ── Create / edit menu ───────────────────────────────────────────────────
  // menuStore.createMenu/updateMenu/deleteMenu already patch `menus` in
  // place (see stores/menuStore.js) — no need to re-fetch the whole list
  // after a save, only the affected card's data changes.
  const menuDialog = ref(false)
  const editMenuItem = ref(null)

  function startEdit(item) {
    editMenuItem.value = { ...item }
    menuDialog.value = true
  }

  async function submitMenu(payload) {
    saving.value = true
    try {
      if (payload.id) {
        await menuStore.updateMenu(payload.id, payload)
      } else {
        await menuStore.createMenu(payload)
      }
      menuDialog.value = false
      notif(t('messages.saved_success'), { type: 'success' })
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Delete menu ──────────────────────────────────────────────────────────
  function confirmDelete(menu) {
    const branchCount = branchCountFor(menu.id)
    confirm({
      title: t('menus.list.confirm_delete.title'),
      message: branchCount
        ? t('menus.list.confirm_delete.message_with_branches', { name: menu.name, n: branchCount })
        : t('menus.list.confirm_delete.message', { name: menu.name }),
      options: { type: 'warning', color: 'warning', width: 420 },
      agree: async () => {
        await menuStore.deleteMenu(menu.id)
        notif(t('messages.deleted_success'), { type: 'success' })
      }
    })
  }

  // ── Assign to branch / edit assignment ──────────────────────────────────
  // branchMenuStore.create/update/remove already patch `branchMenus` in
  // place — same no-full-reload reasoning as menus above.
  const assignDialog = ref(false)
  const editAssignItem = ref(null)
  const presetMenuId = ref(null)

  function openAssignForMenu(menu) {
    editAssignItem.value = null
    presetMenuId.value = menu.id
    assignDialog.value = true
  }

  function openEditAssignment(menu, assignment) {
    editAssignItem.value = { ...assignment }
    presetMenuId.value = null
    assignDialog.value = true
  }

  async function confirmAssign(payload) {
    saving.value = true
    try {
      if (payload.id) {
        await branchMenuStore.update(payload.id, payload)
        notif(t('menus.list.messages.assignment_updated'), { type: 'success' })
      } else {
        await branchMenuStore.create(payload)
        notif(t('menus.list.messages.menu_assigned'), { type: 'success' })
      }
      assignDialog.value = false
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  function confirmRemoveAssignment(assignmentId) {
    confirm({
      title: t('menus.card.remove_title'),
      message: t('menus.card.remove_message'),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        const res = await branchMenuStore.remove(assignmentId)
        if (res.success) {
          assignDialog.value = false
          notif(t('messages.deleted_success'), { type: 'success' })
        } else {
          notif(res.error ?? t('common.error'), { type: 'error' })
        }
      }
    })
  }

  defineExpose({
    openCreateMenu: () => {
      editMenuItem.value = null
      menuDialog.value = true
    }
  })
</script>

<style scoped>
  .menu-card-list {
    gap: 12px;
  }
  .menu-card--inactive {
    opacity: 0.7;
  }
  .branch-row {
    padding: 8px 0;
  }
  .branch-row + .branch-row {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .branch-row__col {
    min-width: 130px;
  }
  .no-branches-warning {
    background: rgba(var(--v-theme-warning), 0.12);
  }
  .assign-branch-btn {
    border-style: dashed !important;
  }
  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex: none;
  }
  .status-dot--serving {
    background: rgb(var(--v-theme-success));
  }
  .status-dot--not_now,
  .status-dot--off {
    background: rgb(var(--v-theme-on-surface));
    opacity: 0.38;
  }
</style>
