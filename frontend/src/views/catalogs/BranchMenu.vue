<template>
  <v-container fluid class="pa-0">
    <div class="d-flex justify-end align-center ga-2 mb-4">
      <v-btn
        :color="showFilters ? 'primary' : 'default'"
        :variant="showFilters ? 'flat' : 'tonal'"
        rounded="lg"
        :prepend-icon="
          showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
        "
        @click="showFilters = !showFilters"
      >
        {{ t('btn.filter') }}
        <!-- badge shows how many filters are active -->
        <v-badge
          v-if="activeFilterCount > 0"
          :content="activeFilterCount"
          color="error"
          floating
        />
      </v-btn>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        rounded="lg"
        elevation="0"
        @click="openCreateDialog"
      >
        {{ t('menus.assign.assign_menu') }}
      </v-btn>
    </div>

    <!-- ── Filters ─────────────────────────────────────────────────────────── -->
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
        <v-card-text>
          <v-row dense align="center">
            <v-col cols="12" md="4">
              <v-select
                v-model="filters.branch_id"
                :items="branchStore.branches.data"
                item-title="name"
                item-value="id"
                :label="t('branch_menu.filter_by_branch')"
                variant="outlined"
                clearable
                hide-details
                prepend-inner-icon="mdi-store"
              />
            </v-col>
            <v-col cols="12" md="4">
              <v-select
                v-model="filters.menu_id"
                :items="menus"
                item-title="name"
                item-value="id"
                :label="t('branch_menu.filter_by_menu')"
                variant="outlined"
                clearable
                hide-details
                prepend-inner-icon="mdi-book-open-outline"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4">
          <v-spacer />
          <v-btn
            v-if="hasActiveFilters"
            rounded="lg"
            variant="tonal"
            color="error"
            prepend-icon="mdi-close"
            @click="resetFilters"
          >
            {{ t('btn.reset') }}
          </v-btn>
          <v-btn
            class="bg-primary"
            rounded="lg"
            prepend-icon="mdi-magnify"
            @click="onFilterChange"
          >
            {{ t('btn.search') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

    <!-- ── Data Table ───────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="store.branchMenus"
        :loading="store.loading"
        item-value="id"
        rounded="lg"
        hover
      >
        <!-- Branch column -->
        <template #item.branch="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar color="primary" size="32" rounded="md">
              <v-icon icon="mdi-store" size="16" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.branch?.name || '—' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.branch?.city || '' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Menu column -->
        <template #item.menu="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar color="success" size="32" rounded="md">
              <v-icon icon="mdi-book-open-outline" size="16" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.menu?.name || '—' }}
              </div>
              <v-chip
                v-if="item.menu?.is_default"
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ t('menus.list.default_badge') }}
              </v-chip>
            </div>
          </div>
        </template>

        <!-- Time column -->
        <template #item.time="{ item }">
          <div
            v-if="item.available_from || item.available_until"
            class="d-flex align-center gap-1"
          >
            <v-icon
              icon="mdi-clock-outline"
              size="14"
              class="text-medium-emphasis"
            />
            <span class="text-body-2">
              {{ item.available_from || '00:00' }} –
              {{ item.available_until || '23:59' }}
            </span>
          </div>
          <span v-else class="text-body-2 text-medium-emphasis">{{ t('branch_menu.all_day') }}</span>
        </template>

        <!-- Days column -->
        <template #item.days_of_week="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <template v-if="item.days_of_week && item.days_of_week.length">
              <v-chip
                v-for="day in item.days_of_week"
                :key="day"
                size="x-small"
                color="info"
                variant="tonal"
              >
                {{ DAY_LABELS[day] }}
              </v-chip>
            </template>
            <span v-else class="text-body-2 text-medium-emphasis">
              {{ t('menus.assign.every_day') }}
            </span>
          </div>
        </template>

        <!-- Available now column -->
        <template #item.available_now="{ item }">
          <v-chip
            :color="isAvailableNow(item) ? 'success' : 'default'"
            size="small"
            variant="tonal"
            :prepend-icon="
              isAvailableNow(item)
                ? 'mdi-check-circle'
                : 'mdi-clock-plus-outline'
            "
          >
            {{ isAvailableNow(item) ? t('tables.status.available') : t('modifiers.page.unavailable') }}
          </v-chip>
        </template>

        <!-- Sort order column -->
        <template #item.sort_order="{ item }">
          <v-chip size="small" variant="tonal" color="secondary">
            {{ item.sort_order ?? 0 }}
          </v-chip>
        </template>

        <!-- Actions column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-end">
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="primary"
              @click="openEditDialog(item)"
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
              icon="mdi-book-open-variant"
              size="48"
              color="medium-emphasis"
              class="mb-3"
            />
            <p class="text-body-1 text-medium-emphasis">
              {{ t('branch_menu.empty') }}
            </p>
            <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-plus"
              class="mt-3"
              @click="openCreateDialog"
            >
              {{ t('branch_menu.assign_first') }}
            </v-btn>
          </div>
        </template>
      </v-data-table>
    </v-card>
    <!-- ── Create / Edit Dialog ─────────────────────────────────────────────── -->
    <BranchMenuFormDialog
      v-model="dialog"
      :edit-item="editItem"
      :branches="branchStore.branches.data"
      :menus="menuStore.menus"
      @saved="onSaved"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useBranchMenuStore } from '@/stores/branchMenuStore'
  import BranchMenuFormDialog from '@/components/catalogs/BranchMenuFormDialog.vue'
  import { useBranchStore } from '@/stores/branchStore'
  import { useMenuStore } from '@/stores/menuStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  // ── Store ─────────────────────────────────────────────────────────────────────
  const store = useBranchMenuStore()
  const branchStore = useBranchStore()
  const menuStore = useMenuStore()
  // ── Day labels ────────────────────────────────────────────────────────────────
  const DAY_LABELS = computed(() => [
    t('menus.assign.day_sun'),
    t('menus.assign.day_mon'),
    t('menus.assign.day_tue'),
    t('menus.assign.day_wed'),
    t('menus.assign.day_thu'),
    t('menus.assign.day_fri'),
    t('menus.assign.day_sat')
  ])

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('form.branch'), key: 'branch', sortable: true },
    { title: t('menus.assign.menu'), key: 'menu', sortable: true },
    { title: t('branch_menu.time_window'), key: 'time', sortable: false },
    { title: t('branch_menu.days'), key: 'days_of_week', sortable: false },
    { title: t('form.status'), key: 'available_now', sortable: false },
    { title: t('branch_menu.sort_order'), key: 'sort_order', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

  // ── Filters ───────────────────────────────────────────────────────────────────
  const filters = ref({ branch_id: null, menu_id: null })
  const menus = ref([]) // TODO: load from menu store
  const showFilters = ref(false)

  // ── Active filter badge ───────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (filters.value.branch_id) count++
    if (filters.value.menu_id) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // ── Dialog state ──────────────────────────────────────────────────────────────
  const dialog = ref(false)
  const editItem = ref(null)

  // ── Load data ─────────────────────────────────────────────────────────────────
  const loadData = () => {
    store.fetchAll()
    branchStore.fetchBranches()
    menuStore.fetchMenus()
  }

  // ── Reset to page 1 and reload when filters change ───────────────────────────
  const onFilterChange = () => {
    loadData()
  }

  const resetFilters = () => {
    filters.value = { branch_id: null, menu_id: null }
    loadData()
  }

  // ── Available now check ───────────────────────────────────────────────────────
  const isAvailableNow = item => {
    const now = new Date()
    const today = now.getDay()
    const nowTime = now.toTimeString().slice(0, 5)

    if (item.days_of_week?.length && !item.days_of_week.includes(today))
      return false
    if (item.available_from && nowTime < item.available_from) return false
    if (item.available_until && nowTime > item.available_until) return false
    return true
  }

  // ── Dialog actions ────────────────────────────────────────────────────────────
  const openCreateDialog = () => {
    editItem.value = null
    dialog.value = true
  }

  const openEditDialog = item => {
    editItem.value = { ...item }
    dialog.value = true
  }

  const onSaved = async payload => {
    let res
    if (payload.id) {
      // Existing assignment → update
      res = await store.update(payload.id, payload)
      notif(t('menus.list.messages.assignment_updated'), {
        type: 'success'
      })
    } else {
      // New assignment → create
      res = await store.create(payload)
      notif(t('menus.list.messages.menu_assigned'), {
        type: 'success'
      })
    }

    if (res.success) loadData()
    else
      notif(t('branch_menu.operation_failed'), {
        type: 'error'
      })
  }

  const confirmDelete = async menu => {
    confirm({
      title: t('branch_menu.remove_title'),
      message: t('branch_menu.remove_message', { name: menu.menu.name }),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        store.remove(menu.id)

        notif(t('messages.deleted_success'), {
          type: 'success'
        })
      }
    })
  }

  // ── Init ──────────────────────────────────────────────────────────────────────
  onMounted(() => loadData())
</script>
